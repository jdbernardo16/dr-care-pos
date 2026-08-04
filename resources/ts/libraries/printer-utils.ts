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

/**
 * Map of Unicode characters to ESC/POS-safe ASCII equivalents.
 * Thermal printer codepages (CP437 etc.) only cover ASCII + a few Latin-1
 * chars — everything else prints as "?" or raw control bytes.
 */
const ESCPOS_SAFE_MAP: Record<string, string> = {
    "₱": "P",       // peso sign
    "×": "x",       // multiplication sign
    "÷": "/",       // division sign
    "–": "-",       // en dash
    "—": "-",       // em dash
    "→": ">",       // right arrow
    "←": "<",       // left arrow
    "↑": "^",       // up arrow
    "↓": "v",       // down arrow
    "•": "*",       // bullet
    "·": "*",       // middle dot
    "✓": "v",       // check mark
    "★": "*",       // star
    "€": "E",       // euro (may be in some codepages, safe fallback)
    "£": "L",       // pound
    "¥": "Y",       // yen
    "©": "(c)",     // copyright
    "®": "(r)",     // registered
    "™": "(tm)",    // trademark
    "°": "deg",     // degree
    "±": "+/-",     // plus-minus
    "≤": "<=",      // less-than-or-equal
    "≥": ">=",      // greater-than-or-equal
    "≠": "!=",      // not equal
    "∞": "inf",     // infinity
    "…": "...",     // ellipsis
    "'": "'",       // curly apostrophe
    "‘": "'",
    "’": "'",
    "“": '"',       // curly quotes
    "”": '"',
    " ": " ",       // non-breaking space
};

/**
 * Replace Unicode characters not representable in ESC/POS codepages with
 * printer-safe ASCII equivalents.
 */
export function sanitizeEscpos(text: string): string {
    return text.replace(/[₱×÷–—→←↑↓•·✓★€£¥©®™°±≤≥≠∞…'‘’“”\u00a0]/g, (ch) => ESCPOS_SAFE_MAP[ch] || ch);
}
