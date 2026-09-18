# Offline Cash Mode — Phase 1 (Foundation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the POS able to load and browse its catalog offline (no selling yet): fix the service worker, add offline snapshot + heartbeat endpoints, cache the catalog in IndexedDB, monitor connectivity, and fall back to the cache in the product grid.

**Architecture:** The Laravel server stays on Hostinger as the master. The Android tablet caches a catalog snapshot (products, categories, customers, payment types, register state) in IndexedDB every 15 minutes while online. A heartbeat monitor detects outages. When offline, the grid reads from IndexedDB. Phase 2 (separate plan) adds the offline checkout, outbox and sync; Phase 3 adds reconciliation.

**Tech Stack:** Laravel 11 / PHP 8.2+, Vue 3 (Options API) + TypeScript, rxjs, native IndexedDB, `vite-plugin-pwa` (workbox generateSW), Jest (+ `fake-indexeddb`) for TS unit tests, Playwright for E2E.

**Spec:** `docs/superpowers/specs/2026-09-18-offline-cash-mode-design.md`

---

## File Structure

**Server**
- Create: `routes/api/pos.php` — heartbeat + offline snapshot routes
- Modify: `routes/api-base.php` — include the new route file
- Create: `app/Http/Controllers/Dashboard/PosController.php` — both endpoints
- Create: `tests/Feature/PosHeartbeatTest.php`
- Create: `tests/Feature/PosOfflineSnapshotTest.php`

**Client (offline layer)**
- Create: `resources/ts/libraries/offline/offline-db.ts` — IndexedDB plumbing
- Create: `resources/ts/libraries/offline/offline-catalog.ts` — pure catalog logic (search, category view)
- Create: `resources/ts/libraries/offline/offline-connectivity.ts` — heartbeat monitor
- Create: `resources/ts/libraries/offline/offline-snapshot.ts` — snapshot fetch + persist + refresh
- Create: `resources/ts/libraries/offline/offline.ts` — singleton wiring exported to components
- Create: `resources/ts/pages/dashboard/pos/ns-pos-offline-banner.vue` — status banner
- Modify: `resources/ts/pages/dashboard/pos/ns-pos.vue` — mount banner, initialize offline mode
- Modify: `resources/ts/pages/dashboard/pos/ns-pos-grid.vue` — offline catalog fallbacks

**Build / infra**
- Modify: `public/.htaccess` — `Service-Worker-Allowed` header
- Modify: `vite.config.js` — workbox runtime caching (POS page + product images)
- Create: `jest.config.js`, modify `package.json` — TS unit test runner

**Tests**
- Create: `ts-tests/offline-catalog.test.ts`
- Create: `ts-tests/offline-db.test.ts`
- Create: `ts-tests/offline-connectivity.test.ts`
- Create: `ts-tests/offline-snapshot.test.ts`
- Create: `playwright/offline-foundation.spec.ts`

---

## Task 1: Frontend test runner (Jest + fake-indexeddb)

**Files:**
- Modify: `package.json`
- Create: `jest.config.js`

- [ ] **Step 1: Install dev dependencies**

Run:
```bash
npm install --save-dev jest babel-jest @babel/preset-env @babel/preset-typescript fake-indexeddb
```
Expected: `package.json` gains the five devDependencies; `package-lock.json` updated.

- [ ] **Step 2: Create the Jest config**

Create `jest.config.js`:
```js
module.exports = {
    testEnvironment: 'node',
    testMatch: ['<rootDir>/ts-tests/**/*.test.ts'],
    transform: {
        '^.+\\.ts$': [
            'babel-jest',
            {
                babelrc: false,
                configFile: false,
                presets: [
                    ['@babel/preset-env', { targets: { node: 'current' } }],
                    '@babel/preset-typescript',
                ],
            },
        ],
    },
};
```

- [ ] **Step 3: Verify the existing TS test runs**

Run:
```bash
npx jest ts-tests/form-validation.test.ts
```
Expected: PASS (3 tests).

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json jest.config.js
git commit -m "chore: add jest runner for TS unit tests"
```

---

## Task 2: Service worker scope fix + app-shell caching

**Files:**
- Modify: `public/.htaccess`
- Modify: `vite.config.js`

The service worker is served from `/build/sw.js` and registered with `scope: '/'` in `resources/views/layout/base.blade.php:42`. Browsers reject that scope unless the response carries `Service-Worker-Allowed: /`. The registration code does not change.

- [ ] **Step 1: Add the header to `.htaccess`**

In `public/.htaccess`, append after the closing `</IfModule>` of the rewrite block:
```apache
# Allow the service worker to control the whole origin (PWA offline support).
# Wrapped in IfModule so a missing mod_headers never breaks the site.
<IfModule mod_headers.c>
    <FilesMatch "^sw\.js$">
        Header set Service-Worker-Allowed "/"
    </FilesMatch>
</IfModule>
```

- [ ] **Step 2: Add runtime caching to the service worker**

In `vite.config.js`, inside `VitePWA({ workbox: { ... } })`, add these entries to `runtimeCaching` **before** the existing `/api/` entry:
```js
{
    // Allows the POS page to reopen while offline (network first, falls back to cache).
    urlPattern: /\/dashboard\/pos$/,
    handler: "NetworkFirst",
    options: {
        cacheName: "pos-page",
        networkTimeoutSeconds: 3,
        expiration: {
            maxEntries: 10,
            maxAgeSeconds: 60 * 60 * 24,
        },
    },
},
{
    // Product images become available offline once viewed.
    urlPattern: /\/storage\/.+\.(?:png|jpg|jpeg|webp|gif|svg)$/i,
    handler: "CacheFirst",
    options: {
        cacheName: "product-images",
        expiration: {
            maxEntries: 500,
            maxAgeSeconds: 60 * 60 * 24 * 30,
        },
    },
},
```

- [ ] **Step 3: Build and verify the SW output**

Run:
```bash
npm run build && grep -c "pos-page" public/build/sw.js
```
Expected: build succeeds; the command prints a number `>= 1`.

- [ ] **Step 4: Verify scope locally**

Serve the app and check that the SW activates with scope `/`:
```bash
php artisan serve --port=8000
```
Then in Chrome DevTools → Application → Service Workers: the worker at `/build/sw.js` shows status **activated** and scope `http://localhost:8000/`.
Note: `php artisan serve` does not apply `.htaccess`, so locally the registration may still fail with a scope error — that is expected. Playwright injects the header in Task 10, and on Hostinger Apache applies the `.htaccess`. If the production check fails, the fallback is to set the header via cPanel or confirm `mod_headers` with Hostinger support.

- [ ] **Step 5: Commit**

```bash
git add public/.htaccess vite.config.js
git commit -m "fix: allow service worker root scope and cache POS shell"
```

---

## Task 3: Heartbeat endpoint

