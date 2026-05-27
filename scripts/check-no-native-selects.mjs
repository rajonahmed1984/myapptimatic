import { execSync } from "node:child_process";
import { readFileSync } from "node:fs";

const trackedFiles = execSync("git ls-files -z", { encoding: "utf8" })
    .split("\0")
    .filter(Boolean);

// Policy update: native selects are allowed in React/Inertia forms for static option sets.
// Keep this guard for Blade templates where select standardization remains enforced.
const scanRoots = ["resources/views/"];
const nativeSelectPattern = /<\s*select\b/gi;

const violations = [];

function lineNumberFromIndex(content, index) {
    return content.slice(0, index).split(/\r?\n/).length;
}

for (const file of trackedFiles) {
    if (!scanRoots.some((root) => file.startsWith(root))) {
        continue;
    }

    let content;
    try {
        content = readFileSync(file, "utf8");
    } catch {
        continue;
    }

    let match;
    while ((match = nativeSelectPattern.exec(content)) !== null) {
        const line = lineNumberFromIndex(content, match.index);
        const lineText = content.split(/\r?\n/)[line - 1] || "";

        violations.push({
            file,
            line,
            excerpt: lineText.trim(),
        });
    }

    nativeSelectPattern.lastIndex = 0;
}

if (violations.length > 0) {
    console.error("Native select guard failed in Blade templates:\n");
    for (const item of violations) {
        console.error(`- ${item.file}:${item.line} ${item.excerpt}`);
    }
    process.exit(1);
}

console.log("Native select guard passed.");
