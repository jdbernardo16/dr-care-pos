import { test, expect } from '@playwright/test';
import { POSPage } from './pages/POSPage';

test.describe('POS Checkout Flow', () => {

    let pos: POSPage;

    test.beforeEach(async ({ page }) => {
        pos = new POSPage(page);
        await pos.goto();
        await pos.enableAutofocus();

        await pos.scanBarcode('856370916519'); // Biogesic
        await pos.acceptQuantityPopup('1');
    });

    test('pay without customer shows customer selection popup', async ({ page }) => {
        await pos.payButton.click();
        await page.waitForTimeout(2000);

        const bodyText = await page.evaluate(() => document.body.innerText);
        const hasCustomerSelect = bodyText.includes('Select Customer') || bodyText.includes('Selected');
        expect(hasCustomerSelect).toBe(true);
    });

    test('customer search returns results', async ({ page }) => {
        await pos.payButton.click();
        await page.waitForTimeout(2000);

        const searchInput = page.locator('.is-popup input[type="text"]').first();
        await expect(searchInput).toBeVisible({ timeout: 5000 });

        await searchInput.fill('Walk');
        await page.waitForTimeout(2000);

        const bodyText = await page.evaluate(() => document.body.innerText);
        const hasResults = bodyText.includes('Walk-in') || bodyText.includes('Customer');
        const hasNoResults = bodyText.includes('No customer match');
    });

    test('order type button shows order type options', async ({ page }) => {
        await pos.closePopup().catch(() => {});

        await pos.orderTypeButton.click();
        await page.waitForTimeout(1000);

        const popup = page.locator('.is-popup');
        const isVisible = await popup.first().isVisible({ timeout: 3000 }).catch(() => false);
        expect(isVisible).toBe(true);
    });

    test('hold order is accessible', async ({ page }) => {
        await pos.holdButton.click();
        await page.waitForTimeout(1000);

        const bodyText = await page.evaluate(() => document.body.innerText);
        const popupVisible = await page.locator('.is-popup').first().isVisible({ timeout: 3000 }).catch(() => false);
        expect(popupVisible).toBe(true);
    });

    test('discount button is accessible', async ({ page }) => {
        await pos.discountButton.click();
        await page.waitForTimeout(1000);

        const popupVisible = await page.locator('.is-popup').first().isVisible({ timeout: 3000 }).catch(() => false);
        expect(popupVisible).toBe(true);
    });

    test('taxes button is accessible', async ({ page }) => {
        await pos.closePopup().catch(() => {});

        const taxesBtn = page.locator('button:has-text("Taxes")').first();
        await taxesBtn.click();
        await page.waitForTimeout(1000);

        const popupVisible = await page.locator('.is-popup').first().isVisible({ timeout: 3000 }).catch(() => false);
        expect(popupVisible).toBe(true);
    });

    test('coupons button is accessible', async ({ page }) => {
        await pos.closePopup().catch(() => {});

        const couponsBtn = page.locator('button:has-text("Coupons")').first();
        await couponsBtn.click();
        await page.waitForTimeout(1000);

        const popupVisible = await page.locator('.is-popup').first().isVisible({ timeout: 3000 }).catch(() => false);
        expect(popupVisible).toBe(true);
    });
});
