// Syncs capacitor.config.json with the mobile settings kept in .env so the native
// shells always point at the right Laravel host without editing the config by hand.
//
//   MOBILE_SERVER_URL  the Laravel origin the WebView loads (required, no trailing slash)
//   MOBILE_APP_ID      native bundle identifier (reverse-DNS)
//   MOBILE_APP_NAME    the name shown under the launcher icon
//
// Run directly with `npm run mobile:config`; it also runs ahead of every sync/open script.

import { readFileSync, writeFileSync, existsSync } from "node:fs";
import { resolve } from "node:path";

const rootDir = process.cwd();
const envPath = resolve(rootDir, ".env");
const configPath = resolve(rootDir, "capacitor.config.json");

function readEnv(path) {
    if (!existsSync(path)) {
        return {};
    }

    const values = {};

    for (const rawLine of readFileSync(path, "utf8").split(/\r?\n/)) {
        const line = rawLine.trim();
        if (line === "" || line.startsWith("#")) {
            continue;
        }

        const separator = line.indexOf("=");
        if (separator === -1) {
            continue;
        }

        const key = line.slice(0, separator).trim();
        let value = line.slice(separator + 1).trim();

        if (
            (value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }

        values[key] = value;
    }

    return values;
}

const env = { ...readEnv(envPath), ...process.env };
const config = JSON.parse(readFileSync(configPath, "utf8"));

const serverUrl = (env.MOBILE_SERVER_URL || "").trim().replace(/\/+$/, "");
const appId = (env.MOBILE_APP_ID || "").trim();
const appName = (env.MOBILE_APP_NAME || "").trim();

if (serverUrl === "") {
    console.error(
        "MOBILE_SERVER_URL is not set in .env.\n" +
            "Point it at the Laravel origin the app should load, e.g.\n" +
            "  MOBILE_SERVER_URL=https://app.myapptimatic.com\n" +
            "  MOBILE_SERVER_URL=http://192.168.0.10:8000   (local device testing)"
    );
    process.exit(1);
}

let parsed;
try {
    parsed = new URL(serverUrl);
} catch {
    console.error(`MOBILE_SERVER_URL is not a valid absolute URL: ${serverUrl}`);
    process.exit(1);
}

if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
    console.error(`MOBILE_SERVER_URL must use http:// or https://, got ${parsed.protocol}`);
    process.exit(1);
}

const isPlainHttp = parsed.protocol === "http:";

config.server = {
    ...config.server,
    url: serverUrl,
    // Plain http only survives the WebView when cleartext is explicitly allowed,
    // which is what local `php artisan serve` testing on a device needs.
    cleartext: isPlainHttp,
};

config.android = {
    ...config.android,
    allowMixedContent: isPlainHttp,
};

if (appId !== "") {
    config.appId = appId;
}

if (appName !== "") {
    config.appName = appName;
}

writeFileSync(configPath, `${JSON.stringify(config, null, 2)}\n`, "utf8");

console.log(`capacitor.config.json updated`);
console.log(`  appId   ${config.appId}`);
console.log(`  appName ${config.appName}`);
console.log(`  server  ${config.server.url}${isPlainHttp ? "  (cleartext enabled)" : ""}`);

if (isPlainHttp) {
    console.log(
        "\nWarning: http:// is for local testing only. Ship release builds with an https:// origin."
    );
}
