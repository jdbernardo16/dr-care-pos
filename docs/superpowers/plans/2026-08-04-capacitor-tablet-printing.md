# Capacitor Tablet Printing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wrap the Laravel POS web app in a Capacitor Android APK so an Android tablet can print 58mm receipts to a Bluetooth Classic SPP JKL-5802H printer, with a LAN-first test phase and a one-line switch to the production HTTPS domain.

**Architecture:** Two repositories. The **web repo (`drcare`)** holds everything the WebView loads from the server: the ESC/POS gateway logic and the JS side of the native bridge. The **app repo (`drcare-tablet`, new)** holds the Capacitor/Android shell: it loads the web app via `server.url` (LAN IP for testing, HTTPS domain for production) and hosts a small custom Android Bluetooth SPP plugin (list paired devices → connect by name → write raw bytes → disconnect). Desktop QZ Tray path stays untouched. Zero PHP/Blade changes.

**Bridge contract (the only coupling between repos):** native plugin name `BluetoothSpp`; JS calls `list() → { devices: string[] }`, `connect({ name })`, `write({ data })` (base64 ESC/POS bytes), `disconnect()`. Defined in JS by `resources/ts/libraries/bluetooth-spp.ts` (web repo) and implemented in Java by `BluetoothSppPlugin.java` (app repo).

**Tech Stack:** Capacitor 7 (core/cli/android), Android native Java (BluetoothSocket over SPP UUID `00001101-0000-1000-8000-00805F9B34FB`), jest + babel (existing TS test setup), Vite (existing web build), Laravel `artisan serve`.

**Spec:** `docs/superpowers/specs/2026-08-04-capacitor-tablet-printing-design.md`

---

## File Structure

### Web repo (`drcare`) — Tasks 1–4

| File | Responsibility |
|---|---|
| `resources/ts/libraries/printer-utils.ts` (create) | Pure, dependency-free helpers: printer-name regex, Capacitor detection, base64 encoding — the only testable module |
| `ts-tests/printer-utils.test.ts` (create) | jest tests for the above (follows existing `ts-tests/form-validation.test.ts` pattern) |
| `resources/ts/libraries/bluetooth-spp.ts` (create) | Typed client for the native `BluetoothSpp` plugin via `window.Capacitor.Plugins` (no @capacitor/core import — keeps web bundle lean) |
| `resources/ts/libraries/xprinter.ts` (modify) | Native branch in `printViaXprinter`, JKL regex via `pickPrinterName()` |

### App repo (`drcare-tablet`, new, default location `~/Sites/drcare-tablet`) — Tasks 5–10

| File | Responsibility |
|---|---|
| `package.json` (create) | Capacitor dependencies + scripts |
| `capacitor.config.json` (create) | appId/appName/webDir/server.url |
| `www/index.html` (create) | Stub web dir (required by `cap sync`; unused at runtime because `server.url` is set) |
| `android/` (generated) | `npx cap add android` output + our Java plugin + manifest edits |
| `android/app/src/main/java/com/drcare/pos/BluetoothSppPlugin.java` (create) | Native Bluetooth SPP plugin (implements the bridge contract) |
| `android/app/src/main/java/com/drcare/pos/MainActivity.java` (modify) | Register the plugin |
| `android/app/src/main/AndroidManifest.xml` (modify) | Bluetooth permissions |
| `android/app/src/debug/AndroidManifest.xml` (modify) | Cleartext HTTP for LAN debug builds only |
| `android/keystore.properties` + `android/app/build.gradle` (create/modify) | Release signing (keystore.properties gitignored) |
| `docs/tablet-printing-setup.md` (create) | User-facing LAN + go-live instructions |
| `README.md` (create) | Bridge contract + how this repo relates to drcare |

---

## Task 0: Install Android Studio (manual, user-driven)

**Files:** none

- [ ] **Step 1: Download and install Android Studio**

Download from https://developer.android.com/studio (macOS with Apple Silicon: `android-studio-*-mac_arm.dmg`). Drag to Applications. First launch → "Standard" setup → let it finish downloading the Android SDK. Do NOT skip the SDK install.

- [ ] **Step 2: Verify Java (bundled JBR)**

Run:

```bash
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
java -version
```