**Files:**
- Create: `routes/api/pos.php`
- Modify: `routes/api-base.php`
- Create: `app/Http/Controllers/Dashboard/PosController.php`
- Test: `tests/Feature/PosHeartbeatTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PosHeartbeatTest.php`:
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\WithAuthentication;

class PosHeartbeatTest extends TestCase
{
    use WithAuthentication;

    public function test_heartbeat_requires_authentication()
    {
        $this->getJson( 'api/pos/heartbeat' )->assertStatus( 401 );
    }

    public function test_heartbeat_returns_server_time()
    {
        $this->attemptAuthenticate();

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->getJson( 'api/pos/heartbeat' );

        $response->assertOk();
        $response->assertJsonStructure( [ 'status', 'time', 'timestamp' ] );
        $this->assertIsInt( $response->json( 'timestamp' ) );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
php artisan test tests/Feature/PosHeartbeatTest.php
```
Expected: FAIL — 404 for the route.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Dashboard/PosController.php`:
```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\DashboardController;

class PosController extends DashboardController
{
    public function heartbeat()
    {
        $now = ns()->date->getNow();

        return [
            'status' => 'success',
            'time' => $now->toDateTimeString(),
            'timestamp' => $now->getTimestamp() * 1000,
        ];
    }
}
```

- [ ] **Step 4: Create the routes file**

Create `routes/api/pos.php`:
```php
<?php

use App\Http\Controllers\Dashboard\PosController;
use Illuminate\Support\Facades\Route;

Route::get( 'pos/heartbeat', [ PosController::class, 'heartbeat' ] );
```

- [ ] **Step 5: Register the routes file**

In `routes/api-base.php`, inside the `auth:sanctum` group, add after the `api/orders.php` include:
```php
include dirname( __FILE__ ) . '/api/pos.php';
```

- [ ] **Step 6: Run the test to verify it passes**

Run:
```bash
php artisan test tests/Feature/PosHeartbeatTest.php
```
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add routes/api/pos.php routes/api-base.php app/Http/Controllers/Dashboard/PosController.php tests/Feature/PosHeartbeatTest.php
git commit -m "feat: add POS heartbeat endpoint"
```

---

## Task 4: Offline snapshot endpoint

**Files:**
- Modify: `routes/api/pos.php`
- Modify: `app/Http/Controllers/Dashboard/PosController.php`
- Test: `tests/Feature/PosOfflineSnapshotTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PosOfflineSnapshotTest.php`:
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\WithAuthentication;

class PosOfflineSnapshotTest extends TestCase
{
    use WithAuthentication;

    public function test_snapshot_requires_authentication()
    {
        $this->getJson( 'api/pos/offline-snapshot' )->assertStatus( 401 );
    }

    public function test_snapshot_returns_catalog_payload()
    {
        $this->attemptAuthenticate();

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->getJson( 'api/pos/offline-snapshot' );

        $response->assertOk();
        $response->assertJsonStructure( [
            'generated_at',
            'server_time',
            'products',
            'categories',
            'payment_types',
            'customers',
            'register',
            'options',
        ] );

        $products = collect( $response->json( 'products' ) );
        $this->assertNotEmpty( $products );
        $this->assertTrue(
            $products->contains( fn( $product ) => ! empty( $product[ 'unit_quantities' ] ) )
        );

        $productWithUnits = $products->first( fn( $product ) => ! empty( $product[ 'unit_quantities' ] ) );
        $this->assertArrayHasKey( 'sale_price', $productWithUnits[ 'unit_quantities' ][ 0 ] );
        $this->assertArrayHasKey( 'unit', $productWithUnits[ 'unit_quantities' ][ 0 ] );
        $this->assertArrayHasKey( 'barcode', $productWithUnits[ 'unit_quantities' ][ 0 ] );
        $this->assertArrayHasKey( 'barcode', $productWithUnits );
        $this->assertArrayHasKey( 'category_id', $productWithUnits );
        $this->assertArrayHasKey( 'pinned', $productWithUnits );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
php artisan test tests/Feature/PosOfflineSnapshotTest.php
```
Expected: FAIL — 404 for the route.

- [ ] **Step 3: Implement the snapshot method**

Add to `app/Http/Controllers/Dashboard/PosController.php` — replace the whole file with:
```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\DashboardController;
use App\Models\Customer;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Register;

class PosController extends DashboardController
{
    public function heartbeat()
    {
        $now = ns()->date->getNow();

        return [
            'status' => 'success',
            'time' => $now->toDateTimeString(),
            'timestamp' => $now->getTimestamp() * 1000,
        ];
    }

    public function offlineSnapshot()
    {
        $enableReordering = ns()->option->get( 'ns_pos_enable_reordering' ) === 'yes';

        $products = Product::with( [
            'unit_quantities' => function ( $query ) {
                $query->where( 'visible', true )->with( 'unit' );
            },
            'category',
            'tax_group.taxes',
            'galleries',
        ] )
            ->onSale()
            ->where( function ( $query ) {
                $this->applyHideProducts( $query );
            } )
            ->trackingDisabled()
            ->get()
            ->map( function ( Product $product ) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'type' => $product->type,
                    'category_id' => $product->category_id,
                    'pinned' => (bool) $product->pinned,
                    'tax_group_id' => $product->tax_group_id,
                    'tax_type' => $product->tax_type,
                    'position' => $product->position,
                    'created_at' => $product->created_at,
                    'tax_group' => [
                        'id' => $product->tax_group?->id,
                        'name' => $product->tax_group?->name,
                        'taxes' => $product->tax_group
                            ? $product->tax_group->taxes->map( fn( $tax ) => [
                                'id' => $tax->id,
                                'name' => $tax->name,
                                'rate' => (float) $tax->rate,
                            ] )->values()
                            : [],
                    ],
                    'unit_quantities' => $product->unit_quantities->map( function ( $unitQuantity ) {
                        return [
                            'id' => $unitQuantity->id,
                            'unit_id' => $unitQuantity->unit_id,
                            'unit_name' => $unitQuantity->unit?->name,
                            'barcode' => $unitQuantity->barcode,
                            'unit' => $unitQuantity->unit ? [
                                'id' => $unitQuantity->unit->id,
                                'name' => $unitQuantity->unit->name,
                            ] : null,
                            'sale_price' => (float) $unitQuantity->sale_price,
                            'sale_price_net' => (float) $unitQuantity->sale_price_net,
                            'sale_price_gross' => (float) $unitQuantity->sale_price_gross,
                            'quantity' => (float) $unitQuantity->quantity,
                        ];
                    } )->values(),
                    'galleries' => $product->galleries->map( fn( $gallery ) => [
                        'id' => $gallery->id,
                        'url' => $gallery->url,
                        'featured' => (bool) $gallery->featured,
                    ] )->values(),
                ];
            } );

        $categories = ProductCategory::where( function ( $query ) {
            $this->applyHideCategories( $query );
        } )
            ->displayOnPOS()
            ->when(
                $enableReordering,
                fn( $query ) => $query->orderBy( 'position', 'asc' ),
                fn( $query ) => $query->orderBy( 'created_at', 'asc' )
            )
            ->get()
            ->map( fn( ProductCategory $category ) => [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'preview' => $category->preview,
                'position' => $category->position,
                'created_at' => $category->created_at,
            ] );

        return [
            'generated_at' => ns()->date->getNow()->toDateTimeString(),
            'server_time' => ns()->date->getNow()->getTimestamp() * 1000,
            'products' => $products,
            'categories' => $categories,
            'payment_types' => PaymentType::active()->get( [ 'id', 'label', 'identifier' ] ),
            'customers' => Customer::get( [ 'id', 'first_name', 'last_name', 'phone', 'group_id' ] ),
            'register' => [
                'open_register_id' => Register::opened()->first()?->id,
            ],
            'options' => $this->offlineOptions(),
        ];
    }

    /**
     * The POS options needed while offline. Kept as an explicit list
     * so the snapshot stays small (some options hold large payloads).
     */
    private function offlineOptions(): array
    {
        $keys = [
            'ns_pos_enable_reordering',
            'ns_pos_hide_exhausted_products',
            'ns_pos_hide_empty_categories',
            'ns_pos_force_autofocus',
            'ns_pos_show_quantity',
            'ns_pos_complete_sale_audio',
            'ns_pos_printing_gateway',
            'ns_pos_registers_enabled',
            'ns_orders_allow_partial',
            'ns_orders_allow_incomplete',
        ];

        return collect( $keys )
            ->mapWithKeys( fn( $key ) => [ $key => ns()->option->get( $key ) ] )
            ->toArray();
    }

    private function applyHideProducts( $query )
    {
        $exhaustedHidden = ns()->option->get( 'ns_pos_hide_exhausted_products' );

        if ( $exhaustedHidden === 'yes' ) {
            $query->where( 'stock_management', Product::STOCK_MANAGEMENT_DISABLED );

            $query->orWhere( function ( $query ) {
                $query->where( 'stock_management', Product::STOCK_MANAGEMENT_ENABLED );

                $query->whereHas( 'unit_quantities', function ( $query ) {
                    $query->where( 'quantity', '>', 0 );
                } );
            } );
        }
    }

    private function applyHideCategories( $query )
    {
        $exhaustedHidden = ns()->option->get( 'ns_pos_hide_empty_categories' );

        if ( $exhaustedHidden === 'yes' ) {
            $query->whereHas( 'products', function ( $query ) {
                $query->where( 'stock_management', Product::STOCK_MANAGEMENT_DISABLED );
            } );

            $query->orWhereHas( 'products', function ( $query ) {
                $query->where( 'stock_management', Product::STOCK_MANAGEMENT_ENABLED );

                $query->whereHas( 'unit_quantities', function ( $query ) {
                    $query->where( 'quantity', '>', 0 );
                } );
            } );
        }
    }
}
```
Note: the two `applyHide*` methods intentionally mirror `CategoryController` (`app/Http/Controllers/Dashboard/CategoryController.php:371-405`) so offline listings match the online POS. Extracting them into a shared service is a follow-up, not part of Phase 1.

- [ ] **Step 4: Add the route with the products permission**

In `routes/api/pos.php`, add:
```php
use App\Http\Middleware\NsRestrictMiddleware;

Route::get( 'pos/offline-snapshot', [ PosController::class, 'offlineSnapshot' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'nexopos.read.products' ) );
```
The full file becomes:
```php
<?php

use App\Http\Controllers\Dashboard\PosController;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::get( 'pos/heartbeat', [ PosController::class, 'heartbeat' ] );
Route::get( 'pos/offline-snapshot', [ PosController::class, 'offlineSnapshot' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'nexopos.read.products' ) );
```

- [ ] **Step 5: Run the test to verify it passes**

Run:
```bash
php artisan test tests/Feature/PosOfflineSnapshotTest.php
```
Expected: PASS (2 tests).

- [ ] **Step 6: Run the neighbouring suite to check for regressions**

Run:
```bash
php artisan test tests/Feature/PosHeartbeatTest.php tests/Feature/CreateOrderTest.php
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/api/pos.php app/Http/Controllers/Dashboard/PosController.php tests/Feature/PosOfflineSnapshotTest.php
git commit -m "feat: add POS offline snapshot endpoint"
```

---

## Task 5: Offline catalog pure logic

**Files:**
- Create: `resources/ts/libraries/offline/offline-catalog.ts`
- Test: `ts-tests/offline-catalog.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `ts-tests/offline-catalog.test.ts`:
```ts
import { describe, expect, test } from '@jest/globals';
import {
    buildCategoryView,
    findByBarcode,
    searchProducts,
} from '../resources/ts/libraries/offline/offline-catalog';

const categories = [
    { id: 1, name: 'Root A', parent_id: null, position: 2, created_at: '2026-01-01' },
    { id: 2, name: 'Root B', parent_id: null, position: 1, created_at: '2026-01-02' },
    { id: 3, name: 'Child A1', parent_id: 1, position: 1, created_at: '2026-01-03' },
];

const products = [
    { id: 10, name: 'Biogesic', barcode: '111', category_id: 1, pinned: true, position: 2, created_at: '2026-01-01', unit_quantities: [{ id: 100, unit_id: 1, sale_price: 5, quantity: 10 }] },
    { id: 11, name: 'Alaxan', barcode: '222', category_id: 1, pinned: false, position: 1, created_at: '2026-01-02', unit_quantities: [{ id: 101, unit_id: 1, sale_price: 7, quantity: 3 }] },
    { id: 12, name: 'Skelan', barcode: '333', category_id: 3, pinned: false, position: 1, created_at: '2026-01-03', unit_quantities: [{ id: 102, unit_id: 1, sale_price: 9, quantity: 0 }] },
];

describe( 'Offline catalog', () => {
    test( 'root view returns top-level categories, pinned products and no products', () => {
        const view = buildCategoryView( products as any, categories as any, null, false );

        expect( view.products ).toEqual( [] );
        expect( view.categories.map( ( category ) => category.id ) ).toEqual( [ 1, 2 ] );
        expect( view.pinnedProducts.map( ( product ) => product.id ) ).toEqual( [ 10 ] );
        expect( view.currentCategory ).toBe( false );
        expect( view.previousCategory ).toBe( false );
    } );

    test( 'category view returns children, products and previous category', () => {
        const view = buildCategoryView( products as any, categories as any, 1, false );

        expect( view.categories.map( ( category ) => category.id ) ).toEqual( [ 3 ] );
        expect( view.products.map( ( product ) => product.id ) ).toEqual( [ 10, 11 ] );
        expect( view.currentCategory?.id ).toBe( 1 );
        expect( view.previousCategory ).toBe( false );
    } );

    test( 'reordering option sorts by position', () => {
        const view = buildCategoryView( products as any, categories as any, 1, true );

        expect( view.products.map( ( product ) => product.id ) ).toEqual( [ 11, 10 ] );
    } );

    test( 'search is case insensitive and limited', () => {
        expect( searchProducts( products as any, 'biog', 20 ).map( ( product ) => product.id ) ).toEqual( [ 10 ] );

        const limited = searchProducts( products as any, 'a', 1 );
        expect( limited.length ).toBe( 1 );
    } );

    test( 'barcode lookup returns the product or false', () => {
        expect( findByBarcode( products as any, '222' )?.id ).toBe( 11 );
        expect( findByBarcode( products as any, '999' ) ).toBe( false );
    } );

    test( 'barcode lookup prefers unit-level barcodes', () => {
        const withUnitBarcode = [
            {
                ...products[ 0 ],
                barcode: '999',
                unit_quantities: [
                    { id: 100, unit_id: 1, barcode: '444', sale_price: 5, quantity: 10 },
                ],
            },
        ] as any;

        expect( findByBarcode( withUnitBarcode, '444' )?.selectedUnitQuantity?.id ).toBe( 100 );
        expect( findByBarcode( withUnitBarcode, '999' )?.selectedUnitQuantity?.id ).toBe( 100 );
    } );
} );
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```bash
npx jest ts-tests/offline-catalog.test.ts
```
Expected: FAIL — cannot find module `offline-catalog`.

- [ ] **Step 3: Implement the module**

Create `resources/ts/libraries/offline/offline-catalog.ts`:
```ts
export interface OfflineUnitQuantity {
    id: number;
    unit_id: number;
    unit_name?: string;
    barcode?: string | null;
    unit?: { id: number; name: string } | null;
    sale_price: number;
    sale_price_net?: number;
    sale_price_gross?: number;
    quantity: number;
}

export interface OfflineProduct {
    id: number;
    name: string;
    sku?: string;
    barcode?: string;
    type?: string;
    category_id?: number;
    pinned?: boolean;
    position?: number;
    created_at?: string;
    tax_group_id?: number;
    tax_type?: string;
    tax_group?: any;
    selectedUnitQuantity?: OfflineUnitQuantity;
    unit_quantities: OfflineUnitQuantity[];
    galleries?: { id: number; url: string; featured?: boolean }[];
}

export interface OfflineCategory {
    id: number;
    name: string;
    parent_id?: number | null;
    preview?: string;
    position?: number;
    created_at?: string;
}

export interface OfflineCategoryView {
    categories: OfflineCategory[];
    products: OfflineProduct[];
    pinnedProducts: OfflineProduct[];
    previousCategory: OfflineCategory | false;
    currentCategory: OfflineCategory | false;
}

function sortCollection<T extends { position?: number; created_at?: string }>(
    items: T[],
    enableReordering: boolean,
): T[] {
    return [ ...items ].sort( ( first, second ) => {
        if ( enableReordering ) {
            return ( first.position ?? 0 ) - ( second.position ?? 0 );
        }

        return `${first.created_at ?? ''}`.localeCompare( `${second.created_at ?? ''}` );
    } );
}

export function buildCategoryView(
    products: OfflineProduct[],
    categories: OfflineCategory[],
    parentId: number | null = null,
    enableReordering = false,
): OfflineCategoryView {
    const currentCategory = parentId
        ? categories.find( ( category ) => Number( category.id ) === Number( parentId ) ) ?? false
        : false;

    const children = categories.filter(
        ( category ) => Number( category.parent_id ?? 0 ) === Number( parentId ?? 0 ),
    );

    const productsInCategory = parentId
        ? products.filter( ( product ) => Number( product.category_id ) === Number( parentId ) )
        : [];

    return {
        categories: sortCollection( children, enableReordering ),
        products: sortCollection( productsInCategory, enableReordering ),
        pinnedProducts: products.filter( ( product ) => product.pinned ),
        previousCategory: currentCategory
            ? categories.find(
                  ( category ) => Number( category.id ) === Number( currentCategory.parent_id ?? 0 ),
              ) ?? false
            : false,
        currentCategory,
    };
}

export function searchProducts(
    products: OfflineProduct[],
    term: string,
    limit = 20,
): OfflineProduct[] {
    const needle = term.trim().toLowerCase();

    if ( needle.length === 0 ) {
        return [];
    }

    return products
        .filter( ( product ) => {
            return (
                `${product.name ?? ''}`.toLowerCase().includes( needle ) ||
                `${product.sku ?? ''}`.toLowerCase().includes( needle ) ||
                `${product.barcode ?? ''}`.toLowerCase().includes( needle )
            );
        } )
        .slice( 0, limit );
}

export function findByBarcode(
    products: OfflineProduct[],
    barcode: string,
): OfflineProduct | false {
    const needle = barcode.trim();

    if ( needle.length === 0 ) {
        return false;
    }

    /**
     * Mirrors the online endpoint: unit-level barcodes take
     * precedence over the product-level barcode.
     */
    for ( const product of products ) {
        const matchingUnit = ( product.unit_quantities ?? [] ).find(
            ( unitQuantity ) => `${unitQuantity.barcode ?? ''}`.trim() === needle,
        );

        if ( matchingUnit ) {
            return { ...product, selectedUnitQuantity: matchingUnit };
        }

        if ( `${product.barcode ?? ''}`.trim() === needle ) {
            return { ...product, selectedUnitQuantity: product.unit_quantities?.[ 0 ] };
        }
    }

    return false;
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:
```bash
npx jest ts-tests/offline-catalog.test.ts
```
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/ts/libraries/offline/offline-catalog.ts ts-tests/offline-catalog.test.ts
git commit -m "feat: add offline catalog search and category logic"
```

---

## Task 6: IndexedDB store

**Files:**
- Create: `resources/ts/libraries/offline/offline-db.ts`
- Test: `ts-tests/offline-db.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `ts-tests/offline-db.test.ts`:
```ts
import 'fake-indexeddb/auto';
import { describe, expect, test } from '@jest/globals';
import { OfflineDatabase } from '../resources/ts/libraries/offline/offline-db';

describe( 'Offline database', () => {
    test( 'stores and replaces collections', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}` );
        await database.open();

        await database.replaceCollection( 'products', [
            { id: 1, name: 'Product A' },
            { id: 2, name: 'Product B' },
        ] );

        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 2 );

        await database.replaceCollection( 'products', [ { id: 2, name: 'Product B' } ] );
        const products = await database.getCollection( 'products' );

        expect( products.length ).toBe( 1 );
        expect( products[ 0 ].id ).toBe( 2 );
    } );

    test( 'stores metadata and counts records', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-meta` );
        await database.open();

        await database.setMeta( 'snapshot', { generated_at: '2026-09-18', offset: 1000 } );

        const meta = await database.getMeta( 'snapshot' );

        expect( meta.offset ).toBe( 1000 );
        expect( await database.count( 'products' ) ).toBe( 0 );
    } );
} );
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```bash
npx jest ts-tests/offline-db.test.ts
```
Expected: FAIL — cannot find module `offline-db`.

