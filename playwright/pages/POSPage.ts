import { type Page, type Locator, expect } from '@playwright/test';

export class POSPage {
    readonly page: Page;

    // Toolbar buttons
    readonly searchButton: Locator;
    readonly mergeToggle: Locator;
    readonly autofocusToggle: Locator;
    readonly barcodeInput: Locator;

    // Cart area
    readonly cartSection: Locator;
    readonly emptyCartMessage: Locator;
    readonly payButton: Locator;
    readonly holdButton: Locator;
    readonly discountButton: Locator;
    readonly voidButton: Locator;

    // Sidebar buttons
    readonly ordersButton: Locator;
    readonly orderTypeButton: Locator;
    readonly customersButton: Locator;
    readonly resetButton: Locator;

    // Grid
    readonly productGrid: Locator;
    readonly categories: Locator;

    constructor(page: Page) {
        this.page = page;

        this.searchButton     = page.locator('[title="Search for products."]');
        this.mergeToggle      = page.locator('[title="Toggle merging similar products."]');
        this.autofocusToggle  = page.locator('[title="Toggle auto focus."]');
        this.barcodeInput     = page.locator('#grid-header input[type="text"]');

        this.cartSection      = page.locator('[id*="pos-cart"], [class*="pos-cart"]').first();
        this.emptyCartMessage = page.locator('h3:has-text("No products added")');
        this.payButton        = page.locator('text=Pay').first();
        this.holdButton       = page.locator('text=Hold').first();
        this.discountButton   = page.locator('text=Discount').first();
        this.voidButton       = page.locator('text=Void').first();

        this.ordersButton     = page.locator('button:has-text("Orders")');
        this.orderTypeButton  = page.locator('button:has-text("Order Type")');
        this.customersButton  = page.locator('button:has-text("Customers")');
        this.resetButton      = page.locator('button:has-text("Reset")');

        this.productGrid      = page.locator('#grid-items, #grid-container').first();
        this.categories       = page.locator('#grid-items .cell-item, [class*="cursor-pointer"] h3');
    }

    async goto() {
        await this.page.goto('/dashboard/pos');
        await this.page.waitForLoadState('networkidle');
        await this.barcodeInput.waitFor({ state: 'visible', timeout: 15000 });
    }

    async enableAutofocus() {
        const isOn = await this.autofocusToggle.evaluate(
            (el: HTMLElement) => el.classList.contains('pos-button-clicked')
        );
        if (!isOn) {
            await this.autofocusToggle.click();
            await this.page.waitForTimeout(600);
        }
    }

    async disableAutofocus() {
        const isOn = await this.autofocusToggle.evaluate(
            (el: HTMLElement) => el.classList.contains('pos-button-clicked')
        );
        if (isOn) {
            await this.autofocusToggle.click();
            await this.page.waitForTimeout(600);
        }
    }

    async scanBarcode(barcode: string) {
        await this.barcodeInput.focus();
        await this.barcodeInput.fill('');

        for (const char of barcode) {
            await this.page.keyboard.type(char);
            await this.page.waitForTimeout(5);
        }

        await this.page.waitForTimeout(2500);
    }

    async acceptQuantityPopup(quantity: string = '1') {
        const popup = this.page.locator('h1:has-text("Define Quantity")');
        if (await popup.isVisible({ timeout: 2000 }).catch(() => false)) {
            if (quantity !== '1') {
                // The numpad is a custom component with clickable number buttons
                // First clear existing value (press backspace a few times)
                const backspaceBtn = this.page.locator('.is-popup [class*="la-backspace"], .is-popup button:has([class*="backspace"])').first();
                for (let i = 0; i < 3; i++) {
                    if (await backspaceBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
                        await backspaceBtn.click();
                    }
                }
                // Click each digit on the numpad
                for (const digit of quantity) {
                    const keyBtn = this.page.locator(`.is-popup [class*="cursor-pointer"]:has-text("${digit}")`).first();
                    if (await keyBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
                        await keyBtn.click();
                    }
                }
                await this.page.waitForTimeout(300);
            }
            await this.page.getByText('Enter', { exact: true }).click();
            await this.page.waitForTimeout(1500);
        }
    }

    async closePopup() {
        const closeBtn = this.page.locator('.is-popup [class*="close"], .is-popup button').first();
        if (await closeBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await closeBtn.click();
        } else {
            await this.page.keyboard.press('Escape');
        }
        await this.page.waitForTimeout(500);
    }

    async getCartTotal(): Promise<string> {
        const bodyText = await this.page.evaluate(() => document.body.innerText);
        const totalMatch = bodyText.match(/Total\s+USD([\d.]+)/g);
        if (totalMatch && totalMatch.length > 0) {
            const lastTotal = totalMatch[totalMatch.length - 1];
            return lastTotal.replace(/Total\s+USD/, '');
        }
        return '0.00';
    }

    async getCartProductCount(): Promise<number> {
        const bodyText = await this.page.evaluate(() => document.body.innerText);
        if (bodyText.includes('No products added')) return 0;

        const priceLines = bodyText.match(/Price\s*:\s*USD[\d.]+/g);
        return priceLines ? priceLines.length : 0;
    }

    async cartHasProduct(name: string): Promise<boolean> {
        const bodyText = await this.page.evaluate(() => document.body.innerText);
        return bodyText.includes(name);
    }

    async addProductFromGrid(categoryName: string) {
        await this.page.locator(`h3:has-text("${categoryName}")`).click();
        await this.page.waitForLoadState('networkidle');
        await this.page.waitForTimeout(1000);
    }

    async resetCart() {
        const hasDialog = await this.page.evaluate(() => document.querySelector('.modal-state')).catch(() => null);
        await this.resetButton.click();
        await this.page.waitForTimeout(500);

        const confirmBtn = this.page.locator('.is-popup button:has-text("Yes"), .is-popup button:has-text("Confirm"), .is-popup button:has-text("Reset")').first();
        if (await confirmBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await confirmBtn.click();
            await this.page.waitForTimeout(1000);
        }
    }
}
