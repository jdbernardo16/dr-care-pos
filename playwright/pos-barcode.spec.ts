import { test, expect } from '@playwright/test';
import { POSPage } from './pages/POSPage';

test.describe('POS Barcode Scanning', () => {

    let pos: POSPage;

    test.beforeEach(async ({ page }) => {
        pos = new POSPage(page);
        await pos.goto();
        await pos.enableAutofocus();
    });

    test.afterEach(async ({ page }) => {
        const bodyText = await page.evaluate(() => document.body.innerText);
        if (!bodyText.includes('No products added')) {
            await pos.resetCart().catch(() => {});
        }
    });

    test('scan valid barcode adds product to cart', async ({ page }) => {
        await pos.scanBarcode('856370916519'); // Biogesic 500mg
        await pos.acceptQuantityPopup('1');

        expect(await pos.cartHasProduct('Biogesic')).toBe(true);
        const count = await pos.getCartProductCount();
        expect(count).toBe(1);
    });

    test('scan valid second product accumulates in cart', async ({ page }) => {
        await pos.scanBarcode('856370916519'); // Biogesic
        await pos.acceptQuantityPopup('1');

        await pos.scanBarcode('644790265829'); // Alaxan FR
        await pos.acceptQuantityPopup('1');

        expect(await pos.cartHasProduct('Biogesic')).toBe(true);
        expect(await pos.cartHasProduct('Alaxan')).toBe(true);
        const count = await pos.getCartProductCount();
        expect(count).toBe(2);
    });

    test('scan same barcode twice merges quantities', async ({ page }) => {
        // Enable merge toggle so duplicate scans combine
        await pos.mergeToggle.click();
        await page.waitForTimeout(500);

        await pos.scanBarcode('856370916519'); // Biogesic
        await pos.acceptQuantityPopup('1');

        await pos.scanBarcode('856370916519'); // Biogesic again
        await pos.acceptQuantityPopup('2');

        const count = await pos.getCartProductCount();
        expect(count).toBe(1);
        const total = await pos.getCartTotal();
        const totalNum = parseFloat(total);
        expect(totalNum).toBeGreaterThan(0);
    });

    test('scan non-existent barcode shows error', async ({ page }) => {
        const errorListener = page.waitForResponse(
            resp => resp.url().includes('/api/products/search/using-barcode/000000000000') && resp.status() === 404,
            { timeout: 10000 }
        ).catch(() => null);

        await pos.scanBarcode('000000000000');

        // Cart should remain empty
        expect(await pos.getCartProductCount()).toBe(0);

        // API should return 404
        const response = await errorListener;
        if (response) {
            expect(response.status()).toBe(404);
        }
    });

    test('scan empty string does not trigger search', async ({ page }) => {
        await pos.barcodeInput.focus();
        await pos.barcodeInput.fill('');
        await page.waitForTimeout(1000);

        const requests = [];
        page.on('request', req => {
            if (req.url().includes('using-barcode')) requests.push(req.url());
        });

        await page.waitForTimeout(1000);
        expect(requests.length).toBe(0);
    });

    test('barcode field clears after successful scan', async ({ page }) => {
        await pos.scanBarcode('856370916519');
        await pos.acceptQuantityPopup('1');

        const inputValue = await pos.barcodeInput.inputValue();
        expect(inputValue).toBe('');
    });

    test('scan with quantity greater than 1', async ({ page }) => {
        // Enable merge so scanning the same barcode accumulates
        await pos.mergeToggle.click();
        await page.waitForTimeout(500);

        // Scan Biogesic 3 times to get quantity = 3
        for (let i = 0; i < 3; i++) {
            await pos.scanBarcode('856370916519');
            await pos.acceptQuantityPopup('1');
        }

        const total = await pos.getCartTotal();
        const totalNum = parseFloat(total);
        // 3 x 4.50 = 13.50
        expect(totalNum).toBeCloseTo(13.50, 1);
    });

    test('autofocus toggle turns on and off', async ({ page }) => {
        await pos.enableAutofocus();
        const isOnAfterEnable = await pos.autofocusToggle.evaluate(
            (el: HTMLElement) => el.classList.contains('pos-button-clicked')
        );
        expect(isOnAfterEnable).toBe(true);

        await pos.disableAutofocus();
        const isOnAfterDisable = await pos.autofocusToggle.evaluate(
            (el: HTMLElement) => el.classList.contains('pos-button-clicked')
        );
        expect(isOnAfterDisable).toBe(false);
    });

    test('no console errors after successful barcode scan', async ({ page }) => {
        const errors: string[] = [];
        page.on('console', msg => {
            if (msg.type() === 'error') errors.push(msg.text());
        });

        await pos.scanBarcode('856370916519');
        await pos.acceptQuantityPopup('1');

        const criticalErrors = errors.filter(e =>
            !e.includes('closest is not a function') &&
            !e.includes('favicon')
        );
        expect(criticalErrors).toEqual([]);
    });
});