- [ ] **Step 3: Implement the database wrapper**

Create `resources/ts/libraries/offline/offline-db.ts`:
```ts
const DEFAULT_DB_NAME = "drcare-offline";
const DB_VERSION = 1;

type StoreName = "products" | "categories" | "customers" | "meta" | "outbox" | "counters";

interface StoreDefinition {
    name: StoreName;
    keyPath: string;
}

const STORES: StoreDefinition[] = [
    { name: "products", keyPath: "id" },
    { name: "categories", keyPath: "id" },
    { name: "customers", keyPath: "id" },
    { name: "meta", keyPath: "key" },
    { name: "outbox", keyPath: "uuid" },
    { name: "counters", keyPath: "key" },
];

export class OfflineDatabase {
    private connection: IDBDatabase | null = null;

    constructor( private name: string = DEFAULT_DB_NAME ) {}

    open(): Promise<void> {
        if ( this.connection ) {
            return Promise.resolve();
        }

        return new Promise( ( resolve, reject ) => {
            const request = indexedDB.open( this.name, DB_VERSION );

            request.onupgradeneeded = () => {
                const database = request.result;

                for ( const store of STORES ) {
                    if ( ! database.objectStoreNames.contains( store.name ) ) {
                        database.createObjectStore( store.name, { keyPath: store.keyPath } );
                    }
                }
            };

            request.onsuccess = () => {
                this.connection = request.result;
                resolve();
            };

            request.onerror = () => reject( request.error );
        } );
    }

    async replaceCollection( store: StoreName, items: any[] ): Promise<void> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( store, "readwrite" );
            const objectStore = transaction.objectStore( store );

            objectStore.clear();

            for ( const item of items ) {
                objectStore.put( item );
            }

            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject( transaction.error );
        } );
    }

    async getCollection<T = any>( store: StoreName ): Promise<T[]> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( store, "readonly" );
            const request = transaction.objectStore( store ).getAll();

            request.onsuccess = () => resolve( request.result as T[] );
            request.onerror = () => reject( request.error );
        } );
    }

    async count( store: StoreName ): Promise<number> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( store, "readonly" );
            const request = transaction.objectStore( store ).count();

            request.onsuccess = () => resolve( request.result );
            request.onerror = () => reject( request.error );
        } );
    }

    async setMeta( key: string, value: any ): Promise<void> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( "meta", "readwrite" );
            transaction.objectStore( "meta" ).put( { key, value } );

            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject( transaction.error );
        } );
    }

    async getMeta<T = any>( key: string ): Promise<T | null> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( "meta", "readonly" );
            const request = transaction.objectStore( "meta" ).get( key );

            request.onsuccess = () => resolve( ( request.result?.value ?? null ) as T | null );
            request.onerror = () => reject( request.error );
        } );
    }

    private database(): IDBDatabase {
        if ( ! this.connection ) {
            throw new Error( "The offline database has not been opened." );
        }

        return this.connection;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:
```bash
npx jest ts-tests/offline-db.test.ts
```
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/ts/libraries/offline/offline-db.ts ts-tests/offline-db.test.ts
git commit -m "feat: add IndexedDB offline store"
```