Expected: `openjdk version "21"` or newer (Android Studio's bundled JetBrains Runtime).

- [ ] **Step 3: Persist JAVA_HOME and verify SDK**

Run:

```bash
echo 'export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"' >> ~/.zshrc
ls ~/Library/Android/sdk
```

Expected: `sdk` dir listing (platforms, platform-tools, build-tools, etc.). If missing, open Android Studio → Settings → Languages & Frameworks → Android SDK and complete the SDK install, then re-check.

- [ ] **Step 4: Confirm environment (no commit — setup only)**

```bash
echo "JAVA_HOME=$JAVA_HOME" && java -version 2>&1 | head -1
```

---

## Task 1 (web repo): Install jest and write failing tests for printer-utils

**Files:**
- Modify: `package.json` (jest devDependency)
- Create: `ts-tests/printer-utils.test.ts`

- [ ] **Step 1: Install jest**

Run (in `/Users/jdbernardo/Sites/drcare`):

```bash
npm install --save-dev jest
```

Expected: jest added to `devDependencies` in `package.json` (the `"test": "jest"` script already exists).

- [ ] **Step 2: Write the failing test file**

Create `ts-tests/printer-utils.test.ts`:

```typescript
import { describe, expect, test } from "@jest/globals";

import {
    pickPrinterName,
    isNativePlatform,
    toBase64,
} from "../resources/ts/libraries/printer-utils";

describe("pickPrinterName", () => {
    test("matches JKL-5802H", () => {
        expect(pickPrinterName(["JKL-5802H"])).toBe("JKL-5802H");
    });

    test("matches XP-58 / XPrinter / 58mm / bluetooth names", () => {
        expect(pickPrinterName(["XP-58II"])).toBe("XP-58II");
        expect(pickPrinterName(["XPrinter 58"])).toBe("XPrinter 58");
        expect(pickPrinterName(["58mm Printer"])).toBe("58mm Printer");
        expect(pickPrinterName(["Bluetooth Printer"])).toBe("Bluetooth Printer");
        expect(pickPrinterName(["Receipt Printer 58"])).toBe("Receipt Printer 58");
    });

    test("returns undefined when nothing matches", () => {
        expect(pickPrinterName(["HP LaserJet", "Brother HL-L2350DW"])).toBeUndefined();
    });
});

describe("isNativePlatform", () => {
    test("is false in a plain browser / node env", () => {
        expect(isNativePlatform()).toBe(false);
    });
});

describe("toBase64", () => {
    test("encodes raw bytes", () => {
        const bytes = new Uint8Array([0x1b, 0x40, 0x68, 0x69]); // ESC @ h i
        expect(toBase64(bytes)).toBe("G0BoaQ==");
    });
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run:

```bash
npx jest ts-tests/printer-utils.test.ts
```

Expected: FAIL — `Cannot find module '../resources/ts/libraries/printer-utils'`.

---

## Task 2 (web repo): Implement printer-utils.ts (pure helpers)

**Files:**
- Create: `resources/ts/libraries/printer-utils.ts`

- [ ] **Step 1: Write the implementation**

Create `resources/ts/libraries/printer-utils.ts`:

```typescript
/**
 * printer-utils.ts — dependency-free helpers shared by the QZ Tray and
 * Capacitor-native print paths. Kept import-free so jest can test it
 * without pulling in qz-tray / browser-only modules.
 */

/**
 * Matches thermal 58mm printer names as reported by the OS / QZ Tray /
 * Android bonded-device list. Covers JKL and XP models plus generic names.
 */
export const PRINTER_NAME_PATTERN = /jkl[-_\s]?58|xp[-_\s]?58|58mm|xprinter|bluetooth|\b58\b/i;

/**
 * Returns the first printer name that looks like a 58mm thermal printer,
 * or undefined if none match.
 */
export function pickPrinterName(names: string[]): string | undefined {
    return names.find((n) => PRINTER_NAME_PATTERN.test(n));
}

/**
 * True when the app runs inside the Capacitor WebView (native bridge injected
 * as window.Capacitor). Safe to call from a plain browser — returns false.
 */
export function isNativePlatform(): boolean {
    if (typeof window === "undefined") {
        return false;
    }
    const capacitor = (window as any).Capacitor;
    return !!(capacitor && typeof capacitor.isNativePlatform === "function" && capacitor.isNativePlatform());
}

/**
 * Encode raw ESC/POS bytes as base64 for transport across the native bridge
 * (Capacitor plugin calls serialize options as JSON — Uint8Array is not supported).
 */
export function toBase64(bytes: Uint8Array): string {
    let binary = "";
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
    }
    return btoa(binary);
}
```

- [ ] **Step 2: Run the test to verify it passes**

Run:

```bash
npx jest ts-tests/printer-utils.test.ts
```

Expected: PASS — all 7 tests green.

- [ ] **Step 3: Run the full jest suite (regression on existing test)**

Run:

```bash
npx jest
```

Expected: `ts-tests/form-validation.test.ts` passes (or note its pre-existing status — it is not touched by this plan; if it fails, that failure pre-dates this work and must be reported, not "fixed" here).

- [ ] **Step 4: Commit (web repo)**

```bash
git add package.json package-lock.json ts-tests/printer-utils.test.ts resources/ts/libraries/printer-utils.ts
git commit -m "feat(print): add printer-utils with JKL-5802H detection, native detection, base64"
```

---

## Task 3 (web repo): Create the native Bluetooth bridge client

**Files:**
- Create: `resources/ts/libraries/bluetooth-spp.ts`

- [ ] **Step 1: Write the client**

Create `resources/ts/libraries/bluetooth-spp.ts`:

```typescript
/**
 * bluetooth-spp.ts — typed client for the native BluetoothSpp plugin.
 *
 * Bridge contract (the Java side lives in the drcare-tablet repo):
 *   plugin name: BluetoothSpp
 *   list()           → resolve { devices: string[] }  (bonded device names)
 *   connect({name})  → resolve | reject("Printer ... is not paired ...")
 *   write({data})    → base64 ESC/POS bytes → resolve | reject
 *   disconnect()     → resolve
 *
 * The plugin is registered in MainActivity and exposed automatically by
 * Capacitor at window.Capacitor.Plugins.BluetoothSpp. We talk to it
 * directly (no @capacitor/core import) so the web bundle stays lean.
 */

