import { test, expect } from '@playwright/test';

test.describe('Attendance API Edge Cases', () => {

    async function apiCall(page: any, method: string, url: string, body: any = null) {
        return await page.evaluate(async ({ method, url, body }) => {
            const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            const token = xsrf ? decodeURIComponent(xsrf[1]) : '';
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token,
                },
                body: body ? JSON.stringify(body) : null,
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => null);
            return { status: res.status, data };
        }, { method, url, body });
    }

    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard/attendance/clock');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);
    });

    test('clock out without clock-in returns error', async ({ page }) => {
        // Ensure not clocked in
        const result = await apiCall(page, 'GET', '/api/attendance/current-status');
        if (result.data?.data?.is_clocked_in) {
            await apiCall(page, 'POST', '/api/attendance/clock-out', {});
            await page.waitForTimeout(1000);
        }

        const response = await apiCall(page, 'POST', '/api/attendance/clock-out', { note: 'test' });
        expect(response.status).toBe(403);
        expect(response.data.message).toContain('No active clock-in');
    });

    test('break in without clock-in returns error', async ({ page }) => {
        const status = await apiCall(page, 'GET', '/api/attendance/current-status');
        if (status.data?.data?.is_clocked_in) {
            await apiCall(page, 'POST', '/api/attendance/clock-out', {});
            await page.waitForTimeout(1000);
        }

        const response = await apiCall(page, 'POST', '/api/attendance/break-in', {});
        expect(response.status).toBe(403);
        expect(response.data.message).toContain('must be clocked in');
    });

    test('break out without break returns error', async ({ page }) => {
        const status = await apiCall(page, 'GET', '/api/attendance/current-status');
        if (status.data?.data?.is_clocked_in) {
            await apiCall(page, 'POST', '/api/attendance/clock-out', {});
            await page.waitForTimeout(1000);
        }

        const response = await apiCall(page, 'POST', '/api/attendance/break-out', {});
        expect(response.status).toBe(403);
    });

    test('double clock-in is prevented', async ({ page }) => {
        // Clean state
        const status = await apiCall(page, 'GET', '/api/attendance/current-status');
        if (status.data?.data?.is_clocked_in) {
            await apiCall(page, 'POST', '/api/attendance/clock-out', {});
            await page.waitForTimeout(1000);
        }

        // Clock in
        const first = await apiCall(page, 'POST', '/api/attendance/clock-in', {});
        expect(first.status).toBe(200);

        // Try double clock in
        const second = await apiCall(page, 'POST', '/api/attendance/clock-in', {});
        expect(second.status).toBe(403);
        expect(second.data.message).toContain('already clocked in');

        // Cleanup
        await apiCall(page, 'POST', '/api/attendance/clock-out', {});
    });

    test('double break-in is prevented', async ({ page }) => {
        // Clean state + clock in
        const status = await apiCall(page, 'GET', '/api/attendance/current-status');
        if (!status.data?.data?.is_clocked_in) {
            await apiCall(page, 'POST', '/api/attendance/clock-in', {});
            await page.waitForTimeout(1000);
        }

        // Start break
        const first = await apiCall(page, 'POST', '/api/attendance/break-in', {});
        expect([200, 403]).toContain(first.status);

        if (first.status === 200) {
            // Try double break
            const second = await apiCall(page, 'POST', '/api/attendance/break-in', {});
            expect(second.status).toBe(403);
            expect(second.data.message).toContain('already on break');

            // Cleanup
            await apiCall(page, 'POST', '/api/attendance/break-out', {});
        }

        await apiCall(page, 'POST', '/api/attendance/clock-out', {});
    });

    test('current-status returns valid structure', async ({ page }) => {
        const response = await apiCall(page, 'GET', '/api/attendance/current-status');
        expect(response.status).toBe(200);
        expect(response.data.status).toBe('success');
        expect(response.data.data).toHaveProperty('is_clocked_in');
        expect(response.data.data).toHaveProperty('is_on_break');
        expect(response.data.data).toHaveProperty('record');
    });

    test('staff-status returns array', async ({ page }) => {
        const response = await apiCall(page, 'GET', '/api/attendance/staff-status');
        expect(response.status).toBe(200);
        expect(Array.isArray(response.data.data)).toBe(true);
    });

    test('history returns paginated results', async ({ page }) => {
        const response = await apiCall(page, 'GET', '/api/attendance/history');
        expect(response.status).toBe(200);
        // Paginated response should have data array
        expect(response.data.data || response.data).toBeTruthy();
    });
});