---

## Task 7: Connectivity monitor

**Files:**
- Create: `resources/ts/libraries/offline/offline-connectivity.ts`
- Test: `ts-tests/offline-connectivity.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `ts-tests/offline-connectivity.test.ts`:
```ts
import { describe, expect, test, jest } from '@jest/globals';
import { OfflineConnectivity } from '../resources/ts/libraries/offline/offline-connectivity';

describe( 'Offline connectivity', () => {
    test( 'goes offline after the configured number of failures', async () => {
        const ping = jest.fn( async () => false );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 2 } );

        expect( connectivity.status ).toBe( 'online' );

        await connectivity.checkNow();
        expect( connectivity.status ).toBe( 'online' );

        await connectivity.checkNow();
        expect( connectivity.status ).toBe( 'offline' );
    } );

    test( 'recovers to online after a successful ping', async () => {
        const ping = jest.fn( async () => false );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 1 } );

        await connectivity.checkNow();
        expect( connectivity.status ).toBe( 'offline' );

        ping.mockImplementation( async () => true );
        await connectivity.checkNow();
        expect( connectivity.status ).toBe( 'online' );
    } );

    test( 'emits status changes to subscribers', async () => {
        const ping = jest.fn( async () => false );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 1 } );
        const statuses: string[] = [];

        const subscription = connectivity.status$.subscribe( ( status ) => statuses.push( status ) );
        await connectivity.checkNow();
        subscription.unsubscribe();

        expect( statuses ).toEqual( [ 'online', 'offline' ] );
    } );

    test( 'treats a throwing ping as a failure', async () => {
        const ping = jest.fn( async () => {
            throw new Error( 'network down' );
        } );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 1 } );

        await connectivity.checkNow();

        expect( connectivity.status ).toBe( 'offline' );
    } );
} );
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```bash
npx jest ts-tests/offline-connectivity.test.ts
```
Expected: FAIL — cannot find module `offline-connectivity`.

