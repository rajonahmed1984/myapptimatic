#!/usr/bin/env python3
import csv
import json
import re
from collections import defaultdict
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
REPORT_DIR = ROOT / "storage/app/migration-reports"
REPORT_DIR.mkdir(parents=True, exist_ok=True)


def read_text_auto(path: Path) -> str:
    raw = path.read_bytes()
    if raw.startswith(b"\xff\xfe") or raw.startswith(b"\xfe\xff"):
        return raw.decode("utf-16")
    if raw.startswith(b"\xef\xbb\xbf"):
        return raw.decode("utf-8-sig")
    return raw.decode("utf-8")


def read_json_auto(path: Path) -> Any:
    return json.loads(read_text_auto(path))


def read_json_optional(path: Path, default: Any) -> Any:
    if not path.exists():
        return default
    return read_json_auto(path)


def php_class_to_file(class_name: str) -> Path | None:
    if not class_name.startswith("App\\"):
        return None
    rel = class_name.replace("App\\", "app\\").replace("\\", "/") + ".php"
    fp = ROOT / rel
    return fp if fp.exists() else None


def extract_brace_block(text: str, open_idx: int) -> tuple[str, int] | tuple[None, None]:
    depth = 0
    i = open_idx
    n = len(text)
    state = "normal"
    heredoc_end = None

    while i < n:
        ch = text[i]
        nxt = text[i + 1] if i + 1 < n else ""

        if state == "normal":
            if ch == "/" and nxt == "/":
                state = "line_comment"
                i += 2
                continue
            if ch == "/" and nxt == "*":
                state = "block_comment"
                i += 2
                continue
            if ch == "#":
                state = "line_comment"
                i += 1
                continue
            if ch == "'":
                state = "single"
                i += 1
                continue
            if ch == '"':
                state = "double"
                i += 1
                continue
            if text[i : i + 3] == "<<<":
                m = re.match(r"<<<\s*['\"]?([A-Za-z_][A-Za-z0-9_]*)['\"]?", text[i:])
                if m:
                    heredoc_end = m.group(1)
                    state = "heredoc"
                    i += m.end()
                    continue
            if ch == "{":
                depth += 1
            elif ch == "}":
                depth -= 1
                if depth == 0:
                    return text[open_idx : i + 1], i
            i += 1
            continue

        if state == "single":
            if ch == "\\":
                i += 2
                continue
            if ch == "'":
                state = "normal"
            i += 1
            continue

        if state == "double":
            if ch == "\\":
                i += 2
                continue
            if ch == '"':
                state = "normal"
            i += 1
            continue

        if state == "line_comment":
            if ch == "\n":
                state = "normal"
            i += 1
            continue

        if state == "block_comment":
            if ch == "*" and nxt == "/":
                state = "normal"
                i += 2
                continue
            i += 1
            continue

        if state == "heredoc":
            line_end = text.find("\n", i)
            if line_end == -1:
                line_end = n
            line = text[i:line_end].strip()
            if heredoc_end and (line == heredoc_end or line == heredoc_end + ";"):
                state = "normal"
                heredoc_end = None
            i = line_end + 1
            continue

    return None, None


METHOD_DEF_RE = re.compile(r"\bfunction\s+&?\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(")


def parse_methods(file_text: str) -> dict[str, dict[str, Any]]:
    methods: dict[str, dict[str, Any]] = {}
    for m in METHOD_DEF_RE.finditer(file_text):
        name = m.group(1)
        brace = file_text.find("{", m.end())
        if brace == -1:
            continue
        block, _ = extract_brace_block(file_text, brace)
        if block is None:
            continue
        methods[name] = {"start": m.start(), "body": block}
    return methods


@dataclass
class BodySignals:
    base: str
    inertia: bool
    has_view: bool
    view_names: list[str]
    non_ui_signal: bool
    delegated_calls: list[str]
    route_redirect_signal: bool


def infer_view_kind(view_name: str, blade_categories: dict[str, str]) -> str:
    key = view_name.replace(".", "/") + ".blade.php"
    category = blade_categories.get(key)
    if category in {"partials", "layout"}:
        return "partial"
    if category in {"pdf"}:
        return "pdf"
    if category in {"email"}:
        return "email"
    if category in {"full_page"}:
        return "full"
    parts = view_name.split(".")
    if "partials" in parts or any(part.startswith("_") for part in parts):
        return "partial"
    return "unknown"


