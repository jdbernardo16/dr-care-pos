import qz from "qz-tray";
import ReceiptPrinterEncoder from "@point-of-sale/receipt-printer-encoder";
import { pickPrinterName, isNativePlatform, sanitizeEscpos } from "./printer-utils";
import { sppListDevices, sppConnect, sppWriteBytes, sppDisconnect } from "./bluetooth-spp";

/**
 * xprinter.ts — XPrinter Thermal 58mm print gateway.
 *
 * Registered on the `ns-custom-print` hook (NexoPOS's official custom printing
 * gateway). When the POS printing gateway is set to "xprinter", this module:
 *
 *   1. fetches the server-rendered receipt HTML (the thermal-58 template,
 *      which is already formatted at 48mm width with Courier),
 *   2. converts the receipt DOM into ESC/POS bytes for a 58mm printer
 *      (32 columns, font A),
 *   3. sends the raw bytes to the printer through QZ Tray (a small companion
 *      app that bridges the browser to Bluetooth Classic / USB / serial
 *      printers — the browser cannot reach Bluetooth Classic printers by
 *      itself).
 *
 * Setup required on the POS machine:
 *   - Install QZ Tray from https://qz.io and keep it running.
 *   - Pair the XPrinter in the OS (Windows/macOS Bluetooth settings).
 *   - On first print, Chrome will show QZ Tray's trust dialog — accept it.
 */

declare const nsHooks: any;
declare const nsSnackBar: any;
declare const __: (text: string, domain?: string) => string;

/** 58mm receipt: 32 columns at ESC/POS font A. */
const RECEIPT_WIDTH = 32;
/** Right column (amounts) keeps 10 chars; left column gets the rest. */
const AMOUNT_COL = 10;
/** Logo raster width in dots (58mm at 203dpi ≈ 384; ~70% per template). Must be multiple of 8. */
const LOGO_WIDTH = 280;

/** Map NexoPOS document type to the server-side receipt route. */
function receiptUrl(referenceId: string | number, document: string): string {
    const base = window.location.origin;
    const suffix = "?dash-visibility=disabled";
    switch (document) {
        case "refund":
            return `${base}/dashboard/orders/refund-receipt/${referenceId}${suffix}`;
        case "z-report":
            return `${base}/dashboard/cash-registers/z-report/${referenceId}${suffix}`;
        case "payment":
        case "sale":
        default:
            return `${base}/dashboard/orders/receipt/${referenceId}${suffix}`;
    }
}

/** Fetch the receipt page and parse it into a DOM document. */
async function fetchReceiptDom(url: string): Promise<Document> {
    const response = await fetch(url, { credentials: "include" });
    if (!response.ok) {
        throw new Error(
            __(`Unable to fetch the receipt (HTTP ${response.status}).`, "NsXprinter")
        );
    }
    const html = await response.text();
    return new DOMParser().parseFromString(html, "text/html");
}

/** Strip HTML to plain text, collapsing whitespace, then sanitize for ESC/POS. */
function textOf(el: Element | null): string {
    if (!el) return "";
    const raw = (el.textContent || "").replace(/\s+/g, " ").trim();
    return sanitizeEscpos(raw);
}

/**
 * Load an <img> from the receipt DOM and append it to the encoder as an
 * ESC/POS raster (monochrome) image. Falls back to the alt text (or nothing)
 * when the image can't be loaded or drawn.
 */
async function printLogo(encoder: any, img: HTMLImageElement): Promise<boolean> {
    const src = img.getAttribute("src");
    if (!src) return false;

    const image = new Image();
    // Same-origin media (/storage/...) — canvas-safe without CORS.
    image.crossOrigin = "anonymous";
    image.src = new URL(src, window.location.origin).toString();

    try {
        await new Promise<void>((resolve, reject) => {
            image.onload = () => resolve();
            image.onerror = () => reject(new Error("logo load failed"));
            // If the image was already cached/completed, resolve immediately.
            if (image.complete && image.naturalWidth > 0) resolve();
        });

        const naturalW = image.naturalWidth;
        const naturalH = image.naturalHeight;
        if (!naturalW || !naturalH) return false;

        // Scale to LOGO_WIDTH dots, keep aspect; height must be multiple of 8.
        const width = LOGO_WIDTH;
        const height = Math.max(8, Math.round((naturalH / naturalW) * width / 8) * 8);

        encoder.align("center");
        encoder.image(image, width, height, "threshold", 128);
        encoder.newline();
        return true;
    } catch (exception) {
        console.warn("[XPrinter] Logo skipped:", exception);
        const alt = img.getAttribute("alt");
        if (alt) {
            encoder.align("center");
            encoder.bold(true);
            encoder.text(alt.toUpperCase());
            encoder.bold(false);
            encoder.newline();
        }
        return false;
    }
}

/**
 * Convert the thermal-58 receipt page into ESC/POS bytes.
 * Walks the known structure of the `_*_thermal` blade templates:
 * .header / .receipt-type / .columns / table / .footer.
 */
