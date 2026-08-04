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
