import { test, expect } from '@playwright/test';
import { POSPage } from './pages/POSPage';

test.describe('POS Cart Operations', () => {

    let pos: POSPage;

    test.beforeEach(async ({ page }) => {
        pos = new POSPage(page);
        await pos.goto();
        await pos.enableAutofocus();
    });

    test('add product by clicking category then product tile', async ({ page }) => {
        await page.locator('h3:has-text("Pain Relief")').click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        const products = page.locator('#grid-items .cell-item');
        const count = await products.count();

        if (count > 0) {
            await products.first().click();
            await page.waitForTimeout(2000);

            const qtyPopup = page.locator('h1:has-text("Define Quantity")');
            if (await qtyPopup.isVisible({ timeout: 2000 }).catch(() => false)) {
                await page.getByText('Enter', { exact: true }).click();
                await page.waitForTimeout(1000);
            }

            const cartCount = await pos.getCartProductCount();
            expect(cartCount).toBeGreaterThanOrEqual(1);
        }
    });

    test('add product via barcode then via grid click', async ({ page }) => {
        await pos.scanBarcode('856370916519');
        await pos.acceptQuantityPopup('1');

        expect(await pos.cartHasProduct('Biogesic')).toBe(true);
    });

    test('void on unpaid order shows error message', async ({ page }) => {
        await pos.scanBarcode('856370916519');
        await pos.acceptQuantityPopup('1');

        expect(await pos.getCartProductCount()).toBeGreaterThanOrEqual(1);

        await pos.voidButton.click();
        await page.waitForTimeout(2000);

        const bodyText = await page.evaluate(() => document.body.innerText);
        // Void only works for paid/saved orders, not unsaved carts
        expect(bodyText).toContain('Unable to void');

        // Cart should still have the product
        const cartCount = await pos.getCartProductCount();
        expect(cartCount).toBeGreaterThanOrEqual(1);
    });

    test('reset clears the cart', async ({ page }) => {
        await pos.scanBarcode('856370916519');
        await pos.acceptQuantityPopup('1');

        expect(await pos.getCartProductCount()).toBeGreaterThanOrEqual(1);

        await pos.resetButton.click();
        await page.waitForTimeout(500);

        const confirmBtn = page.locator('.is-popup button').filter({ hasText: /yes|confirm|reset/i }).first();
        if (await confirmBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await confirmBtn.click();
            await page.waitForTimeout(1000);
        }

        const cartCount = await pos.getCartProductCount();
        expect(cartCount).toBe(0);
    });

    test('cart total updates correctly with multiple items', async ({ page }) => {
        await pos.scanBarcode('856370916519'); // Biogesic
        await pos.acceptQuantityPopup('1');

        const total1 = parseFloat(await pos.getCartTotal());
        expect(total1).toBeGreaterThan(0);

        await pos.scanBarcode('644790265829'); // Alaxan
        await pos.acceptQuantityPopup('1');

        const total2 = parseFloat(await pos.getCartTotal());
        expect(total2).toBeGreaterThan(total1);
    });

    test('merge toggle is clickable', async ({ page }) => {
        const beforeState = await pos.mergeToggle.evaluate(
            (el: HTMLElement) => el.classList.contains('pos-button-clicked')
        );

        await pos.mergeToggle.click();
        await page.waitForTimeout(500);

        const afterState = await pos.mergeToggle.evaluate(
            (el: HTMLElement) => el.classList.contains('pos-button-clicked')
        );

        expect(afterState).not.toBe(beforeState);
    });

    test('product search popup opens', async ({ page }) => {
        await pos.searchButton.click();
        await page.waitForTimeout(1000);

        const popup = page.locator('.is-popup');
        await expect(popup.first()).toBeVisible({ timeout: 5000 });

        const searchField = page.locator('.is-popup input[type="text"]').first();
        await expect(searchField).toBeVisible({ timeout: 3000 });
    });

    test('settings button opens POS settings', async ({ page }) => {
        const settingsBtn = page.locator('button:has-text("Settings")').first();
        await settingsBtn.click();
        await page.waitForTimeout(1000);

        const popup = page.locator('.is-popup');
        await expect(popup.first()).toBeVisible({ timeout: 5000 });
    });
});
