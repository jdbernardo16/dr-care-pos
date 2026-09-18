# Offline Cash Mode — Design Spec

- **Date:** 2026-09-18
- **Branch:** `offline-feature`
- **Status:** Approved for planning

## Problem

The POS server runs on Hostinger (cloud). When the store's internet drops, the Android tablet cannot load or submit anything — no sales can be made at all. Slow/flaky connections are also a recurring source of checkout problems (see the checkout idempotency fix, commit `f32c287`).

## Goal

During an internet outage, the POS must keep working in a bounded way:

- Browse the cached catalog (products, prices, stock estimate)
- Complete **cash** sales
- Print a receipt via RawBT using an offline order number
- Sync automatically to Hostinger when the internet returns
- Change nothing about online behaviour

## Non-goals (v1)

- GCash, Maya, bank, and customer-account payments stay online-only
- Refunds, voids, register open/close, stock receiving, and customer credit stay online-only
- No multi-device offline selling (single tablet today; offline codes are device-scoped for the future)
- No full inventory truth offline — stock is an estimate (see Risks and accepted limitations)

## Decisions log

| Decision | Choice |
|---|---|
| Server location | Stays cloud-hosted (Hostinger) |
| Client platform | PWA (not a native app) — single Android tablet in Chrome |
| Offline payments | Cash only; GCash/Maya paused with a clear message |
| Offline receipts | Required, via RawBT (text ESC/POS built on-device) |
| Stock policy | Allow oversell with a warning; flag for reconciliation |
| Sync strategy | App-managed outbox in IndexedDB (not Service Worker Background Sync) |

## Architecture

### Components

1. **Service Worker (app shell only)**
   - Serve `sw.js` from the web root (or send `Service-Worker-Allowed: /`) so scope `/` is legal. The current registration (`/build/sw.js`, scope `/`) is rejected by browsers, so today no offline caching works at all.
   - Precache built assets + the POS route shell so the tablet can reopen/restart during an outage.
   - The SW does **not** cache API data. All data is managed explicitly in IndexedDB to keep behaviour predictable.

2. **Offline snapshot** (`GET /api/pos/offline-snapshot`, authenticated, gated by the same `nexopos.read.products` permission as the POS product grid)
   - Downloads everything the POS needs to run offline:
     - products: id, name, sku, barcode, type, tax group/type, unit quantities (id, unit_id, sale_price, quantity)
     - categories
     - customers (minimal: id, name, phone, group_id) — for attaching a customer to a sale; no credit offline
     - payment types
     - POS options subset used by the cart
     - register session state (open register id, or null)
     - receipt configuration (store name/address lines used by the receipt builder)
     - `server_time` and `generated_at`
   - Stored in **IndexedDB**. Refreshed on POS load and every 15 minutes while online (interval is configurable).
   - Full snapshot each refresh in v1 (≈2,000 products). Revisit incremental sync only if size becomes a problem.

3. **Connectivity monitor**
   - `GET /api/pos/heartbeat` (auth-only) returns `{ time }`. Poll every 20s, 5s timeout. Two consecutive failures → OFFLINE; any success → ONLINE.
   - `window.online` / `window.offline` events trigger an immediate heartbeat.
   - `navigator.onLine` is a hint only, never the sole signal.
   - Server time from the last successful heartbeat/snapshot is stored as `server_time_offset` to correct the tablet clock for offline order timestamps.

4. **Offline checkout**
   - Cart, search, and barcode lookup run against the IndexedDB catalog when OFFLINE.
   - Payment popup restricts to cash; totals/taxes are computed by the existing client-side cart logic.
   - On completion, an offline order record is written to IndexedDB and the local stock estimate is decremented (may go negative — warning shown, sale allowed).
   - The order gets:
     - `uuid`: reuse the existing checkout key from the idempotency fix
     - `code`: `OFL-<deviceShort>-YYMMDD-####` — sequence persisted per day in IndexedDB; `deviceShort` is the first 6 hex characters of `getDeviceIdentity().device_id` (dashes removed, uppercased), e.g. `OFL-A1B2C3-260918-0001`
     - `created_at`: tablet time + `server_time_offset`
   - Receipt: a client-side ESC/POS builder mirrors `RawbtPrintService`'s text layout (store header, items, totals, tendered/change, footer, cut). No logo in v1. The receipt shows the offline code and an "OFFLINE SALE" marker.
   - Outbox record shape:
     ```
     { uuid, code, created_at, payload, status: 'pending'|'syncing'|'synced'|'failed',
       attempts, last_error, server_order_id }
     ```

5. **Sync engine**
   - Flush the outbox sequentially (one order at a time) on transition to ONLINE and on POS load whenever the outbox is non-empty and the server is reachable.
   - For each order: `POST /api/orders` with the full payload plus `uuid`, `code`, `offline: true`, and the original `created_at`.
   - Success → mark `synced`, store `server_order_id`.
   - Failure → mark `failed`, keep in the "Needs attention" list with the server error. Never delete.
   - The existing `uuid` idempotency guarantees that retrying a partially-delivered sync cannot create duplicates.
   - UI: banner shows `OFFLINE MODE — Cash only · N pending`; a pending list is reachable from the banner; successful sync shows a confirmation.

