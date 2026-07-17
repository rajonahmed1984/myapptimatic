import { existsSync, readFileSync, statSync } from 'node:fs';
import { readdir } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const read = (file) => readFileSync(path.join(root, file), 'utf8');
const failures = [];

const assert = (condition, message) => {
    if (!condition) {
        failures.push(message);
    }
};

const appSource = read('resources/js/app.jsx');
const dateEnhancerSource = read('resources/js/utils/easyDateEnhancer.js');
const emailManageSource = read('resources/js/Pages/Admin/ApptimaticEmail/Manage.jsx');

assert(
    !/^\s*import\s+['"]\.\/http-client['"];?/m.test(appSource),
    'The global app entry must not eagerly import Axios/http-client.'
);
assert(
    !/^\s*import\s+['"]flatpickr\/dist\/flatpickr\.min\.css['"];?/m.test(appSource),
    'The global app entry must not eagerly import Flatpickr CSS.'
);
assert(
    /import\(['"]flatpickr['"]\)/.test(dateEnhancerSource)
        && /import\(['"]flatpickr\/dist\/flatpickr\.min\.css['"]\)/.test(dateEnhancerSource),
    'The easy date enhancer must lazy-load Flatpickr JS and CSS.'
);
assert(
    /import axios from ['"]\.\.\/\.\.\/\.\.\/http-client['"]/.test(emailManageSource),
    'The email management page must import its scoped Axios client.'
);
assert(
    /inertiaRouter\.prefetch\(/.test(appSource) && /cacheFor:\s*['"]30s['"]/.test(appSource),
    'The Inertia navigation bridge must retain its short-lived prefetch cache.'
);

const collectJavaScriptFiles = async (directory) => {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        const absolutePath = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            files.push(...await collectJavaScriptFiles(absolutePath));
        } else if (/\.(?:js|jsx|mjs)$/.test(entry.name)) {
            files.push(absolutePath);
        }
    }

    return files;
};

const javascriptFiles = await collectJavaScriptFiles(path.join(root, 'resources/js'));
const globalAxiosReferences = javascriptFiles.filter((file) => (
    readFileSync(file, 'utf8').includes('window.axios')
));

assert(
    globalAxiosReferences.length === 0,
    `Global window.axios references are not allowed: ${globalAxiosReferences
        .map((file) => path.relative(root, file))
        .join(', ')}`
);

const manifestPath = path.join(root, 'public/build/manifest.json');
if (existsSync(manifestPath)) {
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const entry = manifest['resources/js/app.jsx'];

    assert(Boolean(entry?.file), 'The production manifest must contain the app entry.');

    if (entry?.file) {
        const entryPath = path.join(root, 'public/build', entry.file);
        const entryBytes = statSync(entryPath).size;
        const budgetBytes = 440 * 1024;

        assert(
            entryBytes <= budgetBytes,
            `Main app bundle is ${entryBytes} bytes; budget is ${budgetBytes} bytes.`
        );

        const entryCss = entry.css || [];
        assert(
            !entryCss.some((file) => file.toLowerCase().includes('flatpickr')),
            'Flatpickr CSS must remain outside the main app entry.'
        );

        console.log(
            `Frontend entry: ${(entryBytes / 1024).toFixed(2)} KiB / 440.00 KiB budget.`
        );
    }
} else {
    console.log('Production manifest not found; source-level performance checks only.');
}

if (failures.length > 0) {
    console.error('Frontend performance guard failed:\n');
    failures.forEach((failure) => console.error(`- ${failure}`));
    process.exit(1);
}

console.log('Frontend performance guard passed.');
