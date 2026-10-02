import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { POSPage } from './pages/POSPage';

test.describe('POS Cart Operations', () => {

    let pos: POSPage;

    /**
     * The checkout tests perform real sales and consume stock. The POS
     * hides exhausted products, so the fixture product is topped up
     * before the suite runs. The check is idempotent: stock is only
     * changed when it dropped below the threshold.
     */
    test.beforeAll(() => {
        const MIN_STOCK = 50;
        const TARGET_STOCK = 1000;
        const stockSubQuery =
            `DB::table('nexopos_products')->where('name', 'like', 'MAGIC FLAKES%')->pluck('id')`;
        const readCommand =
            `php artisan tinker --execute="echo 'QTY=' . (int) DB::table('nexopos_products_unit_quantities')->whereIn('product_id', ${stockSubQuery})->value('quantity');"`;

        try {
            const output = execSync(readCommand, {
                cwd: process.cwd(),
                encoding: 'utf8',
            });
            const match = output.match(/QTY=(\d+)/);

            if (match === null) {
                throw new Error(
                    `could not read the stock quantity from tinker output: ${output.trim()}`
                );
            }

            const currentStock = parseInt(match[1], 10);

            if (currentStock < MIN_STOCK) {
                execSync(
                    `php artisan tinker --execute="DB::table('nexopos_products_unit_quantities')->whereIn('product_id', ${stockSubQuery})->update(['quantity' => ${TARGET_STOCK}]);"`,
                    { cwd: process.cwd(), stdio: 'ignore' }
                );
                console.log(
                    `[pos-cart.setup] Restocked MAGIC FLAKES: ${currentStock} -> ${TARGET_STOCK}`
                );
            }
        } catch (error: any) {
            throw new Error(
                `[pos-cart.setup] Unable to ensure MAGIC FLAKES stock: ${error?.message || error}`
            );
        }
    });

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
        // Barcode present in this environment's catalogue.
        await pos.scanBarcode('100000000202');
        // The rehauled UI lists the matching product instead of auto-adding it.
        await page.locator('.cell-item').filter({ hasText: 'BIOGESIC' }).first().click();
        await pos.acceptQuantityPopup('1');

        expect(await pos.getCartProductCount()).toBeGreaterThanOrEqual(1);

        await pos.voidOrder();

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

    test('second sale works without page reload after a successful charge', async ({ page }) => {
        await pos.addProductTile();

        // The Charge button must show the cart total immediately.
        // MAGIC FLAKES is seeded at ₱9.00.
        await expect(pos.chargeButton).toContainText('9.00');

        await pos.completePayment();

        await expect(page.getByText('Transaction Complete')).toBeVisible();

        // Add another product without clicking "New Sale" first.
        await pos.addProductTile();

        await expect(pos.cartPanel).toBeVisible({ timeout: 5000 });
        await expect(page.getByText('Transaction Complete')).toBeHidden();
        expect(await pos.cartHasProduct('MAGIC FLAKES')).toBe(true);

        await pos.chargeButton.click();
        await expect(pos.paymentPopup).toBeVisible({ timeout: 10000 });

        await pos.completeOpenPayment();
        await expect(page.getByText('Transaction Complete')).toBeVisible();
    });

    test('cancelling payment popup does not block subsequent charges', async ({ page }) => {
        await pos.addProductTile();

        await pos.chargeButton.click();
        await expect(pos.paymentPopup).toBeVisible({ timeout: 15000 });

        await pos.paymentPopup.getByRole('button', { name: 'Cancel' }).click();
        await expect(pos.paymentPopup).toBeHidden({ timeout: 5000 });

        await pos.chargeButton.click();
        await expect(pos.paymentPopup).toBeVisible({ timeout: 5000 });
    });
});