import { toBase64 } from "./printer-utils";

export interface BluetoothSppBridge {
    list(): Promise<{ devices: string[] }>;
    connect(options: { name: string }): Promise<void>;
    write(options: { data: string }): Promise<void>;
    disconnect(): Promise<void>;
}

function bridge(): BluetoothSppBridge | undefined {
    if (typeof window === "undefined") {
        return undefined;
    }
    const capacitor = (window as any).Capacitor;
    return capacitor?.Plugins?.BluetoothSpp;
}

export async function sppListDevices(): Promise<string[]> {
    const plugin = bridge();
    if (!plugin) {
        throw new Error(__("Native Bluetooth bridge unavailable.", "NsXprinter"));
    }
    const result = await plugin.list();
    return result.devices || [];
}

export async function sppConnect(name: string): Promise<void> {
    const plugin = bridge();
    if (!plugin) {
        throw new Error(__("Native Bluetooth bridge unavailable.", "NsXprinter"));
    }
    await plugin.connect({ name });
}

export async function sppWriteBytes(bytes: Uint8Array): Promise<void> {
    const plugin = bridge();
    if (!plugin) {
        throw new Error(__("Native Bluetooth bridge unavailable.", "NsXprinter"));
    }
    await plugin.write({ data: toBase64(bytes) });
}

export async function sppDisconnect(): Promise<void> {
    const plugin = bridge();
    if (!plugin) {
        return;
    }
    await plugin.disconnect();
}