def classify_body(body: str, blade_categories: dict[str, str]) -> BodySignals:
    lower = body.lower()
    inertia = bool(re.search(r"\bInertia::render\s*\(|\binertia\s*\(", body))
    view_names: list[str] = []
    for pattern in [
        r"\bview\s*\(\s*['\"]([^'\"]+)['\"]",
        r"response\s*\(\s*\)\s*->\s*view\s*\(\s*['\"]([^'\"]+)['\"]",
        r"View::make\s*\(\s*['\"]([^'\"]+)['\"]",
        r"Route::view\s*\([^,]+,\s*['\"]([^'\"]+)['\"]",
    ]:
        view_names.extend(re.findall(pattern, body, flags=re.I))
    view_names = sorted(set(view_names))
    has_view = bool(view_names)

    non_ui_tokens = [
        "response()->json",
        "->json(",
        "jsonresponse",
        "streamedresponse",
        "binaryfileresponse",
        "->download(",
        "->stream(",
        "response()->file",
        "storage::disk(",
        "->response(",
        "redirect()->",
        "redirect(",
        "to_route(",
        "abort(",
    ]
    non_ui_signal = any(tok in lower for tok in non_ui_tokens)

    delegated_calls = re.findall(r"(?:\$this->|self::|static::)([A-Za-z_][A-Za-z0-9_]*)\s*\(", body)
    route_redirect_signal = "redirect()->route(" in lower or "redirect()->to(" in lower

    view_kinds = [infer_view_kind(v, blade_categories) for v in view_names]

    if inertia and not has_view:
        base = "Inertia"
    elif has_view:
        if view_kinds and all(kind in {"pdf", "email"} for kind in view_kinds):
            base = "Non-UI"
        elif view_kinds and all(kind == "partial" for kind in view_kinds):
            base = "Partial/Fragment"
        elif any(kind == "full" for kind in view_kinds):
            base = "Blade UI"
        elif inertia:
            base = "Partial/Fragment"
        else:
            base = "Blade UI"
    elif non_ui_signal:
        base = "Non-UI"
    else:
        base = "Unknown"

    return BodySignals(
        base=base,
        inertia=inertia,
        has_view=has_view,
        view_names=view_names,
        non_ui_signal=non_ui_signal,
        delegated_calls=delegated_calls,
        route_redirect_signal=route_redirect_signal,
    )


class ControllerAnalyzer:
    def __init__(self, blade_categories: dict[str, str]) -> None:
        self.blade_categories = blade_categories
        self.cache: dict[str, dict[str, Any]] = {}

    def _load(self, class_name: str) -> dict[str, Any]:
        if class_name in self.cache:
            return self.cache[class_name]
        fp = php_class_to_file(class_name)
        if fp is None:
            self.cache[class_name] = {"file": None, "methods": {}, "text": ""}
            return self.cache[class_name]
        text = read_text_auto(fp)
        methods = parse_methods(text)
        self.cache[class_name] = {"file": fp, "methods": methods, "text": text}
        return self.cache[class_name]

    def analyze(self, class_name: str, method_name: str, visited: set[tuple[str, str]] | None = None) -> dict[str, Any]:
        if visited is None:
            visited = set()
        key = (class_name, method_name)
        if key in visited:
            return {
                "classification": "Unknown",
                "reason": "recursive delegation",
                "file": None,
                "method": method_name,
                "signals": None,
                "view_names": [],
                "delegated_to": [],
            }
        visited.add(key)

        meta = self._load(class_name)
        methods: dict[str, dict[str, Any]] = meta["methods"]
        file_path: Path | None = meta["file"]
        method = methods.get(method_name)
        if method is None and method_name != "__invoke":
            method_name = "__invoke"
            method = methods.get(method_name)
        if method is None:
            return {
                "classification": "Unknown",
                "reason": "method not found",
                "file": file_path.relative_to(ROOT).as_posix() if file_path else None,
                "method": method_name,
                "signals": None,
                "view_names": [],
                "delegated_to": [],
            }

        signals = classify_body(method["body"], self.blade_categories)
        classification = signals.base
        delegated = []

        if signals.delegated_calls and classification in {"Unknown", "Non-UI", "Partial/Fragment"}:
            for callee in sorted(set(signals.delegated_calls)):
                if callee == method_name:
                    continue
                if callee not in methods:
                    continue
                sub = self.analyze(class_name, callee, visited=set(visited))
                delegated.append(sub)
            if delegated:
                for candidate in ["Blade UI", "Inertia", "Partial/Fragment", "Non-UI", "Unknown"]:
                    if any(d["classification"] == candidate for d in delegated):
                        classification = candidate
                        break

        if signals.inertia and signals.has_view:
            full_views = [v for v in signals.view_names if infer_view_kind(v, self.blade_categories) == "full"]
            classification = "Blade UI" if full_views else "Partial/Fragment"

        if signals.non_ui_signal and not signals.has_view and not signals.inertia:
            classification = "Non-UI"

        if classification in {"Unknown", "Partial/Fragment"} and signals.route_redirect_signal and not signals.has_view and not signals.inertia:
            classification = "Non-UI"

        reason_parts = []
        if signals.inertia:
            reason_parts.append("Inertia::render detected")
        if signals.view_names:
            reason_parts.append("views: " + ", ".join(signals.view_names[:4]))
        if signals.non_ui_signal:
            reason_parts.append("non-UI response signal detected")
        if delegated:
            reason_parts.append("delegates to: " + ", ".join(sorted({d["method"] for d in delegated})))

        return {
            "classification": classification,
            "reason": "; ".join(reason_parts) if reason_parts else "no explicit render signal",
            "file": file_path.relative_to(ROOT).as_posix() if file_path else None,
            "method": method_name,
            "signals": signals,
            "view_names": signals.view_names,
            "delegated_to": delegated,
        }

    def method_body(self, class_name: str, method_name: str) -> str:
        meta = self._load(class_name)
        methods: dict[str, dict[str, Any]] = meta["methods"]
        method = methods.get(method_name)
        if method is None and method_name != "__invoke":
            method = methods.get("__invoke")
        return "" if method is None else method["body"]