6. **Reconciliation**
   - Offline orders carry an `is_offline` flag (new column) and the `OFL-` code prefix.
   - Dashboard gains a filter for offline sales and a variance view (orders whose stock went negative on sync).
   - Failed syncs are resolved manually: retry, or convert to a quick-product line when the original product no longer exists.

### Server changes

1. **Accept the client-provided code for offline orders.** Validate format `^OFL-[A-Z0-9]{2,12}-\d{6}-\d{4,}$`; reject if the code already belongs to a different order.
2. **Bypass the hard stock rejection** when `offline: true`. The order is created with the sale prices submitted (the server already trusts `unit_price` — `computeProduct`, `OrdersService.php`). Stock is still adjusted; products whose quantity goes negative are what the variance view lists.
3. **Mark the order offline.** New `is_offline` boolean column (migration, default `false`).
4. **Register attribution.** Attach the payment to the open register session; if none is open, leave it unattributed and list the order for review in the variance view.
5. **Preserve the sale timestamp.** The provided `created_at` is used as-is (the create flow already accepts it).

### Data model (IndexedDB stores)

| Store | Contents |
|---|---|
| `catalog` | products, categories (searchable in memory; 2k records) |
| `customers` | minimal customer list |
| `meta` | snapshot timestamp, `server_time_offset`, register state, options, receipt config |
| `outbox` | pending/failed offline orders |
| `counters` | offline code sequence per date |

### Offline sale flow

```
cart (IndexedDB catalog)
  → local order (uuid + OFL code + corrected time)
  → stock estimate adjusted (warn if < 0)
  → RawBT receipt
  → outbox (pending)

reconnect
  → POST /api/orders (uuid, code, offline)
  → server: idempotency check → create order → adjust real stock → register/accounting
  → mark synced; failures listed for resolution
```

## Error handling

- **Product deleted while offline** → sync fails; order stays in "Needs attention" for manual resolution.
- **Insufficient real stock on sync** → allowed; flagged as a discrepancy (policy decision).
- **No register session open on sync** → order flagged; payment unattributed until reconciled.
- **Browser data cleared** → outbox is lost. The POS tablet is treated as a fixed device; the store must not clear site data. (A future option is Background Sync as a secondary safety net.)
- **Clock drift** → corrected with `server_time_offset`.
- **Multiple tabs** → a localStorage lock ensures only one tab owns offline mode.

## Testing

- **Server (feature tests, TDD):**
  - offline order with `uuid` + `code` + `offline: true` creates exactly one order, preserving the code and timestamp
  - replaying the same sync returns the existing order (existing idempotency test extended)
  - stock insufficiency on sync does not reject the order and is flagged
  - register attribution behaves correctly with/without an open session
- **Frontend unit tests:** outbox state machine, offline code generator, stock estimate updates.
- **E2E (Playwright):** go offline → complete a cash sale → produce the receipt payload → come back online → verify the order exists exactly once with the offline code.

## Phasing

1. **Phase 1 — Foundation:** SW fix + app shell offline + snapshot endpoint + IndexedDB catalog + connectivity banner + offline catalog browsing (no selling).
2. **Phase 2 — Selling:** offline cash checkout + RawBT receipt + outbox + sync engine + pending UI.
3. **Phase 3 — Hardening:** reconciliation report, failure resolution UI, register/accounting edge cases, optional Background Sync.

## Risks and accepted limitations

- Offline stock is an estimate; drift is bounded by snapshot freshness (15 min) and surfaces as flagged variances.
- GCash and other online payments are unavailable offline by definition.
- Pending sales live in browser storage; clearing it loses them (device policy).
- Receipts omit the logo offline in v1.
- Only one tablet exists today; if a second terminal is added, offline codes are device-scoped but reconciliation volume grows.

## Known follow-ups

Documented from the Phase 1 implementation review; address before or during the indicated phase.

**Before / during Phase 2**
- Add receipt configuration (store header lines) to the snapshot payload.
- Bound the customer payload (limit or filter) instead of returning every customer.
- Extract the duplicated `applyHideProducts`/`applyHideCategories` catalog filters into a shared service used by both `CategoryController` and `PosController`.
- Make the snapshot write transactional across stores (single multi-store IndexedDB transaction).
- Add a multi-tab lock so only one tab owns offline mode and flushes the outbox.
- Add an offline **reload** E2E assertion once the service worker can be activated in the test environment (local `php artisan serve` does not send `Service-Worker-Allowed`; production does via `.htaccess`).

**Before / during Phase 3**
- Purge the service-worker auth caches (`pos-page`, `api-cache`) on sign-out so a shared tablet cannot serve the previous user's cached page.
- Add a 403 test for the snapshot endpoint's `nexopos.read.products` permission.