- [ ] **Step 3: Implement the monitor**

Create `resources/ts/libraries/offline/offline-connectivity.ts`:
```ts
import { BehaviorSubject, Observable } from "rxjs";

export type ConnectivityStatus = "online" | "offline";

export interface ConnectivityOptions {
    intervalMs?: number;
    timeoutMs?: number;
    failuresToOffline?: number;
}

/**
 * Watches for server availability using an injected ping.
 * Two consecutive failures (configurable) mark the POS as offline;
 * a single success brings it back online.
 */
export class OfflineConnectivity {
    private failures = 0;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private stopped = false;
    private statusSubject = new BehaviorSubject<ConnectivityStatus>( "online" );

    constructor(
        private ping: ( timeoutMs: number ) => Promise<boolean>,
        private options: ConnectivityOptions = {},
    ) {}

    get status$(): Observable<ConnectivityStatus> {
        return this.statusSubject.asObservable();
    }

    get status(): ConnectivityStatus {
        return this.statusSubject.getValue();
    }

    start(): void {
        this.stopped = false;
        this.scheduleNext( 0 );
    }

    stop(): void {
        this.stopped = true;

        if ( this.timer ) {
            clearTimeout( this.timer );
            this.timer = null;
        }
    }

    async checkNow(): Promise<ConnectivityStatus> {
        const reachable = await this.safePing();

        if ( reachable ) {
            this.failures = 0;
            this.emit( "online" );
        } else {
            this.failures++;

            if ( this.failures >= ( this.options.failuresToOffline ?? 2 ) ) {
                this.emit( "offline" );
            }
        }

        return this.status;
    }

    private async safePing(): Promise<boolean> {
        try {
            return await this.ping( this.options.timeoutMs ?? 5000 );
        } catch ( exception ) {
            return false;
        }
    }

    private emit( status: ConnectivityStatus ): void {
        if ( this.statusSubject.getValue() !== status ) {
            this.statusSubject.next( status );
        }
    }

    private scheduleNext( delay: number ): void {
        if ( this.stopped ) {
            return;
        }

        this.timer = setTimeout( async () => {
            await this.checkNow();
            this.scheduleNext( this.options.intervalMs ?? 20000 );
        }, delay );
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:
```bash
npx jest ts-tests/offline-connectivity.test.ts
```
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/ts/libraries/offline/offline-connectivity.ts ts-tests/offline-connectivity.test.ts
git commit -m "feat: add offline connectivity monitor"
```

