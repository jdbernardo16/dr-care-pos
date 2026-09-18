import { test, expect } from '@playwright/test';

/**
 * Verifies the Phase 1 offline foundation:
 *  - the catalog snapshot is stored in IndexedDB
 *  - the POS shows the offline banner when the server is unreachable
 *  - the grid falls back to the cached catalog
 *
 * The offline state is forced through the app's own singleton
 * (window.nsOffline) so the test is deterministic instead of waiting
 * for the real heartbeat schedule.
 */
test.describe('Offline foundation', () => {

    test('caches the catalog and browses it while offline', async ({ page, context }) => {
        await page.goto('/dashboard/pos');
        await page.waitForSelector('#pos-container');

        /**
         * Wait for the snapshot using the app's own database connection.
         * Do NOT open `drcare-offline` with raw indexedDB here: opening it
         * before the app would create an empty version-1 database without
         * the object stores.
         *
         * `expect.poll` re-runs the async IndexedDB read until the snapshot
         * lands. `page.waitForFunction` is not used here because it does not
         * await promise-returning predicates with the default polling.
         */
        await expect
            .poll(
                async () => {
                    return await page.evaluate(async () => {
                        const offline = (window as any).nsOffline;

                        if (!offline) {
                            return false;
                        }

                        const meta = await offline.database.getMeta('snapshot');

                        return !!meta;
                    });
                },
                { timeout: 30000 },
            )
            .toBeTruthy();

        // Go offline and force two failed heartbeats.
        await context.setOffline(true);

        await expect
            .poll(
                async () => {
                    return await page.evaluate(async () => {
                        const offline = (window as any).nsOffline;

                        await offline.connectivity.checkNow();

                        return offline.connectivity.status;
                    });
                },
                { timeout: 15000 },
            )
            .toBe('offline');

        await expect(page.locator('#ns-pos-offline-banner')).toBeVisible();

        // The grid must render cached categories.
        await expect(page.locator('[data-category-id]').first()).toBeVisible();

        // Drill into categories until cached products render.
        for (let depth = 0; depth < 4; depth++) {
            if ((await page.locator('[data-product-id]').count()) > 0) {
                break;
            }

            const category = page.locator('[data-category-id]').first();

            if ((await category.count()) === 0) {
                break;
            }

            await category.click();
            await page.waitForTimeout(250);
        }

        await expect(page.locator('[data-product-id]').first()).toBeVisible();
    });
});