declare const __: (text: string, domain?: string) => string;
```

- [ ] **Step 2: Verify TypeScript compiles**

Run:

```bash
npx tsc --noEmit -p tsconfig.json 2>&1 | grep -v node_modules | head -20
```

Expected: no errors mentioning `bluetooth-spp.ts` or `printer-utils.ts`.

- [ ] **Step 3: Commit (web repo)**

```bash
git add resources/ts/libraries/bluetooth-spp.ts
git commit -m "feat(print): add native Bluetooth SPP bridge client"
```

---

## Task 4 (web repo): Add the native branch to xprinter.ts

**Files:**
- Modify: `resources/ts/libraries/xprinter.ts`

- [ ] **Step 1: Update imports (top of file, after line 2)**

Replace the current import block start with:

```typescript
import qz from "qz-tray";
import ReceiptPrinterEncoder from "@point-of-sale/receipt-printer-encoder";
import { pickPrinterName, isNativePlatform } from "./printer-utils";
import { sppListDevices, sppConnect, sppWriteBytes, sppDisconnect } from "./bluetooth-spp";
```

- [ ] **Step 2: Replace findPrinter() to use pickPrinterName**

Replace the existing `findPrinter` function (lines ~226-241) with:

```typescript
/** Find the XPrinter/JKL in QZ Tray's printer list (fall back to first raw printer). */
async function findPrinter(): Promise<string> {
    const printers = await qz.printers.find();
    if (!printers || printers.length === 0) {
        throw new Error(
            __(
                "No printer found in QZ Tray. Make sure QZ Tray is running and the printer is paired.",
                "NsXprinter"
            )
        );
    }
    const names = printers.map((p: any) => String(p.name || p));
    const match = pickPrinterName(names);
    return match || names[0];
}
```

- [ ] **Step 3: Add the native branch to printViaXprinter**

Replace the body of `printViaXprinter` (currently lines ~244-255) with:

```typescript
/** Main entry: print an order/report to the thermal printer. */
export async function printViaXprinter(referenceId: string | number, document: string): Promise<void> {
    const url = receiptUrl(referenceId, document);
    const doc = await fetchReceiptDom(url);
    const bytes = receiptDomToEscpos(doc);

    if (isNativePlatform()) {
        const devices = await sppListDevices();
        const target = pickPrinterName(devices) || devices[0];

        if (!target) {
            throw new Error(
                __(
                    "No printer found. Pair the JKL-5802H in Android Settings > Bluetooth.",
                    "NsXprinter"
                )
            );
        }

        await sppConnect(target);
        try {
            await sppWriteBytes(bytes);
        } finally {
            await sppDisconnect();
        }
        return;
    }

    if (!qz.websocket.isActive()) {
        await qz.websocket.connect();
    }

    const printer = await findPrinter();
    await qz.raw.send(printer, bytes);
}
```

- [ ] **Step 4: Verify build + tests**

Run:

```bash
npm run build
npx jest ts-tests/printer-utils.test.ts
```

Expected: Vite build succeeds; jest tests still pass.

- [ ] **Step 5: Commit (web repo)**

```bash
git add resources/ts/libraries/xprinter.ts
git commit -m "feat(print): native Capacitor branch in xprinter gateway"
```

---

## Task 5 (app repo): Create the app repo and scaffold Capacitor

**Files (all in the NEW repo):**
- Create: `~/Sites/drcare-tablet/` (repo root — rename freely)
- Create: `package.json`
- Create: `capacitor.config.json`
- Create: `www/index.html`
- Generate: `android/`

- [ ] **Step 1: Create and init the new repo**

Run:

```bash
mkdir -p ~/Sites/drcare-tablet && cd ~/Sites/drcare-tablet
git init
npm init -y
```

Expected: new git repo with a default `package.json`.

- [ ] **Step 2: Install Capacitor packages**

Run:

```bash
npm install @capacitor/core @capacitor/cli @capacitor/android
```

Expected: three packages in `dependencies`.

- [ ] **Step 3: Create the web-dir stub**

Create `www/index.html`:

```html
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>DrCare POS</title>
</head>
<body>
    Loading…
</body>
</html>
```

(This stub is required by `cap sync`; it is never shown because `server.url` points at the real app.)

- [ ] **Step 4: Create capacitor.config.json**

Create `capacitor.config.json`:

```json
{
    "appId": "com.drcare.pos",
    "appName": "DrCare POS",
    "webDir": "www",
    "server": {
        "url": "http://192.168.1.100:8000"
    }
}
```

> Substitute `<LAN_IP>`: replace `192.168.1.100` with the output of `ipconfig getifaddr en0` on the Mac. This value is the LAN test target; production switch is Task 10.

- [ ] **Step 5: Add the Android platform**

Run:

```bash
npx cap add android
```

Expected: `android/` directory created; output ends with something like `[info] add android completed`.

- [ ] **Step 6: Verify sync works**

Run:

```bash
npx cap sync android
```

Expected: `[info] Sync finished` (or similar success output).

- [ ] **Step 7: Commit (app repo)**

```bash
git add -A
git commit -m "chore: scaffold Capacitor Android shell for DrCare POS"
```

---

## Task 6 (app repo): Native Bluetooth SPP plugin (Java) + permissions

**Files (all in the NEW repo):**
- Create: `android/app/src/main/java/com/drcare/pos/BluetoothSppPlugin.java`
- Modify: `android/app/src/main/java/com/drcare/pos/MainActivity.java`
- Modify: `android/app/src/main/AndroidManifest.xml`
- Modify: `android/app/src/debug/AndroidManifest.xml`

> Implements the bridge contract defined in the web repo's `resources/ts/libraries/bluetooth-spp.ts`: plugin `BluetoothSpp`, methods `list`/`connect`/`write`/`disconnect`, base64 payloads. If the JS side ever changes, it must change here too — update `docs/tablet-printing-setup.md` / README when that happens.

- [ ] **Step 1: Write the Java plugin**

Create `android/app/src/main/java/com/drcare/pos/BluetoothSppPlugin.java`:

```java
package com.drcare.pos;

