# Attendance Clock Device Enforcement — Design

**Date:** 2026-08-18
**Status:** Approved design
**Scope:** Backend (Laravel/NexoPOS) + PWA frontend (Vue)

## Problem

The attendance clock is a PWA. Cashiers can open it on any personal device and
clock in/out, which the pharmacy owner does not want. IP address cannot be used
for enforcement because the store has no static IP.

## Goal

- Clock in/out/break actions from a **shared store tablet** only, for all
  non-admin users (cashiers/staff).
- `admin` and `nexopos.developer` roles may clock in/out from **any** device
  (unchanged behavior).
- No reliance on IP address.

## Approach: Trusted-device enrollment

The tablet self-registers once via a one-time admin-generated code. Every clock
action from a non-admin user must then present the enrolled device's identity.
A personal phone has never been enrolled, so its clock attempts are rejected
with a 403.

### Roles

| Role | Device required |
| ---- | --------------- |
| `admin`, `nexopos.developer` | No (bypass) |
| Everyone else | Yes — enrolled device |

### Components

#### 1. Device identity (client)

- On first app load, the PWA checks `localStorage` for a device identity.
- If absent, generate:
  - `device_id`: cryptographically random UUID (`crypto.randomUUID()`).
  - `device_secret`: 32+ random bytes, base64url-encoded.
- Persist both in `localStorage` under a single key, e.g. `ns-device-identity`.

#### 2. Enrollment

- Admin goes to **Settings → Clock-in Devices** (new admin page).
- Admin clicks "Generate enrollment code" → 6-digit code, valid 10 minutes
  (single-use), shown with an optional QR code.
- On the tablet, the admin (or a staff member with the code) enters the code in
  a prompt shown by the clock page when the device is not yet enrolled.
- Frontend POSTs `{ code, device_id, device_secret, label }` to
  `POST /api/attendance/enroll-device`.
- Server validates the code (exists, not expired, not used), then stores the
  device with `device_secret` **hashed** (bcrypt), never in plaintext.

#### 3. Enforcement

- New middleware `EnsureEnrolledDevice` applied to the four clock routes:
  `attendance/clock-in`, `attendance/clock-out`, `attendance/break-in`,
  `attendance/break-out`.
- Middleware logic:
  1. If the authenticated user has roles `admin` or `nexopos.developer` →
     allow (bypass).
  2. Read `X-Device-Id` + `X-Device-Secret` headers.
  3. Look up an active, enrolled device by `device_id`. Verify the secret with
     `Hash::check`. On failure → 403 JSON:
     `"Clock in/out is only available on the store's registered device."`
  4. Touch `last_used_at` on the device row (throttled, not on every request
     if hot — acceptable to update every request; volume is low).

#### 4. Management (admin)

- CRUD list of enrolled devices: label, enrolled by, enrolled at, last used,
  active toggle, delete.
- "Generate enrollment code" action (with expiry + single-use).
- Re-enrollment path for tablet replacement or cleared browser data.

#### 5. Audit trail

- Existing `clock_in_ip` / `clock_out_ip` continue to record the source IP.
- New columns/values captured per clock action: the `device_id` used, stored on
  the attendance record (see Data model) for auditability.

### Data model

New table `nexopos_attendance_devices`:

| Column       | Type      | Notes                                    |
| ------------ | --------- | ---------------------------------------- |
| `id`         | bigint PK |                                          |
| `device_id`  | string    | unique, indexed                          |
| `secret_hash`| string    | bcrypt hash of the device secret         |
| `label`      | string    | human-friendly name (e.g. "Store Tablet")|
| `enrolled_by`| int FK    | user who enrolled                        |
| `enrolled_at`| datetime  |                                          |
| `last_used_at`| datetime | nullable                               |
| `active`     | bool      | default true                             |
| `timestamps` |           |                                          |

Migration placed under `database/migrations/update/`.

Optional: add `clock_in_device_id` / `clock_out_device_id` columns to the
`attendance` table for audit (recommended, cheap).

### Frontend changes

- HTTP client: inject `X-Device-Id` / `X-Device-Secret` headers on all requests
  when the device identity exists (`resources/ts/libraries/http-client.ts`).
- Clock page (`attendance-clock.vue`):
  - On 403 from a clock action → show "This device is not registered" state
    with a code-entry field for the admin to enroll it.
  - Otherwise unchanged.
- New admin settings page: "Clock-in Devices" (list + enrollment code
  generation). Follow existing dashboard CRUD conventions.
- Generate code → show as text and QR (existing QR helper if available;
  otherwise plain text code is sufficient).

### Error handling

- Clock action from unenrolled device (non-admin): 403 with a clear message.
- Enrollment code invalid/expired/used: 400 with specific message; code is
  single-use.
- Admin entering code on the tablet: succeeds, enrolls device, clock proceeds.
- Cleared browser data on the tablet: device identity lost → re-enroll via a
  new admin code (documented in the payroll/attendance user manual).

### Security notes

- Device secret stored bcrypt-hashed server-side; the raw secret exists only
  on the enrolled device.
- Transport is HTTPS (assumed — required for PWA install anyway).
- Deterrence-level control: a determined insider could copy the secret out of
  the tablet's localStorage. Acceptable for a pharmacy; noted as a limitation.
- Enrollment codes are short-lived, single-use, and require an admin to
  generate — a cashier cannot self-enroll their phone.

### Out of scope

- WebAuthn/hardware binding (possible future hardening).
- MAC-address binding (not possible in a PWA).
- Per-user device assignment (owner chose one shared tablet).

## Testing

- Unit: middleware allows admin/developer without headers; rejects non-admin
  without headers; rejects wrong/unknown device; accepts valid device.
- Unit: enrollment code validation (expiry, single-use, hashing).
- Feature/E2E: clock in from an enrolled device works; from an unenrolled
  device is blocked with the 403 message; admin clocks in from any device.
- Manual QA checklist documented for the owner (enroll tablet, clock in,
  attempt from personal phone, replace tablet, re-enroll).