---

## Task 8: Snapshot sync service

**Files:**
- Create: `resources/ts/libraries/offline/offline-snapshot.ts`
- Test: `ts-tests/offline-snapshot.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `ts-tests/offline-snapshot.test.ts`:
```ts
import 'fake-indexeddb/auto';
import { describe, expect, test, jest } from '@jest/globals';
import { OfflineDatabase } from '../resources/ts/libraries/offline/offline-db';
import { OfflineSnapshotService } from '../resources/ts/libraries/offline/offline-snapshot';

function buildSnapshot() {
    return {
        generated_at: '2026-09-18 10:00:00',
        server_time: Date.now() + 5000,
        products: [ { id: 1, name: 'Product A', unit_quantities: [] } ],
        categories: [ { id: 1, name: 'Category A', parent_id: null } ],
        customers: [ { id: 1, first_name: 'Walk-in', last_name: 'Customer' } ],
        payment_types: [ { id: 1, label: 'Cash', identifier: 'cash-payment' } ],
        register: { open_register_id: 3 },
        options: { ns_pos_enable_reordering: 'yes' },
    };
}

describe( 'Offline snapshot service', () => {
    test( 'persists the snapshot collections and metadata', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-snapshot` );
        await database.open();
        const fetcher = jest.fn( async () => buildSnapshot() );
        const service = new OfflineSnapshotService( database, fetcher );

        const refreshed = await service.refresh();

        expect( refreshed ).toBe( true );
        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 1 );
        expect( ( await database.getCollection( 'categories' ) ).length ).toBe( 1 );

        const meta = await database.getMeta( 'snapshot' );

        expect( meta.generated_at ).toBe( '2026-09-18 10:00:00' );
        expect( typeof meta.offset ).toBe( 'number' );

        const options = await database.getMeta( 'options' );

        expect( options.ns_pos_enable_reordering ).toBe( 'yes' );
    } );

    test( 'keeps the previous data when the refresh fails', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-failure` );
        await database.open();
        const fetcher = jest.fn( async () => {
            throw new Error( 'offline' );
        } );
        const service = new OfflineSnapshotService( database, fetcher );

        const refreshed = await service.refresh();

        expect( refreshed ).toBe( false );
        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 0 );
    } );
} );
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```bash
npx jest ts-tests/offline-snapshot.test.ts
```
Expected: FAIL — cannot find module `offline-snapshot`.

- [ ] **Step 3: Implement the service**

Create `resources/ts/libraries/offline/offline-snapshot.ts`:
```ts
import { OfflineDatabase } from "./offline-db";

export interface SnapshotResponse {
    generated_at: string;
    server_time: number;
    products: any[];
    categories: any[];
    customers: any[];
    payment_types: any[];
    register: { open_register_id: number | null };
    options: Record<string, any>;
}

export class OfflineSnapshotService {
    private timer: ReturnType<typeof setInterval> | null = null;

    constructor(
        private database: OfflineDatabase,
        private fetcher: () => Promise<SnapshotResponse>,
        private intervalMs = 15 * 60 * 1000,
    ) {}

    async refresh(): Promise<boolean> {
        try {
            const snapshot = await this.fetcher();

            await this.database.replaceCollection( "products", snapshot.products );
            await this.database.replaceCollection( "categories", snapshot.categories );
            await this.database.replaceCollection( "customers", snapshot.customers );
            await this.database.setMeta( "payment_types", snapshot.payment_types );
            await this.database.setMeta( "register", snapshot.register );
            await this.database.setMeta( "options", snapshot.options );
            await this.database.setMeta( "snapshot", {
                generated_at: snapshot.generated_at,
                fetched_at: Date.now(),
                server_time: snapshot.server_time,
                offset: snapshot.server_time - Date.now(),
            } );

            return true;
        } catch ( exception ) {
            console.warn( "Unable to refresh the offline snapshot", exception );
            return false;
        }
    }

    start(): void {
        this.refresh();

        if ( this.timer ) {
            clearInterval( this.timer );
        }

        this.timer = setInterval( () => this.refresh(), this.intervalMs );
    }

    stop(): void {
        if ( this.timer ) {
            clearInterval( this.timer );
            this.timer = null;
        }
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:
```bash
npx jest ts-tests/offline-snapshot.test.ts
```
Expected: PASS (2 tests).

- [ ] **Step 5: Run all TS unit tests**

Run:
```bash
npx jest
```
Expected: all suites PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/ts/libraries/offline/offline-snapshot.ts ts-tests/offline-snapshot.test.ts
git commit -m "feat: add offline snapshot sync service"
```

---

## Task 9: Offline singleton, banner and grid fallback

**Files:**
- Create: `resources/ts/libraries/offline/offline.ts`
- Create: `resources/ts/pages/dashboard/pos/ns-pos-offline-banner.vue`
- Modify: `resources/ts/pages/dashboard/pos/ns-pos.vue`
- Modify: `resources/ts/pages/dashboard/pos/ns-pos-grid.vue`

- [ ] **Step 1: Create the singleton wiring**