def classify_closure(route: dict[str, Any]) -> tuple[str, str]:
    uri = (route.get("uri") or "").lower()
    name = (route.get("name") or "").lower()
    middleware = [m.lower() for m in (route.get("middleware") or [])]

    if uri == "__ui/react-sandbox" or name == "ui.react-sandbox":
        return "Inertia", "closure route returns Inertia sandbox page"
    if name.endswith(".legacy"):
        return "Non-UI", "legacy redirect alias"
    if name in {"employee.home", "sales.home", "support.home"}:
        return "Non-UI", "portal redirect route"
    if uri.startswith("storage/") or "/attachment" in uri or uri.startswith("branding/"):
        return "Non-UI", "asset/file closure endpoint"
    if uri.startswith("v1/") or "license-risk" in name:
        return "Non-UI", "mock API endpoint"
    if uri == "up":
        return "Non-UI", "health endpoint"
    if any("payment-callbacks" in m for m in middleware) or "payments/" in uri:
        return "Non-UI", "payment callback closure endpoint"
    if name.startswith("generated::"):
        return "Non-UI", "generated closure endpoint"
    return "Unknown", "closure route requires manual verification"


def route_non_ui_override(route: dict[str, Any], classification: str, view_names: list[str]) -> tuple[str, str | None]:
    uri = (route.get("uri") or "").lower()
    name = (route.get("name") or "").lower()
    middleware = [m.lower() for m in (route.get("middleware") or [])]

    if classification == "Inertia":
        return classification, None

    uri_tokens = [
        "callback",
        "webhook",
        "download",
        "export",
        "attachment",
        "stream",
        "upload",
        "inline",
        "storage/",
        "_ignition",
        "sanctum",
    ]
    if any(token in uri for token in uri_tokens):
        return "Non-UI", "URI token marks endpoint as non-UI"

    if uri.startswith("cron/") or any("restrict.cron" in m for m in middleware):
        return "Non-UI", "cron endpoint excluded from UI audit"

    if any("payment-callbacks" in m for m in middleware) or name.startswith("payments."):
        return "Non-UI", "payment callback endpoint excluded from UI audit"

    if view_names and all(v.endswith(".pdf") or ".pdf" in v for v in view_names):
        return "Non-UI", "PDF generation endpoint"

    return classification, None


def is_get_route(route: dict[str, Any]) -> bool:
    methods = (route.get("method") or "").split("|")
    return "GET" in methods