async function receiptDomToEscpos(doc: Document): Promise<Uint8Array> {
    const encoder = new ReceiptPrinterEncoder({
        language: "esc-pos",
        width: RECEIPT_WIDTH,
        // XPrinter 58mm speaks GS v 0 raster natively (pos-5890 class).
        imageMode: "raster",
    });

    encoder.initialize();
    encoder.align("center");
    encoder.newline();

    // ── Header: store logo (raster), else store name; address, TIN ──
    const header = doc.querySelector(".header");
    if (header) {
        const logo = header.querySelector("img");
        if (logo) {
            await printLogo(encoder, logo);
        } else {
            const h2 = header.querySelector("h2");
            if (h2) {
                encoder.bold(true);
                encoder.text(textOf(h2).toUpperCase());
                encoder.bold(false);
                encoder.newline();
            }
        }
        const infoEls = header.querySelectorAll(".info");
        infoEls.forEach((info) => {
            const line = textOf(info);
            if (line) {
                encoder.text(line);
                encoder.newline();
            }
        });
    }

    // ── Receipt type: e.g. PAYMENT RECEIPT (bold, dashed frame) ──
    const receiptType = doc.querySelector(".receipt-type");
    if (receiptType) {
        encoder.newline();
        encoder.rule({ style: "single" });
        encoder.bold(true);
        encoder.text(textOf(receiptType));
        encoder.bold(false);
        encoder.newline();
        encoder.rule({ style: "single" });
        encoder.newline();
    }

    // ── Metadata columns (order info / customer info) ──
    const columns = doc.querySelector(".columns");
    if (columns) {
        const cols = columns.querySelectorAll(".col");
        cols.forEach((col) => {
            sanitizeEscpos(col.textContent || "")
                .split("\n")
                .map((l) => l.replace(/\s+/g, " ").trim())
                .filter(Boolean)
                .forEach((line) => {
                    encoder.align("left");
                    encoder.text(line);
                    encoder.newline();
                });
        });
        encoder.newline();
    }

    // ── Items + totals table ──
    const table = doc.querySelector("table");
    if (table) {
        // Header row (Item / Amount)
        const headCells = table.querySelectorAll("thead th");
        if (headCells.length >= 2) {
            encoder.bold(true);
            encoder.table(
                [
                    { width: RECEIPT_WIDTH - AMOUNT_COL, align: "left" },
                    { width: AMOUNT_COL, align: "right" },
                ],
                [[textOf(headCells[0]), textOf(headCells[1])]]
            );
            encoder.bold(false);
        }

        // Body rows
        table.querySelectorAll("tbody tr").forEach((row) => {
            const tds = row.querySelectorAll("td");
            if (tds.length === 0) return;

            const isTotal = row.classList.contains("total-row");
            const isBold = isTotal || row.classList.contains("bold");
            const isPayment = row.classList.contains("payment-row");

            // A single colspan cell (e.g. "Payments" section header) → full line
            if (tds.length === 1) {
                const label = textOf(tds[0]);
                if (!label) return;
                if (isBold || isPayment) encoder.bold(true);
                encoder.align("left");
                encoder.text(label);
                encoder.newline();
                if (isBold || isPayment) encoder.bold(false);
                if (isTotal) encoder.rule({ style: "single" });
                return;
            }

            // Two-column row: label + amount
            const leftCell = tds[0];
            const rightCell = tds[1];

            // Product rows have .product-name + .product-meta
            const nameEl = leftCell.querySelector(".product-name");
            const metaEl = leftCell.querySelector(".product-meta");
            const leftText = nameEl ? textOf(nameEl) : textOf(leftCell);
            const metaText = metaEl ? textOf(metaEl) : "";

            if (isBold) encoder.bold(true);
            encoder.table(
                [
                    { width: RECEIPT_WIDTH - AMOUNT_COL, align: "left" },
                    { width: AMOUNT_COL, align: "right" },
                ],
                [[leftText, textOf(rightCell)]]
            );
            if (isBold) encoder.bold(false);

            if (metaText) {
                encoder.align("left");
                encoder.text(metaText);
                encoder.newline();
            }

            if (isTotal) {
                encoder.rule({ style: "single" });
            }
        });
    }

    // ── Footer ──
    const footer = doc.querySelector(".footer");
    if (footer) {
        encoder.newline();
        encoder.align("center");
        (sanitizeEscpos(footer.textContent || ""))
            .split("\n")
            .map((l) => l.trim())
            .filter(Boolean)
            .forEach((line) => {
                encoder.text(line);
                encoder.newline();
            });
    }

    encoder.newline(2);
    encoder.cut("partial");

    return encoder.encode();
}

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

/** Main entry: print an order/report to the thermal printer. */
export async function printViaXprinter(referenceId: string | number, document: string): Promise<void> {
    const url = receiptUrl(referenceId, document);
    const doc = await fetchReceiptDom(url);
    const bytes = await receiptDomToEscpos(doc);

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

/**
 * Register the gateway on the `ns-custom-print` hook.
 * Must be called once, after the app is bootstrapped (pos-init constructor).
 */
export function registerXprinterGateway(): void {
    nsHooks.addFilter("ns-custom-print", "ns-xprinter", (result: any) => {
        const { params } = result;
        if (params.gateway !== "xprinter") {
            return result;
        }

        const { reference_id, document } = params;

        return {
            params: {
                ...params,
                printed: true,
            },
            promise: () =>
                printViaXprinter(reference_id, document)
                    .then(() => ({
                        status: "success",
                        message: __("Receipt sent to the thermal printer.", "NsXprinter"),
                    }))
                    .catch((exception: any) => {
                        nsSnackBar.error(
                            exception.message ||
                                __("An unexpected error occurred while printing.", "NsXprinter")
                        );
                        throw exception;
                    }),
        };
    }, 20);
}
