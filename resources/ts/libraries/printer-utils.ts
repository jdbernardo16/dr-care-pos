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