Create `resources/ts/libraries/offline/offline.ts`:
```ts
import { nsHttpClient } from "~/bootstrap";
import { OfflineDatabase } from "./offline-db";
import {
    buildCategoryView,
    findByBarcode,
    searchProducts,
    type OfflineProduct,
} from "./offline-catalog";
import { OfflineConnectivity } from "./offline-connectivity";
import { OfflineSnapshotService, type SnapshotResponse } from "./offline-snapshot";

class OfflineCatalog {
    constructor( private database: OfflineDatabase ) {}

    async getCategoryView( parentId: number | null, enableReordering = false ) {
        const products = await this.database.getCollection<OfflineProduct>( "products" );
        const categories = await this.database.getCollection( "categories" );

        return buildCategoryView( products, categories, parentId, enableReordering );
    }

    async search( term: string, limit = 20 ) {
        const products = await this.database.getCollection<OfflineProduct>( "products" );

        return searchProducts( products, term, limit );
    }

    async findByBarcode( barcode: string ) {
        const products = await this.database.getCollection<OfflineProduct>( "products" );

        return findByBarcode( products, barcode );
    }
}

export interface OfflineContext {
    database: OfflineDatabase;
    snapshot: OfflineSnapshotService;
    connectivity: OfflineConnectivity;
    catalog: OfflineCatalog;
}

let context: OfflineContext | null = null;

function heartbeat( timeoutMs: number ): Promise<boolean> {
    return new Promise( ( resolve ) => {
        const timer = setTimeout( () => resolve( false ), timeoutMs );

        nsHttpClient.get( "/api/pos/heartbeat" ).subscribe( {
            next: () => {
                clearTimeout( timer );
                resolve( true );
            },
            error: () => {
                clearTimeout( timer );
                resolve( false );
            },
        } );
    } );
}

function fetchSnapshot(): Promise<SnapshotResponse> {
    return new Promise( ( resolve, reject ) => {
        nsHttpClient.get( "/api/pos/offline-snapshot" ).subscribe( {
            next: ( snapshot: SnapshotResponse ) => resolve( snapshot ),
            error: ( error: any ) => reject( error ),
        } );
    } );
}

export async function initializeOfflineMode(): Promise<OfflineContext> {
    if ( context ) {
        return context;
    }

    const database = new OfflineDatabase();

    await database.open();

    const connectivity = new OfflineConnectivity( heartbeat );
    const snapshot = new OfflineSnapshotService( database, fetchSnapshot );
    const catalog = new OfflineCatalog( database );

    context = { database, snapshot, connectivity, catalog };

    connectivity.start();
    snapshot.start();

    window.addEventListener( "offline", () => connectivity.checkNow() );
    window.addEventListener( "online", () => connectivity.checkNow() );

    return context;
}

export function getOfflineContext(): OfflineContext | null {
    return context;
}
```

- [ ] **Step 2: Create the banner component**

Create `resources/ts/pages/dashboard/pos/ns-pos-offline-banner.vue`:
```vue
<script lang="ts">
import { __ } from "~/libraries/lang";
import { getOfflineContext } from "~/libraries/offline/offline";

export default {
    name: "ns-pos-offline-banner",
    data() {
        return {
            status: "online",
            snapshotAge: null,
            subscription: null,
        };
    },
    mounted() {
        const context = getOfflineContext();

        if ( ! context ) {
            return;
        }

        this.subscription = context.connectivity.status$.subscribe( ( status ) => {
            this.status = status;

            if ( status === "offline" ) {
                this.loadSnapshotAge( context );
            }
        } );
    },
    unmounted() {
        this.subscription?.unsubscribe();
    },
    methods: {
        __,
        async loadSnapshotAge( context ) {
            const meta = await context.database.getMeta( "snapshot" );

            if ( meta?.fetched_at ) {
                this.snapshotAge = Math.round( ( Date.now() - meta.fetched_at ) / 60000 );
            }
        },
    },
};
</script>
<template>
    <div
        v-if="status === 'offline'"
        id="ns-pos-offline-banner"
        class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-red-600"
    >
        <i class="las la-unlink"></i>
        <span>{{ __( "OFFLINE MODE" ) }} —</span>
        <span>{{ __( "You can browse the cached catalog; sales are disabled until the connection is back." ) }}</span>
        <span v-if="snapshotAge !== null">
            ({{ __( "catalog cached" ) }} {{ snapshotAge }} min ago)
        </span>
    </div>
</template>
```

- [ ] **Step 3: Mount the banner and initialize offline mode**

In `resources/ts/pages/dashboard/pos/ns-pos.vue`:

Add to the template, right after the opening `#pos-container` div:
```html
<ns-pos-offline-banner></ns-pos-offline-banner>
```
Replace the `<script>` imports with:
```js
import nsPosCart from './ns-pos-cart.vue';
import nsPosGrid from './ns-pos-grid.vue';
import nsPosTransactionSummary from './ns-pos-transaction-summary.vue';
import nsPosOfflineBanner from './ns-pos-offline-banner.vue';
import { initializeOfflineMode } from '~/libraries/offline/offline';
```
Add `nsPosOfflineBanner` to `components`. Add to the top of `mounted()`:
```js
initializeOfflineMode().catch( ( exception ) => {
    console.warn( 'Unable to initialize offline mode', exception );
} );
```

- [ ] **Step 4: Add the grid fallbacks**

In `resources/ts/pages/dashboard/pos/ns-pos-grid.vue`:

Add the import near the other imports:
```ts
import { getOfflineContext } from "~/libraries/offline/offline";
```

Change the signature from `loadCategories(parent)` to `async loadCategories(parent)` and replace the beginning of the method:
```ts
async loadCategories(parent) {
    // Clear any leftover search text when navigating categories,
    // so the grid always reflects the category view.
    this.barcode = "";
    this.isLoading = true;

    const offline = getOfflineContext();

    if (offline && offline.connectivity.status === "offline") {
        const options = (await offline.database.getMeta("options")) ?? {};
        const view = await offline.catalog.getCategoryView(
            parent ? parent.id : null,
            options.ns_pos_enable_reordering === "yes",
        );

        this.categories = view.categories;
        this.products = view.products;
        this.pinnedProducts = view.pinnedProducts;
        this.previousCategory = view.previousCategory;
        this.currentCategory = view.currentCategory;
        this.updateBreadCrumb(this.currentCategory);
        this.isLoading = false;
        return;
    }

    nsHttpClient
        .get(`/api/categories/pos/${parent ? parent.id : ""}`)
        /* ... existing subscribe stays unchanged ... */
```
Note: `loadCategories` is called from the template (`@click="loadCategories()"`); no other caller changes.

