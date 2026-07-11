import { test, expect } from '@playwright/test';

test.describe('Attendance Clock', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard/attendance/clock');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
    });

    test('clock page loads with time display', async ({ page }) => {
        await expect(page.locator('.ns-attendance-clock')).toBeVisible({ timeout: 10000 });
        const timeText = await page.locator('.ns-attendance-clock').textContent();
        expect(timeText).toMatch(/\d{2}:\d{2}:\d{2}/);
    });

    test('clock page shows date', async ({ page }) => {
        const dateText = await page.evaluate(() => document.body.innerText);
        expect(dateText).toMatch(/\w+day,/i);
    });

    test('clock in/out cycle works', async ({ page }) => {
        // If clocked in, clock out first
        const clockOutBtn = page.locator('button:has-text("Clock Out")');
        if (await clockOutBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await clockOutBtn.click();
            await page.waitForTimeout(2000);
        }

        // Clock In
        await page.locator('button:has-text("Clock In")').click();
        await page.waitForTimeout(2000);
        let bodyText = await page.evaluate(() => document.body.innerText);
        expect(bodyText).toContain('Clocked In');

        // Clock Out
        await page.locator('button:has-text("Clock Out")').click();
        await page.waitForTimeout(2000);
        bodyText = await page.evaluate(() => document.body.innerText);
        expect(bodyText).toContain('Not Clocked In');
    });

    test('break in/out cycle works', async ({ page }) => {
        // Ensure clocked in
        const clockInBtn = page.locator('button:has-text("Clock In")');
        if (await clockInBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await clockInBtn.click();
            await page.waitForTimeout(2000);
        }

        // Break In
        await page.locator('button:has-text("Break In")').click();
        await page.waitForTimeout(2000);
        let bodyText = await page.evaluate(() => document.body.innerText);
        expect(bodyText).toContain('On Break');

        // Break Out
        await page.locator('button:has-text("Break Out")').click();
        await page.waitForTimeout(2000);
        bodyText = await page.evaluate(() => document.body.innerText);
        expect(bodyText).toContain('Clocked In');
    });

    test('note field accepts text', async ({ page }) => {
        const noteField = page.locator('textarea[placeholder*="note"]');
        await expect(noteField).toBeVisible();
        await noteField.fill('Test note for attendance');
        const value = await noteField.inputValue();
        expect(value).toBe('Test note for attendance');
    });

    test('last completed shift shows after clock out', async ({ page }) => {
        // Ensure clocked in
        const clockInBtn = page.locator('button:has-text("Clock In")');
        if (await clockInBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await clockInBtn.click();
            await page.waitForTimeout(2000);
        }

        // Clock Out
        await page.locator('button:has-text("Clock Out")').click();
        await page.waitForTimeout(2000);

        const bodyText = await page.evaluate(() => document.body.innerText);
        expect(bodyText).toContain('Last Completed Shift');
    });

    test('overtime link is present', async ({ page }) => {
        const link = page.locator('a[href*="overtime/file"]');
        await expect(link).toBeVisible();
    });

    test('clock in prevents double clock-in', async ({ page }) => {
        // Ensure clocked in
        const clockInBtn = page.locator('button:has-text("Clock In")');
        if (await clockInBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await clockInBtn.click();
            await page.waitForTimeout(2000);
        }

        // When clocked in, Clock In button should not be visible
        const isVisible = await page.locator('button:has-text("Clock In")').isVisible({ timeout: 1000 }).catch(() => false);
        expect(isVisible).toBe(false);
    });

    test('status badge updates correctly', async ({ page }) => {
        // Check current state has a status badge
        const badge = page.locator('.ns-attendance-clock span.rounded-full');
        await expect(badge.first()).toBeVisible();
        const badgeText = await badge.first().textContent();
        expect(['Not Clocked In', 'Clocked In', 'On Break']).toContain(badgeText?.trim());
    });

    test('no critical JS errors on page', async ({ page }) => {
        const errors: string[] = [];
        page.on('console', msg => {
            if (msg.type() === 'error') errors.push(msg.text());
        });

        await page.goto('/dashboard/attendance/clock');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Filter out known Service Worker scope error
        const criticalErrors = errors.filter(e =>
            !e.includes('Service Worker') &&
            !e.includes('ServiceWorker') &&
            !e.includes('scope') &&
            !e.includes('favicon')
        );
        expect(criticalErrors.length).toBe(0);
    });
});
