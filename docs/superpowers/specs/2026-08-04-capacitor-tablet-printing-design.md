# Capacitor Tablet Printing — Design Spec

Date: 2026-08-04
Status: Approved (pending spec review)

## 1. Context & Goal

The POS prints receipts to a **JKL-5802H 58mm ESC/POS thermal printer** over **Bluetooth Classic (SPP)**. Desktop printing works today via QZ Tray (`resources/ts/libraries/xprinter.ts`). QZ Tray has **no Android build**, and Chrome's Web Bluetooth only supports BLE — the JKL-5802H is Bluetooth Classic SPP, so a pure browser PWA on an Android tablet **cannot** reach the printer.

Goal: wrap the existing Laravel web app in a **Capacitor Android app** (WebView shell) that loads the real app and prints receipts to the paired JKL-5802H over native Bluetooth SPP.

## 2. Constraints (confirmed with user)

- One tablet, one counter, one printer
- Android only (no iPad)
- Online required (receipts are fetched from the server; same as current flow)
- Production: public HTTPS domain
- Dev: LAN-first testing (plain HTTP) before going live
- Build tooling: Android Studio on the Mac
- Desktop QZ Tray path must keep working

## 3. Architecture

```
Android tablet (APK)
 └─ Capacitor WebView ──server.url──> https://pos.<domain>   (or http://<LAN-IP>:8000 for dev)
      └─ xprinter.ts gateway
           ├─ Desktop/browser → QZ Tray (unchanged)
           └─ NEW: Capacitor/native → Bluetooth SPP plugin → JKL-5802H
```

- **Zero PHP/Blade changes.** Receipts, settings, gateway option all stay server-side.
- **ESC/POS bytes unchanged.** `receiptDomToEscpos()` already produces them; only transport changes (QZ socket → native Bluetooth socket).
- The app is a pure shell: all URLs derive from `window.location.origin`, so receipts/settings/printing automatically follow the server the APK points at.

## 4. Components

### 4.1 Capacitor scaffold
- Add `@capacitor/core`, `@capacitor/cli`, `@capacitor/android` to `package.json`
- `capacitor.config.json`:
  - `appId`: e.g. `com.drcare.pos`
  - `appName`: e.g. `DrCare POS`
  - `server.url`: build-time target (see §5)
  - `server.cleartext`: NOT used — cleartext handled via debug manifest overlay (§4.4)
- `npx cap add android` → `android/` platform folder

### 4.2 Bluetooth SPP plugin
- Must be a **Bluetooth Classic SPP (serial)** plugin that can write raw bytes — NOT BLE-only plugins (`@capawesome-team/bluetooth-le` is BLE-only, rejected).
- Candidates at implementation time (verify maintenance/recency first):
  1. Generic Bluetooth Classic serial plugin (raw byte write)
  2. `thermal-printer-cordova-plugin` (Cordova plugin, works under Capacitor)
  3. **Fallback:** custom ~50-line Capacitor plugin using Android `BluetoothSocket` over SPP (UUID `00001101-0000-1000-8000-00805F9B34FB`), exposing `list()`, `connect()`, `write(bytes)`, `disconnect()`.
- Reject Rongta-SDK-based plugins (brand-locked).

### 4.3 xprinter.ts changes
- Add native branch: if `window.Capacitor?.isNativePlatform()`, send bytes via plugin; else existing QZ path.
- Update printer-name regex to include JKL models:
  `/jkl[-_\s]?58|xp[-_\s]?58|58mm|xprinter|bluetooth|\b58\b/i`
- Keep `receiptUrl()`, `fetchReceiptDom()`, `receiptDomToEscpos()` unchanged.
- Flow: list paired devices → find JKL-5802H by name → connect → write bytes → disconnect.

### 4.4 Android permissions & cleartext
- Manifest permissions: `BLUETOOTH_CONNECT` (Android 12+), `BLUETOOTH`/`BLUETOOTH_ADMIN` + location for scan (<12). Plugin usually declares these; verify.
- **Debug manifest overlay** (`android/app/src/debug/AndroidManifest.xml`): `android:usesCleartextTraffic="true"` so the LAN test APK can load `http://<LAN-IP>:8000`.
- **Release build stays HTTPS-only** (no cleartext) for production.

### 4.5 PWA
- The app remains a web app; the APK is the install target. PWA/service-worker behavior inside the WebView is incidental, not a requirement.

## 5. Server URL strategy (LAN dev → live)

| Phase | `server.url` | Build | Cleartext |
|---|---|---|---|
| Dev/test on LAN | `http://<Mac LAN IP>:8000` | debug | allowed (debug manifest only) |
| Production | `https://pos.<domain>` | release, signed | not allowed |

Switching = edit `server.url` in `capacitor.config.json` → `npx cap sync android` → rebuild APK → reinstall. Not runtime-switchable (accepted trade-off). Runtime URL switcher is explicitly out of scope (§9).

## 6. LAN dev workflow

1. Find Mac IP: `ipconfig getifaddr en0`
2. Serve Laravel on all interfaces: `php artisan serve --host=0.0.0.0 --port=8000`
   (default binds localhost only — tablet cannot reach it otherwise)
3. Set `server.url` to `http://<IP>:8000`, sync, build debug APK
4. Install APK on tablet; tablet and Mac on same Wi-Fi
5. Pair JKL-5802H in Android Settings → Bluetooth
6. Print test receipt via POS

Note: `APP_URL` in `.env` is not critical for this flow because the app derives URLs from `window.location.origin` (the WebView origin), not from `APP_URL`.

## 7. Error handling

- Printer not paired / not found → snackbar: "Pair the JKL-5802H in Android Settings"
- Bluetooth off → prompt to enable
- Connection/write failure → one retry, then clear snackbar error
- No config UI (one tablet, one printer, auto-detect by name)

## 8. Build, signing & deliverables

1. Install Android Studio (bundles JDK + SDK; machine currently has neither)
2. Scaffold Capacitor + Android platform
3. Add Bluetooth SPP plugin + permissions
4. Extend `xprinter.ts` (native branch + JKL regex)
5. Build + install debug APK → LAN test on tablet
6. One-time keystore generation; signed release APK for production
7. Deliver: (a) LAN test APK + setup instructions, (b) one-line "go live" instruction (server.url swap + rebuild)

## 9. Out of scope

- Runtime server-URL switcher (add later only if switching becomes frequent)
- iOS support
- Offline mode
- Printer config UI / multi-printer management
- USB printing

## 10. Testing

- Desktop browser: QZ Tray path still prints (regression)
- Tablet debug APK on LAN: full POS flow → receipt prints on JKL-5802H
- Receipt format: 32-column / 58mm layout matches current thermal output
- Permission prompts (Bluetooth connect) appear correctly on Android 12+