import android.Manifest;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothSocket;
import android.content.pm.PackageManager;
import android.os.Build;
import android.util.Base64;

import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

import java.io.IOException;
import java.io.OutputStream;
import java.util.UUID;

/**
 * BluetoothSppPlugin — prints ESC/POS bytes to a Bluetooth Classic SPP
 * thermal printer (JKL-5802H). Lists bonded devices, connects by name,
 * writes raw bytes, disconnects. Implements the bridge contract defined
 * in the drcare web repo (resources/ts/libraries/bluetooth-spp.ts).
 */
@CapacitorPlugin(
        name = "BluetoothSpp",
        permissions = {
                @Permission(alias = "bluetooth", strings = { Manifest.permission.BLUETOOTH_CONNECT })
        }
)
public class BluetoothSppPlugin extends Plugin {

    private static final UUID SPP_UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB");

    private BluetoothSocket socket;
    private OutputStream outputStream;
    private PluginCall pendingCall;

    private boolean hasBtPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            return getContext().checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT)
                    == PackageManager.PERMISSION_GRANTED;
        }
        return true;
    }

    private boolean guardPermission(PluginCall call) {
        if (!hasBtPermission()) {
            pendingCall = call;
            requestPermissionForAlias("bluetooth", call, "permissionCallback");
            return false;
        }
        return true;
    }

    @PermissionCallback
    private void permissionCallback(PluginCall call) {
        PluginCall original = pendingCall;
        pendingCall = null;
        if (original == null) {
            return;
        }
        if (call.getPermissionResult("bluetooth")) {
            handlePending(original);
        } else {
            original.reject("Bluetooth permission denied. Enable it in Android Settings.");
        }
    }

    private void handlePending(PluginCall call) {
        String method = call.getMethodName();
        if ("list".equals(method)) {
            list(call);
        } else if ("connect".equals(method)) {
            connect(call);
        }
    }

    @PluginMethod
    public void list(PluginCall call) {
        if (!guardPermission(call)) {
            return;
        }
        try {
            BluetoothAdapter adapter = BluetoothAdapter.getDefaultAdapter();
            if (adapter == null) {
                call.reject("Bluetooth is not supported on this device.");
                return;
            }
            JSArray devices = new JSArray();
            for (BluetoothDevice device : adapter.getBondedDevices()) {
                String name = device.getName();
                devices.put(name != null ? name : "Unknown device");
            }
            JSObject ret = new JSObject();
            ret.put("devices", devices);
            call.resolve(ret);
        } catch (SecurityException e) {
            call.reject("Bluetooth permission denied: " + e.getMessage());
        }
    }

    @PluginMethod
    public void connect(PluginCall call) {
        if (!guardPermission(call)) {
            return;
        }
        String name = call.getString("name");
        if (name == null || name.isEmpty()) {
            call.reject("name is required.");
            return;
        }
        try {
            BluetoothAdapter adapter = BluetoothAdapter.getDefaultAdapter();
            if (adapter == null) {
                call.reject("Bluetooth is not supported on this device.");
                return;
            }
            BluetoothDevice target = null;
            for (BluetoothDevice device : adapter.getBondedDevices()) {
                if (name.equals(device.getName())) {
                    target = device;
                    break;
                }
            }
            if (target == null) {
                call.reject("Printer \"" + name + "\" is not paired. Pair it in Android Settings > Bluetooth.");
                return;
            }
            disconnectInternal();
            socket = target.createRfcommSocketToServiceRecord(SPP_UUID);
            socket.connect();
            outputStream = socket.getOutputStream();
            call.resolve();
        } catch (SecurityException e) {
            call.reject("Bluetooth permission denied: " + e.getMessage());
        } catch (IOException e) {
            call.reject("Connection failed: " + e.getMessage());
        }
    }

    @PluginMethod
    public void write(PluginCall call) {
        String data = call.getString("data");
        if (data == null || data.isEmpty()) {
            call.reject("data (base64) is required.");
            return;
        }
        if (outputStream == null) {
            call.reject("Not connected. Call connect() first.");
            return;
        }
        try {
            byte[] bytes = Base64.decode(data, Base64.DEFAULT);
            outputStream.write(bytes);
            outputStream.flush();
            call.resolve();
        } catch (IOException e) {
            call.reject("Write failed: " + e.getMessage());
        }
    }

    @PluginMethod
    public void disconnect(PluginCall call) {
        disconnectInternal();
        call.resolve();
    }

    private void disconnectInternal() {
        try {
            if (outputStream != null) {
                outputStream.close();
            }
        } catch (IOException ignored) {
        }
        try {
            if (socket != null) {
                socket.close();
            }
        } catch (IOException ignored) {
        }
        outputStream = null;
        socket = null;
    }
}
```

- [ ] **Step 2: Register the plugin in MainActivity**

Verify the generated file exists at `android/app/src/main/java/com/drcare/pos/MainActivity.java` (package may differ if the appId in Task 5 Step 4 was changed — adjust path accordingly). Replace its entire content with:

```java
package com.drcare.pos;