def build_blade_inventory() -> tuple[list[dict[str, Any]], dict[str, str]]:
    blade_files = sorted((ROOT / "resources/views").rglob("*.blade.php"))
    blade_categories: dict[str, str] = {}
    inventory: list[dict[str, Any]] = []
    for bf in blade_files:
        rel = bf.relative_to(ROOT / "resources/views").as_posix()
        if rel.startswith("emails/"):
            category = "email"
        elif rel.startswith("layouts/") or rel.startswith("react-"):
            category = "layout"
        elif "/partials/" in rel or rel.split("/")[-1].startswith("_"):
            category = "partials"
        elif rel.startswith("components/"):
            category = "component"
        elif rel.endswith("/pdf.blade.php") or "/pdf." in rel:
            category = "pdf"
        else:
            category = "full_page"
        blade_categories[rel] = category
        inventory.append({"path": rel, "category": category})
    return inventory, blade_categories


def map_blade_references(blade_inventory: list[dict[str, Any]]) -> list[dict[str, Any]]:
    refs = defaultdict(list)
    patterns = [
        r"\bview\s*\(\s*['\"]([^'\"]+)['\"]",
        r"response\s*\(\s*\)\s*->\s*view\s*\(\s*['\"]([^'\"]+)['\"]",
        r"View::make\s*\(\s*['\"]([^'\"]+)['\"]",
        r"Route::view\s*\([^,]+,\s*['\"]([^'\"]+)['\"]",
        r"@include(?:If|When|Unless|First)?\s*\(\s*['\"]([^'\"]+)['\"]",
        r"@extends\s*\(\s*['\"]([^'\"]+)['\"]",
        r"@component\s*\(\s*['\"]([^'\"]+)['\"]",
    ]

    for root in [ROOT / "app", ROOT / "routes", ROOT / "resources/views"]:
        for fp in root.rglob("*"):
            if fp.is_dir() or fp.suffix.lower() not in {".php", ".blade.php"}:
                continue
            try:
                text = read_text_auto(fp)
            except Exception:
                continue
            for pattern in patterns:
                for m in re.finditer(pattern, text, flags=re.I):
                    view_name = m.group(1)
                    key = view_name.replace(".", "/") + ".blade.php"
                    refs[key].append(
                        {
                            "file": fp.relative_to(ROOT).as_posix(),
                            "line": text[: m.start()].count("\n") + 1,
                            "view_name": view_name,
                        }
                    )

    for item in blade_inventory:
        item["references"] = refs.get(item["path"], [])
        item["referenced"] = bool(item["references"])
    return blade_inventory


