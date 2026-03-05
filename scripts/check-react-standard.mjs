import { execSync } from "node:child_process";
import { readFileSync } from "node:fs";

const trackedFiles = execSync("git ls-files -z", { encoding: "utf8" })
    .split("\0")
    .filter(Boolean);

const bannedPathPrefix = "resources/js/react/";
const scanRoots = [
    "app/",
    "bootstrap/",
    "config/",
    "resources/js/",
    "resources/views/",
    "routes/",
    "vite.config.js",
    "package.json",
];

const bannedReferencePatterns = [
    {
        label: "legacy React tree reference",
        regex: /resources\/js\/react\//g,
    },
    {
        label: "legacy portal root blade reference",
        regex: /['"]react-(admin|client|employee|guest|public|rep|sandbox|support)['"]/g,
    },
];

const violations = [];

for (const file of trackedFiles) {
    if (file.startsWith(bannedPathPrefix)) {
        violations.push({
            file,
            line: 0,
            label: "legacy file path under resources/js/react",
            excerpt: file,
        });
        continue;
    }

    if (!scanRoots.some((root) => file === root || file.startsWith(root))) {
        continue;
    }

    let content;
    try {
        content = readFileSync(file, "utf8");
    } catch {
        continue;
    }

    const lines = content.split(/\r?\n/);
    lines.forEach((lineText, index) => {
        for (const pattern of bannedReferencePatterns) {
            if (pattern.regex.test(lineText)) {
                violations.push({
                    file,
                    line: index + 1,
                    label: pattern.label,
                    excerpt: lineText.trim(),
                });
            }
            pattern.regex.lastIndex = 0;
        }
    });
}

if (violations.length > 0) {
    console.error("Standard React guard failed. Legacy references detected:\n");
    for (const item of violations) {
        const lineLabel = item.line > 0 ? `:${item.line}` : "";
        console.error(
            `- ${item.file}${lineLabel} [${item.label}] ${item.excerpt}`.trim()
        );
    }
    process.exit(1);
}

console.log("Standard React guard passed.");
