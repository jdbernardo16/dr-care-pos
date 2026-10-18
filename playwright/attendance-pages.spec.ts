import { test, expect } from '@playwright/test';

test.describe('Attendance/Payroll Pages', () => {

    const pages = [
        { name: 'Attendance Records', url: '/dashboard/attendance' },
        { name: 'Attendance Create', url: '/dashboard/attendance/create' },
        { name: 'Attendance Clock', url: '/dashboard/attendance/clock' },
        { name: 'Holidays', url: '/dashboard/holidays' },
        { name: 'Overtime List', url: '/dashboard/overtime' },
        { name: 'Overtime File', url: '/dashboard/overtime/file' },
        { name: 'Payroll List', url: '/dashboard/payroll' },
        { name: 'Payroll Create', url: '/dashboard/payroll/create' },
    ];

    for (const p of pages) {
        test(`${p.name} page loads successfully`, async ({ page }) => {
            const response = await page.goto(p.url);
            expect(response?.status()).toBe(200);

            const title = await page.title();
            expect(title.length).toBeGreaterThan(0);

            const bodyText = await page.evaluate(() => document.body.innerText);
            expect(bodyText.length).toBeGreaterThan(100);

            // Should not show Laravel error pages
            expect(bodyText).not.toContain('Whoops');
            expect(bodyText).not.toContain('Exception');
        });
    }

    test('sidebar has Attendance menu', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');

        const attendanceLink = page.locator('a:has-text("Attendance")').first();
        await expect(attendanceLink).toBeVisible({ timeout: 5000 });
    });

    test('sidebar has Payroll menu', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');

        const payrollLink = page.locator('a:has-text("Payroll")').first();
        await expect(payrollLink).toBeVisible({ timeout: 5000 });
    });

    test('attendance submenu items are present', async ({ page }) => {
        await page.goto('/dashboard/attendance/clock');
        await page.waitForLoadState('networkidle');

        const bodyText = await page.evaluate(() => document.body.innerText);
        expect(bodyText).toContain('Clock In/Out');
        expect(bodyText).toContain('Records');
    });

    test('payroll submenu items are present', async ({ page }) => {
        await page.goto('/dashboard/payroll');
        await page.waitForLoadState('networkidle');

        const bodyText = await page.evaluate(() => document.body.innerText);
        expect(bodyText).toContain('Pay Runs') ;
        expect(bodyText).toContain('Create Pay Run');
    });
});