def main() -> None:
    routes_latest = read_json_auto(ROOT / "storage/app/routes-latest.json")
    routes_current = read_json_optional(ROOT / "storage/app/routes-current.json", [])
    blade_route_candidates = read_json_optional(ROOT / "storage/app/blade-route-candidates.json", [])
    blade_ui_get_candidates = read_json_optional(ROOT / "storage/app/blade-ui-get-candidates.json", [])

    blade_inventory, blade_categories = build_blade_inventory()
    blade_inventory = map_blade_references(blade_inventory)
    analyzer = ControllerAnalyzer(blade_categories)

    route_rows: list[dict[str, Any]] = []
    inertia_components: set[str] = set()

    for route in routes_latest:
        if not is_get_route(route):
            continue

        method = route.get("method") or ""
        uri = route.get("uri") or ""
        name = route.get("name")
        action = route.get("action") or ""
        middleware = route.get("middleware") or []
        uses_wrapper = any("ConvertAdminViewToInertia" in mw for mw in middleware)

        classification = "Unknown"
        reason = ""
        controller_file = None
        controller_method = None
        view_names: list[str] = []

        if action == "Closure":
            classification, reason = classify_closure(route)
        elif "Illuminate\\Routing\\RedirectController" in action:
            classification = "Non-UI"
            reason = "redirect controller route"
        elif action.startswith("App\\"):
            if "@" in action:
                cls, meth = action.split("@", 1)
            else:
                cls, meth = action, "__invoke"
            analyzed = analyzer.analyze(cls, meth)
            classification = analyzed["classification"]
            reason = analyzed["reason"]
            controller_file = analyzed["file"]
            controller_method = analyzed["method"]
            view_names = analyzed["view_names"]
            body = analyzer.method_body(cls, meth)
            inertia_components.update(re.findall(r"Inertia::render\s*\(\s*['\"]([^'\"]+)['\"]", body))
            inertia_components.update(re.findall(r"\binertia\s*\(\s*['\"]([^'\"]+)['\"]", body))
        else:
            classification = "Non-UI"
            reason = "framework/vendor endpoint"

        classification, override_reason = route_non_ui_override(route, classification, view_names)
        if override_reason:
            reason = f"{reason}; {override_reason}" if reason else override_reason

        is_ui_candidate = classification in {"Inertia", "Blade UI", "Partial/Fragment", "Unknown"}

        route_rows.append(
            {
                "method": method,
                "uri": uri,
                "name": name,
                "action": action,
                "classification": classification,
                "ui_candidate": is_ui_candidate,
                "uses_convert_admin_view_to_inertia": uses_wrapper,
                "controller_file": controller_file,
                "controller_method": controller_method,
                "view_names": ";".join(view_names),
                "reason": reason,
                "middleware": "|".join(middleware),
            }
        )

    for route_file in (ROOT / "routes").glob("*.php"):
        text = read_text_auto(route_file)
        inertia_components.update(re.findall(r"Inertia::render\s*\(\s*['\"]([^'\"]+)['\"]", text))
        inertia_components.update(re.findall(r"\binertia\s*\(\s*['\"]([^'\"]+)['\"]", text))

    missing_components = []
    for comp in sorted(inertia_components):
        if not (ROOT / "resources/js/react/Pages" / f"{comp}.jsx").exists():
            missing_components.append(comp)

    resolver = {
        "app_file": "resources/js/react/app.jsx",
        "uses_pages_glob": False,
        "glob_pattern": None,
        "resolver_expression": None,
    }
    app_file = ROOT / "resources/js/react/app.jsx"
    if app_file.exists():
        app_text = read_text_auto(app_file)
        gm = re.search(r"import\.meta\.glob\(\s*['\"]([^'\"]+)['\"]", app_text)
        if gm:
            resolver["uses_pages_glob"] = True
            resolver["glob_pattern"] = gm.group(1)
        if "./Pages/${name}.jsx" in app_text:
            resolver["resolver_expression"] = "./Pages/${name}.jsx"

    wrapper_routes = [r for r in route_rows if r["uses_convert_admin_view_to_inertia"]]
    wrapper_ui = [r for r in wrapper_routes if r["ui_candidate"]]
    wrapper_non_ui = [r for r in wrapper_routes if r["classification"] == "Non-UI"]

    blade_ui_refs = []
    for item in blade_inventory:
        if item["category"] in {"email", "pdf"} or not item["referenced"]:
            continue
        rel_refs = [r for r in item["references"] if r["file"].startswith("app/") or r["file"].startswith("routes/")]
        if rel_refs:
            blade_ui_refs.append({"path": item["path"], "category": item["category"], "references": rel_refs})

    get_routes_total = sum(1 for r in routes_latest if is_get_route(r))
    ui_candidates = [r for r in route_rows if r["ui_candidate"]]
    direct_inertia_ui = [r for r in route_rows if r["classification"] == "Inertia"]
    blade_ui_routes = [r for r in route_rows if r["classification"] == "Blade UI"]
    partial_routes = [r for r in route_rows if r["classification"] == "Partial/Fragment"]
    non_ui_routes = [r for r in route_rows if r["classification"] == "Non-UI"]
    unknown_ui = [r for r in route_rows if r["ui_candidate"] and r["classification"] == "Unknown"]

    baseline_lookup: dict[tuple[str, str], dict[str, Any]] = {}
    for row in blade_ui_get_candidates:
        baseline_lookup[(row.get("Name") or "", row.get("Uri") or "")] = row
    baseline_status = []
    for row in route_rows:
        key = (row.get("name") or "", row.get("uri") or "")
        if key in baseline_lookup:
            baseline_status.append(
                {
                    "name": row["name"],
                    "uri": row["uri"],
                    "action": row["action"],
                    "latest_classification": row["classification"],
                    "reason": row["reason"],
                    "baseline_partial_only": baseline_lookup[key].get("PartialOnly"),
                }
            )

    pass_gate = len(blade_ui_routes) == 0 and len(unknown_ui) == 0 and len(missing_components) == 0
    verdict_message = "✅ FULL FRONTEND REACT MIGRATION COMPLETE" if pass_gate else "❌ MIGRATION INCOMPLETE"

    summary = {
        "audit_timestamp": datetime.now().isoformat(),
        "inputs": {
            "routes_latest": "storage/app/routes-latest.json",
            "routes_current": "storage/app/routes-current.json",
            "blade_route_candidates": "storage/app/blade-route-candidates.json",
            "blade_ui_get_candidates": "storage/app/blade-ui-get-candidates.json",
        },
        "baseline": {
            "routes_current_total": len(routes_current),
            "blade_route_candidates_total": len(blade_route_candidates),
            "blade_ui_get_candidates_total": len(blade_ui_get_candidates),
            "baseline_candidate_status": baseline_status,
        },
        "totals": {
            "total_routes": len(routes_latest),
            "get_routes_total": get_routes_total,
            "ui_candidate_get_routes": len(ui_candidates),
            "direct_inertia_ui_routes": len(direct_inertia_ui),
            "remaining_blade_ui_routes": len(blade_ui_routes),
            "partial_fragment_endpoints": len(partial_routes),
            "non_ui_endpoints_excluded": len(non_ui_routes),
            "unknown_ui_routes": len(unknown_ui),
            "convert_admin_view_to_inertia_routes_total": len(wrapper_routes),
            "convert_admin_view_to_inertia_ui_subset": len(wrapper_ui),
            "convert_admin_view_to_inertia_non_ui_subset": len(wrapper_non_ui),
        },
        "inertia_pages": {
            "components_detected": sorted(inertia_components),
            "missing_components": missing_components,
            "resolver": resolver,
        },
        "verdict": {"pass": pass_gate, "message": verdict_message},
    }

    summary_path = REPORT_DIR / "ui-migration-audit-summary.json"
    details_path = REPORT_DIR / "ui-migration-audit-details.csv"
    blockers_path = REPORT_DIR / "ui-migration-audit-blockers.md"

    summary_path.write_text(json.dumps(summary, indent=2), encoding="utf-8")

    with details_path.open("w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(
            f,
            fieldnames=[
                "method",
                "uri",
                "name",
                "action",
                "classification",
                "ui_candidate",
                "uses_convert_admin_view_to_inertia",
                "controller_file",
                "controller_method",
                "view_names",
                "reason",
                "middleware",
            ],
        )
        writer.writeheader()
        for row in sorted(route_rows, key=lambda r: (r["classification"], r["uri"])):
            writer.writerow(row)

    lines = [
        "# UI Migration Audit Blockers",
        "",
        f"Verdict: {verdict_message}",
        "",
        "## Executive Summary",
    ]
    for key, value in summary["totals"].items():
        lines.append(f"- {key}: {value}")

    lines += ["", "## Remaining Blade UI Routes"]
    if blade_ui_routes:
        for r in blade_ui_routes:
            lines.append(f"- `{r['method']}` `{r['uri']}` ({r['name']}) -> `{r['action']}`")
            lines.append(f"  - file: `{r['controller_file']}` method: `{r['controller_method']}`")
            lines.append(f"  - reason: {r['reason']}")
    else:
        lines.append("- None")

    lines += ["", "## Unknown UI GET Routes"]
    if unknown_ui:
        for r in unknown_ui:
            lines.append(f"- `{r['method']}` `{r['uri']}` ({r['name']}) -> `{r['action']}`")
            lines.append(f"  - file: `{r['controller_file']}` method: `{r['controller_method']}`")
            lines.append(f"  - reason: {r['reason']}")
    else:
        lines.append("- None")

    lines += ["", "## ConvertAdminViewToInertia Dependency (UI subset)"]
    if wrapper_ui:
        for r in wrapper_ui:
            lines.append(f"- `{r['method']}` `{r['uri']}` ({r['name']}) [{r['classification']}] -> `{r['action']}`")
    else:
        lines.append("- None")

    lines += ["", "## Missing Inertia Pages"]
    if missing_components:
        for comp in missing_components:
            lines.append(f"- `{comp}` -> `resources/js/react/Pages/{comp}.jsx` missing")
    else:
        lines.append("- None")

    lines += ["", "## Blade UI Files Still Referenced from Routes/Controllers"]
    if blade_ui_refs:
        for item in blade_ui_refs:
            refs = "; ".join(f"{r['file']}:{r['line']}" for r in item["references"][:3])
            lines.append(f"- `{item['path']}` ({item['category']}) refs: {refs}")
    else:
        lines.append("- None")

    blockers_path.write_text("\n".join(lines), encoding="utf-8")

    print(f"Report written: {summary_path}")
    print(f"Report written: {details_path}")
    print(f"Report written: {blockers_path}")
    safe_verdict = verdict_message.replace("✅ ", "").replace("❌ ", "")
    print(f"Verdict: {safe_verdict}")
    print("Totals:", json.dumps(summary["totals"]))


if __name__ == "__main__":
    main()
