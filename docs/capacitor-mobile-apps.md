# Capacitor Native Apps (Android + iOS)

MyApptimatic now ships with Capacitor native shells:

- `android/` — open in Android Studio, build an APK or AAB
- `ios/` — open in Xcode on a Mac, run or archive for the App Store

## How it works

The app is Laravel + Inertia, so pages are rendered by the server and auth lives in
the Laravel session. That means the native app **cannot** bundle the site as static
files. Instead the shells run in **remote server mode**: the native WebView loads
`MOBILE_SERVER_URL` directly, so sessions, CSRF and every existing route keep
working exactly as they do in a browser.

`mobile/www/index.html` is the only bundled page. Capacitor shows it (via
`server.errorPath`) when the server cannot be reached, so a dropped connection
gives a branded retry screen instead of a WebView error.

`resources/js/native.js` runs only inside the native shell (it is lazily imported
from `resources/js/app.jsx` when `window.Capacitor` is present) and adds:

- Android hardware back button → history back, then portal home, then minimise
- external links opened in the system browser instead of hijacking the WebView
- splash screen hide, status bar colour, keyboard open/close class
- `data-network-status` on `<html>` plus a reload when connectivity returns

The WebView user agent ends with `MyApptimaticApp`, so Laravel can detect app
traffic when needed:

```php
$isNativeApp = str_contains((string) $request->userAgent(), 'MyApptimaticApp');
```

## Configuration

Everything is driven from `.env`:

```
MOBILE_SERVER_URL=https://app.myapptimatic.com
MOBILE_APP_ID=com.myapptimatic.app
MOBILE_APP_NAME=MyApptimatic
```

`npm run mobile:config` writes those values into `capacitor.config.json`. It runs
automatically before every sync. Plain `http://` URLs automatically enable
cleartext traffic so a device can hit `php artisan serve` over the LAN; release
builds must use `https://`.

## Commands

| Command | What it does |
| --- | --- |
| `npm run mobile:config` | Apply the `.env` mobile settings to `capacitor.config.json` |
| `npm run mobile:sync` | Config + copy web assets + update both native projects |
| `npm run mobile:android` | Sync, then open the project in Android Studio |
| `npm run mobile:ios` | Sync, then open the workspace in Xcode (Mac only) |
| `npm run mobile:run:android` | Sync, then build and run on a connected device/emulator |
| `npm run mobile:run:ios` | Sync, then build and run on a simulator/device (Mac only) |

Run `npm run mobile:sync` after changing `.env`, adding a Capacitor plugin, or
pulling changes that touch `mobile/www`.

## Android build

Requirements: Android Studio (Ladybug or newer), JDK 21, Android SDK 36
(compileSdk/targetSdk 36, minSdk 24, AGP 8.13, Gradle 8.14.3).

1. `npm run mobile:android`
2. Let Gradle sync finish.
3. Debug APK: **Build → Build Bundle(s) / APK(s) → Build APK(s)**, or from the
   command line: `cd android && ./gradlew assembleDebug`
   (output: `android/app/build/outputs/apk/debug/app-debug.apk`)
4. Play Store bundle: `cd android && ./gradlew bundleRelease`
   (output: `android/app/build/outputs/bundle/release/app-release.aab`)

Release builds need a signing key. Generate one **outside** the repo, then create
`android/keystore.properties` (already git-ignored):

```
storeFile=C:/keys/myapptimatic.jks
storePassword=...
keyAlias=myapptimatic
keyPassword=...
```

and wire it into `android/app/build.gradle` with a `signingConfigs` block.

## iOS build (Mac only)

Requirements: macOS, Xcode 16+, an Apple Developer account. Capacitor 8 uses
Swift Package Manager, so CocoaPods is not needed.

1. Copy the repo to a Mac (the `ios/` folder is committed, so a `git pull` is enough).
2. `npm install && npm run mobile:ios`
3. In Xcode: select the **App** target → **Signing & Capabilities** → pick your team.
4. Run on a simulator/device, or **Product → Archive** to upload to App Store Connect.

## Testing against a local server

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Set `MOBILE_SERVER_URL=http://<your-lan-ip>:8000` in `.env`, run
`npm run mobile:sync`, and make sure the phone is on the same Wi-Fi. Also add that
origin to `APP_URL`/trusted hosts if Laravel rejects it.

## App icons and splash screens

Icons are still the Capacitor defaults. To brand them, drop a 1024×1024 icon and a
2732×2732 splash into `resources/mobile-assets/` and run:

```bash
npm install --save-dev @capacitor/assets
npx capacitor-assets generate
```

## Store submission notes

Both stores accept web-backed apps, but Apple review guideline 4.2 rejects shells
that add nothing beyond the website. Before submitting to the App Store, plan to
add at least one native capability — push notifications, biometric login, camera
upload for payment proofs, or offline access to a key screen. The Capacitor
plugins for all of these install the same way as the ones already wired up.

## Not included

- **PWA**: this project is not a Progressive Web App. There is no web manifest or
  service worker, and Capacitor does not need one.
- **Push notifications**: `@capacitor/push-notifications` plus a Firebase project
  (Android) and APNs key (iOS) are required; nothing is configured yet.
