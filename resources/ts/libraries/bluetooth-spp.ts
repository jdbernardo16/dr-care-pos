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
