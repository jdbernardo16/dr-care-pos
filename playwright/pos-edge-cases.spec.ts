import { test, expect } from '@playwright/test';
import { POSPage } from './pages/POSPage';

test.describe('POS Edge Cases', () => {

    let pos: POSPage;

    test.beforeEach(async ({ page }) => {
        pos = new POSPage(page);
        await pos.goto();
        await pos.enableAutofocus();
    });

    test('very long barcode string (50+ chars)', async ({ page }) => {
        const longBarcode = '9'.repeat(50);
        await pos.scanBarcode(longBarcode);

        const response = page.waitForResponse(
            resp => resp.url().includes('using-barcode'),
            { timeout: 10000 }
        ).catch(() => null);

        // Should not crash the app
        expect(await pos.getCartProductCount()).toBe(0);
        const bodyText = await page.evaluate(() => document.body.innerText);
        expect(bodyText).toContain('No products added');
    });

    test('barcode with special characters', async ({ page }) => {
        await pos.scanBarcode('!@#$%^&*()');
        await page.waitForTimeout(3000);

        expect(await pos.getCartProductCount()).toBe(0);
    });

    test('rapid successive scans of different barcodes', async ({ page }) => {
        const barcodes = ['856370916519', '644790265829', '291664704396'];
        const products = ['Biogesic', 'Alaxan', 'Medicol'];

        for (let i = 0; i < barcodes.length; i++) {
            await pos.scanBarcode(barcodes[i]);
            await pos.acceptQuantityPopup('1');
        }

        for (const name of products) {
            expect(await pos.cartHasProduct(name)).toBe(true);
        }
    });

    test('scan barcode with letters mixed in', async ({ page }) => {
        await pos.scanBarcode('ABC856370916519XYZ');
        await page.waitForTimeout(3000);

        expect(await pos.getCartProductCount()).toBe(0);
    });

    test('partial barcode does not match', async ({ page }) => {
        await pos.scanBarcode('856'); // partial barcode of Biogesic
        await page.waitForTimeout(3000);

        expect(await pos.cartHasProduct('Biogesic')).toBe(false);
    });

    test('naviage to POS from dashboard and back', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');

        await page.goto('/dashboard/pos');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('#grid-header')).toBeVisible({ timeout: 10000 });
    });

    test('no javascript errors on POS page load', async ({ page }) => {
        const errors: string[] = [];
        page.on('console', msg => {
            if (msg.type() === 'error') errors.push(msg.text());
        });

        await page.goto('/dashboard/pos');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        const criticalErrors = errors.filter(e =>
            !e.includes('closest is not a function') &&
            !e.includes('favicon') &&
            !e.includes('404')
        );
        expect(criticalErrors.length).toBe(0);
    });

    test('product grid categories are clickable', async ({ page }) => {
        const categories = await page.locator('#grid-container h3').allTextContents();
        expect(categories.length).toBeGreaterThan(0);

        for (const cat of categories.slice(0, 2)) {
            await page.locator(`h3:has-text("${cat}")`).click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(500);

            const breadcrumb = await page.evaluate(() => document.body.innerText);
            expect(breadcrumb).toContain(cat);

            await page.locator('a:has-text("Home")').click();
            await page.waitForTimeout(500);
        }
    });
});