Change the signature from `submitSearch(value)` to `async submitSearch(value)` and replace the scan branch:
```ts
if (this.scanMode) {
    const offline = getOfflineContext();

    if (offline && offline.connectivity.status === "offline") {
        const product = await offline.catalog.findByBarcode(value);

        this.barcode = "";

        if (product) {
            /**
             * addScannedProductToCart expects the barcode endpoint
             * shape: { product, unitQuantity, unit }.
             * findByBarcode sets selectedUnitQuantity to the unit
             * whose barcode matched (or the first unit otherwise).
             */
            const unitQuantity =
                product.selectedUnitQuantity ?? product.unit_quantities?.[0];

            this.addScannedProductToCart({
                product: { ...product, selectedUnitQuantity: unitQuantity },
                unitQuantity,
                unit: unitQuantity?.unit,
            });
        } else {
            nsSnackBar.error(__("Product not found in the offline catalog."));
        }

        return;
    }

    // Barcode mode — single product lookup
    const url = nsHooks.applyFilters( ... existing code ... );
    ...
```
And the search branch:
```ts
} else {
    const offline = getOfflineContext();

    if (offline && offline.connectivity.status === "offline") {
        const results = await offline.catalog.search(value, 20);

        if (results.length > 0) {
            this.products = results;
            this.categories = [];
        } else {
            nsSnackBar.info(__("No products match your search."));
        }

        return;
    }

    // Search mode — product list lookup
    nsHttpClient.post("/api/products/search", { ... existing code ... });
```

- [ ] **Step 5: Type-check the modified files**

Run:
```bash
./node_modules/.bin/esbuild resources/ts/libraries/offline/offline.ts --loader:.ts=ts --format=esm --target=es2020 > /dev/null && echo "offline.ts OK"
node -e 'const fs=require("fs");const {parse,compileScript,compileTemplate}=require("@vue/compiler-sfc");for(const file of ["resources/ts/pages/dashboard/pos/ns-pos-offline-banner.vue","resources/ts/pages/dashboard/pos/ns-pos.vue","resources/ts/pages/dashboard/pos/ns-pos-grid.vue"]){const {descriptor,errors}=parse(fs.readFileSync(file,"utf8"),{filename:file});if(errors.length){console.error(file,errors);process.exit(1)}compileScript(descriptor,{id:"x"});if(descriptor.template)compileTemplate({source:descriptor.template.content,filename:file,id:"x"});console.log(file+" OK")}'
```
Expected: all files compile without errors.

- [ ] **Step 6: Build the assets**

Run:
```bash
npm run build
```
Expected: build succeeds.

- [ ] **Step 7: Commit**

```bash
git add resources/ts/libraries/offline/offline.ts resources/ts/pages/dashboard/pos/ns-pos-offline-banner.vue resources/ts/pages/dashboard/pos/ns-pos.vue resources/ts/pages/dashboard/pos/ns-pos-grid.vue public/build
git commit -m "feat: add offline banner and cached catalog browsing in POS"
```

---

## Task 10: Playwright E2E — offline foundation

**Files:**
- Create: `playwright/offline-foundation.spec.ts`

The service worker header is injected in the test because `php artisan serve` does not read `.htaccess`.

- [ ] **Step 1: Write the E2E spec**

Create `playwright/offline-foundation.spec.ts`:
```ts
import { test, expect } from '@playwright/test';

/**
 * Verifies the Phase 1 offline foundation:
 *  - the catalog snapshot is stored in IndexedDB
 *  - the POS falls back to the cached catalog and shows the offline banner
 *
 * The service worker scope fix is verified manually (DevTools / curl) because
 * php artisan serve does not apply .htaccess and Playwright does not intercept
 * the service worker script request.
 */
test.describe('Offline foundation', () => {

    test('caches the catalog and browses it while offline', async ({ page, context }) => {
        await page.goto('/dashboard/pos');
        await page.waitForSelector('#pos-container');

        // 1 — wait for the offline snapshot to be persisted
        const snapshotStored = await (
            await page.waitForFunction(async () => {
                const database = await new Promise<IDBDatabase>((resolve, reject) => {
                    const request = indexedDB.open('drcare-offline');

                    request.onsuccess = () => resolve(request.result);
                    request.onerror = () => reject(request.error);
                });

                const meta = await new Promise<any>((resolve, reject) => {
                    const transaction = database.transaction('meta', 'readonly');
                    const request = transaction.objectStore('meta').get('snapshot');

                    request.onsuccess = () => resolve(request.result);
                    request.onerror = () => reject(request.error);
                });

                return !!meta;
            }, null, { timeout: 30000 })
        ).jsonValue();
        expect(snapshotStored).toBeTruthy();

        // Go offline and wait for the monitor to report offline.
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

        // 3 — the grid must render cached categories
        await expect(page.locator('[data-category-id]').first()).toBeVisible();

        // 4 — drill into categories until cached products render
        for (let depth = 0; depth < 4; depth++) {
            if (await page.locator('[data-product-id]').count() > 0) {
                break;
            }

            const category = page.locator('[data-category-id]').first();

            if (await category.count() === 0) {
                break;
            }

            await category.click();
            await page.waitForTimeout(250);
        }

        await expect(page.locator('[data-product-id]').first()).toBeVisible();
    });
});
```

- [ ] **Step 2: Expose the singleton for the test**

In `resources/ts/libraries/offline/offline.ts`, at the end of `initializeOfflineMode()`, before `return context;`, add:
```ts
// Exposed for debugging and E2E tests.
(window as any).nsOffline = context;
```

- [ ] **Step 3: Add stable hooks to the grid cards**

In `resources/ts/pages/dashboard/pos/ns-pos-grid.vue`:
- On the category card (the div with `@click="loadCategories(category)"`), add:
```html
:data-category-id="category.id"
```
- On the product card (the div with `@click="!isOutOfStock(product) && addToTheCart(product)"`), add:
```html
:data-product-id="product.id"
```

- [ ] **Step 4: Rebuild and run the E2E test**

Run:
```bash
npm run build
npx playwright test playwright/offline-foundation.spec.ts
```
Expected: 1 passed. If the test fails because the POS page shows no categories in this environment, seed categories first (`php artisan test tests/Feature/CreateCategoryTest.php`) against the database the E2E server uses.

- [ ] **Step 5: Run the existing POS E2E specs to check for regressions**

Run:
```bash
npx playwright test playwright/pos-cart.spec.ts playwright/pos-checkout.spec.ts
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add playwright/offline-foundation.spec.ts resources/ts/libraries/offline/offline.ts resources/ts/pages/dashboard/pos/ns-pos-grid.vue public/build
git commit -m "test: add offline foundation E2E coverage"
```

---

## Phase 1 Done Criteria

- `php artisan test tests/Feature/PosHeartbeatTest.php tests/Feature/PosOfflineSnapshotTest.php` passes.
- `npx jest` passes (offline logic suites).
- `npm run build` succeeds and `public/build/sw.js` includes the `pos-page` cache.
- On the live site, `/build/sw.js` responds with `Service-Worker-Allowed: /` (verify with `curl -I`).
- In the POS, switching the browser offline shows the red **OFFLINE MODE** banner and the grid still lists cached categories and products.

## Deploy note

Phase 1 touches `.htaccess`, PHP routes/controllers, TS sources and built assets. Deploy like the previous update: pull on the server, deploy `public/build`, run `php artisan optimize:clear`, then hard-refresh the POS. No DB migration is required in Phase 1.