import android.os.Bundle;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(BluetoothSppPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
```

- [ ] **Step 3: Add Bluetooth permissions to the main manifest**

Read `android/app/src/main/AndroidManifest.xml` and add these lines immediately **before** the `<application>` tag:

```xml
<uses-permission android:name="android.permission.BLUETOOTH" android:maxSdkVersion="30" />
<uses-permission android:name="android.permission.BLUETOOTH_ADMIN" android:maxSdkVersion="30" />
<uses-permission android:name="android.permission.BLUETOOTH_CONNECT" />
```

- [ ] **Step 4: Allow cleartext HTTP in debug builds only**

Read `android/app/src/debug/AndroidManifest.xml` (generated; contains the INTERNET permission). Replace its content with:

```xml
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:tools="http://schemas.android.com/tools">

    <uses-permission android:name="android.permission.INTERNET" />

    <application
        android:usesCleartextTraffic="true"
        tools:replace="android:usesCleartextTraffic" />
</manifest>
```

(This lets the debug APK load `http://<LAN_IP>:8000`. Release builds are unaffected and stay HTTPS-only.)

- [ ] **Step 5: Sync and compile-check**

Run (in `~/Sites/drcare-tablet`):

```bash
npx cap sync android
cd android && ./gradlew assembleDebug
```

Expected: `BUILD SUCCESSFUL` (first run downloads Gradle + dependencies — allow several minutes). If `JAVA_HOME` errors, re-run Task 0 Step 2 first.

- [ ] **Step 6: Commit (app repo)**

```bash
git add -A
git commit -m "feat(android): Bluetooth SPP plugin with debug cleartext manifest"
```

---

## Task 7 (app repo): Build debug APK and verify artifact

**Files:** none (build output)

- [ ] **Step 1: Build the debug APK**

Run (in `~/Sites/drcare-tablet/android`):

```bash
./gradlew assembleDebug
```

Expected: `BUILD SUCCESSFUL`; APK at `android/app/build/outputs/apk/debug/app-debug.apk`.

- [ ] **Step 2: Confirm the APK exists**

Run:

```bash
ls -lh android/app/build/outputs/apk/debug/app-debug.apk
```

Expected: file present, size in the tens of MB.

---

## Task 8: LAN test on the tablet (user-assisted, both repos)

**Files:** none

- [ ] **Step 1: Find the Mac's LAN IP**

Run:

```bash
ipconfig getifaddr en0
```

Expected: e.g. `192.168.1.50`. If blank, try `ipconfig getifaddr en1` or check Wi-Fi is on.

- [ ] **Step 2: Ensure capacitor.config.json points at the right IP**

Verify `capacitor.config.json` `server.url` (app repo) matches the Step 1 IP. If it differs, update it, then run `npx cap sync android` and rebuild (Task 7 Step 1).

- [ ] **Step 3: Deploy the web changes (web repo)**

Run (in `/Users/jdbernardo/Sites/drcare`):

```bash
npm run build
```

Expected: Vite rebuilds `public/build` — the LAN server now serves the xprinter native-branch bundle. (JS-only changes never require an APK rebuild.)

- [ ] **Step 4: Serve Laravel on all interfaces (user keeps this terminal open)**

Run (user's terminal, web repo):

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Expected: `Starting Laravel development server: http://0.0.0.0:8000`. macOS may show a firewall prompt — click **Allow** (or grant "local network" permission in System Settings → Privacy & Security → Local Network).

- [ ] **Step 5: Verify the app is reachable from the tablet**

On the tablet's browser, open `http://<LAN_IP>:8000`. Expected: the POS login page renders.

- [ ] **Step 6: Install the APK on the tablet**

Either:
```bash
adb install -r ~/Sites/drcare-tablet/android/app/build/outputs/apk/debug/app-debug.apk
```
(requires tablet connected via USB with USB debugging enabled), or copy `app-debug.apk` to the tablet (Drive/email) and open it — allow "Install unknown apps" for the file manager.

- [ ] **Step 7: Pair the JKL-5802H on the tablet**

Tablet → Settings → Bluetooth → pair the **JKL-5802H** (may show as `JKL-5802H` or similar). Power the printer on first; it must appear as an available device.

- [ ] **Step 8: Print a receipt**

Open the app on the tablet → log in → open POS → complete a sale (gateway must be `xprinter` in Settings → POS → Printing). Expected: **snackbar "Receipt sent to the thermal printer"** and the JKL-5802H prints a 58mm receipt, then cuts the paper.

- [ ] **Step 9: Verify desktop regression**

On the Mac browser (localhost:8000), with QZ Tray running and gateway `xprinter`, print a receipt. Expected: unchanged behavior (QZ path still works).

---

## Task 9 (app repo): Release signing

**Files (in the NEW repo):**
- Create: `android/keystore.properties`
- Modify: `android/app/build.gradle`
- Modify: `.gitignore`

- [ ] **Step 1: Generate a keystore (one-time)**

Run (you will be asked interactively for passwords):

```bash
keytool -genkey -v -keystore ~/.android/drcare-pos.keystore -alias drcare-pos -keyalg RSA -keysize 2048 -validity 10000
```

Expected: keystore file at `~/.android/drcare-pos.keystore`; note the keystore password, key password and alias (`drcare-pos`) — they are needed for every future release build.

- [ ] **Step 2: Create keystore.properties**

Create `android/keystore.properties` (never commit this file):

```properties
storeFile=/Users/<your-username>/.android/drcare-pos.keystore
storePassword=<your-keystore-password>
keyAlias=drcare-pos
keyPassword=<your-key-password>
```

- [ ] **Step 3: Wire signing into build.gradle**

Read `android/app/build.gradle`, and inside the `android { ... }` block (after `buildTypes { ... }`), add:

```gradle
    signingConfigs {
        release {
            def props = new Properties()
            def propsFile = rootProject.file('keystore.properties')
            if (propsFile.exists()) {
                props.load(new FileInputStream(propsFile))
                storeFile file(props.getProperty('storeFile'))
                storePassword props.getProperty('storePassword')
                keyAlias props.getProperty('keyAlias')
                keyPassword props.getProperty('keyPassword')
            }
        }
    }
```

Then inside `buildTypes { release { ... } }`, add the line:

```gradle
            signingConfig signingConfigs.release
```

(If `keystore.properties` is missing, release builds fall back to unsigned — debug builds are unaffected.)

- [ ] **Step 4: Build the signed release APK**

Run (in `~/Sites/drcare-tablet/android`):

```bash
./gradlew assembleRelease
```

Expected: `BUILD SUCCESSFUL`; APK at `android/app/build/outputs/apk/release/app-release.apk` (signed).

- [ ] **Step 5: Commit (config only — never the keystore)**

```bash
echo "android/keystore.properties" >> .gitignore
git add android/app/build.gradle .gitignore
git commit -m "chore(android): release signing config (keystore gitignored)"
```

---

## Task 10 (app repo): Go-live switch + user instructions + README

**Files (in the NEW repo):**
- Create: `docs/tablet-printing-setup.md`
- Create: `README.md`

- [ ] **Step 1: Write the user instructions**

Create `docs/tablet-printing-setup.md`:

```markdown
# Tablet Printing Setup (DrCare POS)

## LAN testing (debug APK)

1. Find the Mac IP: `ipconfig getifaddr en0`
2. Set `server.url` in `capacitor.config.json` to `http://<LAN_IP>:8000`
3. `npx cap sync android`
4. Serve Laravel: `php artisan serve --host=0.0.0.0 --port=8000`
   (macOS: allow the firewall/local-network prompt)
5. Build: `cd android && ./gradlew assembleDebug`
6. Install `android/app/build/outputs/apk/debug/app-debug.apk` on the tablet
7. Tablet Settings → Bluetooth → pair the JKL-5802H
8. Open the app → POS → complete a sale → receipt prints

## Going live (production HTTPS)

1. Set `server.url` in `capacitor.config.json` to `https://pos.<your-domain>`
2. `npx cap sync android`
3. Build the signed release APK: `cd android && ./gradlew assembleRelease`
4. Install `android/app/build/outputs/apk/release/app-release.apk` on the tablet
5. Done — the release APK only allows HTTPS (no cleartext)

## Switching back/forth

Only `server.url` in `capacitor.config.json` changes, then sync + rebuild + reinstall.
Receipts/settings/printing follow the URL automatically (they derive from `window.location.origin`).

## Bridge contract (do not change without updating the web repo)

Native plugin `BluetoothSpp`:
- `list()` → `{ devices: string[] }` (bonded device names)
- `connect({ name })` → resolve / reject with message
- `write({ data })` → base64 ESC/POS bytes
- `disconnect()` → resolve

JS client: `resources/ts/libraries/bluetooth-spp.ts` in the drcare repo.
Java implementation: `android/app/src/main/java/com/drcare/pos/BluetoothSppPlugin.java` here.

## Troubleshooting

- "No printer found. Pair the JKL-5802H..." → printer not paired in Android Bluetooth settings
- "Printer ... is not paired" → same fix; power the printer on first
- "Bluetooth permission denied" → grant the app the Bluetooth permission in Android Settings → Apps → DrCare POS
- Web page unreachable from tablet → tablet and Mac on the same Wi-Fi; Laravel served with `--host=0.0.0.0`; firewall allowed
- Desktop printing unchanged → QZ Tray path is untouched; gateway setting still `xprinter`
```

- [ ] **Step 2: Write the repo README**

Create `README.md`:

```markdown
# DrCare Tablet (Android shell)

Capacitor Android app that wraps the DrCare POS web app for tablet use.

- Web app (all receipts/settings/print logic): the **drcare** repo
- This repo: Capacitor shell + native Bluetooth SPP printing plugin

The APK loads the web app from `server.url` in `capacitor.config.json`:

- Debug builds may use `http://<LAN_IP>:8000` (cleartext allowed in debug only)
- Release builds must use `https://pos.<domain>` (HTTPS only)

See `docs/tablet-printing-setup.md` for LAN testing, going live, and the
bridge contract shared with the drcare web repo.
```

- [ ] **Step 3: Commit (app repo)**

```bash
git add -A
git commit -m "docs: tablet setup instructions and repo README"
```

---

## Self-Review Notes (against spec)

- Spec §4.0 repo split → applied throughout (web tasks 1–4, app tasks 5–10)
- Spec §4.1 scaffold → Task 5
- Spec §4.2 custom SPP plugin (fallback elevated to primary) → Task 6
- Spec §4.3 xprinter native branch + JKL regex → Tasks 2, 4
- Spec §4.4 permissions + debug-only cleartext → Task 6
- Spec §5 LAN→live URL strategy → Tasks 5, 8, 10
- Spec §6 LAN dev workflow → Task 8
- Spec §7 error handling → Java rejects + JS snackbar messages
- Spec §8 build/signing → Tasks 0, 7, 9
- Spec §9 out of scope → respected (no runtime URL switcher, no iOS, no config UI)
- Spec §10 testing → Task 8 + desktop regression
