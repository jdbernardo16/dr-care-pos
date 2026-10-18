# Attendance Clock Device Enforcement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restrict clock in/out/break actions to a single enrolled store device, except for `admin` and `nexopos.developer` roles who keep anywhere-access.

**Architecture:** A trusted-device enrollment system. The PWA generates a persistent device identity (`device_id` + `device_secret`) in `localStorage`. An admin generates a short-lived, single-use enrollment code on the tablet; the tablet self-registers by presenting that code. A Laravel middleware on the four clock routes verifies every request from non-admin users carries a valid, active enrolled device's identity (secret bcrypt-hashed server-side). Admin/developer requests bypass the check. The device identity is also recorded on each attendance row for audit.

**Tech Stack:** Laravel 10 (NexoPOS), MySQL/SQLite, Vue 3 SFC + TypeScript/JS (Vite), PHPUnit for Feature tests, Tailwind CSS.

**Branch:** `feat/attendance-device-enrollment` (created from `ui/pos-revamp-loyverse`).

---

## File Structure

**Created (backend):**
- `database/migrations/update/2026_08_18_000000_create_attendance_devices_table.php`
- `app/Models/AttendanceDevice.php`
- `app/Services/AttendanceDeviceService.php`
- `app/Http/Middleware/EnsureEnrolledDevice.php`
- `tests/Feature/AttendanceDeviceEnforcementTest.php`

**Modified (backend):**
- `app/Services/AttendanceService.php` (record device id on clock in/out; expose `device_verified` in `getCurrentStatus`)
- `app/Http/Controllers/Dashboard/AttendanceController.php` (enroll/generate/list/toggle/delete endpoints)
- `routes/api/attendance.php` (new routes + middleware on clock routes)
- `resources/views/pages/dashboard/attendance/devices.blade.php` (admin page view)

**Created (frontend):**
- `resources/ts/libraries/device-identity.ts` (device identity helper)
- `resources/ts/pages/dashboard/attendance/attendance-devices.vue` (admin devices page)

**Modified (frontend):**
- `resources/ts/bootstrap.ts` (inject device headers via axios interceptor)
- `resources/ts/app-init.ts` (register `nsAttendanceDevices` component)
- `resources/ts/pages/dashboard/attendance/attendance-clock.vue` (enrollment prompt + 403 handling)

**Docs:**
- `docs/PAYROLL_ATTENDANCE_USER_MANUAL.md` (enrollment steps for the owner)

---

### Task 1: Database Migration

**Files:**
- Create: `database/migrations/update/2026_08_18_000000_create_attendance_devices_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create( 'nexopos_attendance_devices', function ( Blueprint $table ) {
            $table->bigIncrements( 'id' );
            $table->string( 'device_id' )->unique();
            $table->string( 'secret_hash' );
            $table->string( 'label' )->nullable();
            $table->unsignedBigInteger( 'enrolled_by' )->nullable();
            $table->datetime( 'enrolled_at' )->nullable();
            $table->datetime( 'last_used_at' )->nullable();
            $table->boolean( 'active' )->default( true );
            $table->timestamps();
        } );

        Schema::table( 'nexopos_attendance', function ( Blueprint $table ) {
            $table->string( 'clock_in_device_id' )->nullable()->after( 'clock_in_ip' );
            $table->string( 'clock_out_device_id' )->nullable()->after( 'clock_out_ip' );
        } );
    }

    public function down()
    {
        Schema::table( 'nexopos_attendance', function ( Blueprint $table ) {
            $table->dropColumn( [ 'clock_in_device_id', 'clock_out_device_id' ] );
        } );

        Schema::dropIfExists( 'nexopos_attendance_devices' );
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php run-pending-migrations.php`
Expected: migration applies without error; `nexopos_attendance_devices` exists.

- [ ] **Step 3: Verify columns**

Run: `php artisan tinker --execute="echo Schema::hasTable('nexopos_attendance_devices') ? 'devices:ok' : 'devices:missing'; echo PHP_EOL; echo Schema::hasColumn('nexopos_attendance', 'clock_in_device_id') ? 'col:ok' : 'col:missing';"`
Expected: `devices:ok` and `col:ok`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/update/2026_08_18_000000_create_attendance_devices_table.php
git commit -m "feat: add attendance devices table and device id columns"
```

---

### Task 2: AttendanceDevice Model

**Files:**
- Create: `app/Models/AttendanceDevice.php`

- [ ] **Step 1: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceDevice extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_attendance_devices';

    protected $fillable = [
        'device_id',
        'secret_hash',
        'label',
        'enrolled_by',
        'enrolled_at',
        'last_used_at',
        'active',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'last_used_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function scopeActive( $query )
    {
        return $query->where( 'active', true );
    }

    public function scopeForDeviceId( $query, $deviceId )
    {
        return $query->where( 'device_id', $deviceId );
    }
}
```

- [ ] **Step 2: Smoke-check the model loads**

Run: `php artisan tinker --execute="echo class_exists(\App\Models\AttendanceDevice::class) ? 'model:ok' : 'model:missing';"`
Expected: `model:ok`

- [ ] **Step 3: Commit**

```bash
git add app/Models/AttendanceDevice.php
git commit -m "feat: add AttendanceDevice model"
```

---

### Task 3: AttendanceDeviceService

**Files:**
- Create: `app/Services/AttendanceDeviceService.php`

- [ ] **Step 1: Write the service**

```php
<?php

namespace App\Services;

use App\Exceptions\NotAllowedException;
use App\Models\AttendanceDevice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class AttendanceDeviceService
{
    private const CODE_CACHE_PREFIX = 'attendance-enrollment-code-';

    private const CODE_TTL_MINUTES = 10;

    /**
     * Admin/developer roles always bypass device enforcement.
     */
    public function canBypassDeviceCheck( $user = null ): bool
    {
        $user = $user ?: Auth::user();

        if ( ! $user instanceof User ) {
            return false;
        }

        return $user->hasRoles( [ 'admin', 'nexopos.developer' ] );
    }

    /**
     * Whether the current request carries a valid, active enrolled device.
     */
    public function requestHasValidDevice( $request = null ): bool
    {
        $request = $request ?: request();

        $deviceId = $request->header( 'X-Device-Id' );
        $secret = $request->header( 'X-Device-Secret' );

        if ( empty( $deviceId ) || empty( $secret ) ) {
            return false;
        }

        $device = AttendanceDevice::active()
            ->forDeviceId( $deviceId )
            ->first();

        if ( ! $device instanceof AttendanceDevice ) {
            return false;
        }

        if ( ! Hash::check( $secret, $device->secret_hash ) ) {
            return false;
        }

        $device->update( [ 'last_used_at' => now() ] );

        return true;
    }

    /**
     * Generate a single-use, short-lived enrollment code (admin only).
     */
    public function generateEnrollmentCode(): array
    {
        if ( ! $this->canBypassDeviceCheck() ) {
            throw new NotAllowedException( __( 'Only an administrator can generate an enrollment code.' ) );
        }

        $code = (string) random_int( 100000, 999999 );
        $expiresAt = now()->addMinutes( self::CODE_TTL_MINUTES );

        Cache::put(
            self::CODE_CACHE_PREFIX . $code,
            $expiresAt->timestamp,
            $expiresAt
        );

        return [
            'status' => 'success',
            'message' => __( 'Enrollment code generated. It expires in 10 minutes and can only be used once.' ),
            'data' => [
                'code' => $code,
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
        ];
    }

    /**
     * Enroll the current device using a valid code.
     */
    public function enrollDevice( array $payload ): array
    {
        $code = trim( (string) ( $payload[ 'code' ] ?? '' ) );
        $deviceId = trim( (string) ( $payload[ 'device_id' ] ?? '' ) );
        $deviceSecret = trim( (string) ( $payload[ 'device_secret' ] ?? '' ) );
        $label = trim( (string) ( $payload[ 'label' ] ?? '' ) );

        if ( $code === '' || $deviceId === '' || $deviceSecret === '' ) {
            throw new NotAllowedException( __( 'Code, device id and device secret are required.' ) );
        }

        $cacheKey = self::CODE_CACHE_PREFIX . $code;
        $expiresAt = Cache::get( $cacheKey );

        if ( ! is_numeric( $expiresAt ) || (int) $expiresAt < now()->timestamp ) {
            throw new NotAllowedException( __( 'This enrollment code is invalid or has expired.' ) );
        }

        Cache::forget( $cacheKey );

        $device = AttendanceDevice::forDeviceId( $deviceId )->first();

        if ( $device instanceof AttendanceDevice ) {
            throw new NotAllowedException( __( 'This device is already enrolled. Ask an administrator to reactivate it if needed.' ) );
        }

        $attendanceDevice = new AttendanceDevice;
        $attendanceDevice->device_id = $deviceId;
        $attendanceDevice->secret_hash = Hash::make( $deviceSecret );
        $attendanceDevice->label = $label !== '' ? $label : __( 'Unnamed device' );
        $attendanceDevice->enrolled_by = Auth::id();
        $attendanceDevice->enrolled_at = now();
        $attendanceDevice->active = true;
        $attendanceDevice->save();

        return [
            'status' => 'success',
            'message' => __( 'This device is now registered as the attendance clock device.' ),
            'data' => [ 'device' => $attendanceDevice ],
        ];
    }

    /**
     * Admin: list all enrolled devices.
     */
    public function listDevices(): array
    {
        $this->requireAdmin();

        return [
            'status' => 'success',
            'data' => AttendanceDevice::orderByDesc( 'id' )->get(),
        ];
    }

    /**
     * Admin: toggle device active state.
     */
    public function toggleDevice( AttendanceDevice $device ): array
    {
        $this->requireAdmin();

        $device->active = ! $device->active;
        $device->save();

        return [
            'status' => 'success',
            'message' => $device->active
                ? __( 'Device re-activated.' )
                : __( 'Device deactivated. It can no longer clock in.' ),
            'data' => [ 'device' => $device ],
        ];
    }

    /**
     * Admin: delete a device record.
     */
    public function deleteDevice( AttendanceDevice $device ): array
    {
        $this->requireAdmin();

        $device->delete();

        return [
            'status' => 'success',
            'message' => __( 'Device removed.' ),
        ];
    }

    private function requireAdmin(): void
    {
        if ( ! $this->canBypassDeviceCheck() ) {
            throw new NotAllowedException( __( 'Only an administrator can manage clock devices.' ) );
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/AttendanceDeviceService.php
git commit -m "feat: add AttendanceDeviceService"
```

---

### Task 4: EnsureEnrolledDevice Middleware

**Files:**
- Create: `app/Http/Middleware/EnsureEnrolledDevice.php`

- [ ] **Step 1: Write the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Exceptions\NotAllowedException;
use App\Services\AttendanceDeviceService;
use Closure;
use Illuminate\Http\Request;

class EnsureEnrolledDevice
{
    public function __construct(
        protected AttendanceDeviceService $attendanceDeviceService
    ) {}

    public function handle( Request $request, Closure $next )
    {
        if ( $this->attendanceDeviceService->canBypassDeviceCheck() ) {
            return $next( $request );
        }

        if ( ! $this->attendanceDeviceService->requestHasValidDevice( $request ) ) {
            throw new NotAllowedException(
                __( 'Clock in/out is only available on the store\'s registered device.' )
            );
        }

        return $next( $request );
    }
}
```

- [ ] **Step 2: Wire the middleware into the clock routes**

Modify: `routes/api/attendance.php` — replace the whole file with:

```php
<?php

use App\Http\Controllers\Dashboard\AttendanceController;
use App\Http\Middleware\EnsureEnrolledDevice;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::post( 'attendance/clock-in', [ AttendanceController::class, 'clockIn' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), EnsureEnrolledDevice::class );

Route::post( 'attendance/clock-out', [ AttendanceController::class, 'clockOut' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), EnsureEnrolledDevice::class );

Route::post( 'attendance/break-in', [ AttendanceController::class, 'breakIn' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), EnsureEnrolledDevice::class );

Route::post( 'attendance/break-out', [ AttendanceController::class, 'breakOut' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), EnsureEnrolledDevice::class );

Route::middleware( NsRestrictMiddleware::arguments( 'attendance.read' ) )->group( function () {
    Route::get( 'attendance/current-status', [ AttendanceController::class, 'getCurrentStatus' ] );
    Route::get( 'attendance/staff-status', [ AttendanceController::class, 'getStaffStatus' ] );
    Route::get( 'attendance/history', [ AttendanceController::class, 'getHistory' ] );
} );

Route::post( 'attendance/enroll-device', [ AttendanceController::class, 'enrollDevice' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ) );

Route::post( 'attendance/generate-enrollment-code', [ AttendanceController::class, 'generateEnrollmentCode' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ) );

Route::middleware( NsRestrictMiddleware::arguments( 'attendance.read' ) )->group( function () {
    Route::get( 'attendance/devices', [ AttendanceController::class, 'listDevices' ] );
    Route::post( 'attendance/devices/{device}/toggle', [ AttendanceController::class, 'toggleDevice' ] );
    Route::delete( 'attendance/devices/{device}', [ AttendanceController::class, 'deleteDevice' ] );
} );
```

- [ ] **Step 3: Verify routes register**

Run: `php artisan route:list --path=attendance`
Expected: all clock routes show both middlewares; enroll/generate/devices routes present.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Middleware/EnsureEnrolledDevice.php routes/api/attendance.php
git commit -m "feat: enforce enrolled device on attendance clock routes"
```

---

### Task 5: Controller Endpoints

**Files:**
- Modify: `app/Http/Controllers/Dashboard/AttendanceController.php`

- [ ] **Step 1: Add the device endpoints to the controller**

Replace the class body of `AttendanceController.php` with:

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Crud\AttendanceCrud;
use App\Http\Controllers\DashboardController;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Services\AttendanceDeviceService;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends DashboardController
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected AttendanceDeviceService $attendanceDeviceService
    ) {}

    public function clockIn( Request $request )
    {
        return $this->attendanceService->clockIn(
            Auth::id(),
            $request->input( 'note' )
        );
    }

    public function clockOut( Request $request )
    {
        return $this->attendanceService->clockOut(
            Auth::id(),
            $request->input( 'note' )
        );
    }

    public function breakIn( Request $request )
    {
        return $this->attendanceService->breakIn(
            Auth::id(),
            $request->input( 'note' )
        );
    }

    public function breakOut( Request $request )
    {
        return $this->attendanceService->breakOut(
            Auth::id(),
            $request->input( 'note' )
        );
    }

    public function getCurrentStatus()
    {
        return $this->attendanceService->getCurrentStatus( Auth::id() );
    }

    public function clockPage()
    {
        return view( 'pages.dashboard.attendance.clock', [
            'title' => __( 'Attendance Clock' ),
        ] );
    }

    public function getStaffStatus()
    {
        return $this->attendanceService->getStaffStatus();
    }

    public function getHistory( Request $request )
    {
        return $this->attendanceService->getHistory( $request->only( [
            'user_id', 'start_date', 'end_date', 'status',
        ] ) );
    }

    public function listAttendances()
    {
        return AttendanceCrud::table();
    }

    public function createAttendance()
    {
        return AttendanceCrud::form();
    }

    public function editAttendance( Attendance $attendance )
    {
        return AttendanceCrud::form( $attendance );
    }

    public function enrollDevice( Request $request )
    {
        return $this->attendanceDeviceService->enrollDevice( $request->only( [
            'code', 'device_id', 'device_secret', 'label',
        ] ) );
    }

    public function generateEnrollmentCode()
    {
        return $this->attendanceDeviceService->generateEnrollmentCode();
    }

    public function listDevices()
    {
        return $this->attendanceDeviceService->listDevices();
    }

    public function toggleDevice( AttendanceDevice $device )
    {
        return $this->attendanceDeviceService->toggleDevice( $device );
    }

    public function deleteDevice( AttendanceDevice $device )
    {
        return $this->attendanceDeviceService->deleteDevice( $device );
    }

    public function devicesPage()
    {
        return view( 'pages.dashboard.attendance.devices', [
            'title' => __( 'Clock-in Devices' ),
        ] );
    }
}
```

- [ ] **Step 2: Add the devices page web route**

Modify: `routes/web/attendance.php` — append this line:

```php
Route::get( '/attendance/devices', [ AttendanceController::class, 'devicesPage' ] )->name( ns()->routeName( 'ns.dashboard.attendance-devices' ) );
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Dashboard/AttendanceController.php routes/web/attendance.php
git commit -m "feat: add attendance device enrollment and management endpoints"
```

---

### Task 6: Record Device on Clock In/Out + Status Flag

**Files:**
- Modify: `app/Services/AttendanceService.php`

- [ ] **Step 1: Record the device id and expose `device_verified`**

Make these edits in `AttendanceService.php`:

1. Add the import at the top:

```php
use App\Services\AttendanceDeviceService;
```

2. In `clockIn()`, after `$attendance->clock_in_ip = request()->ip();` add:

```php
$attendance->clock_in_device_id = request()->header( 'X-Device-Id' );
```

3. In `clockOut()`, after `$activeRecord->clock_out_ip = request()->ip();` add:

```php
$activeRecord->clock_out_device_id = request()->header( 'X-Device-Id' );
```

4. In `getCurrentStatus()`, build the response through a shared helper. Replace the two `return [...]` blocks with:

```php
        return $this->buildCurrentStatusResponse( $todayRecord );
```

5. Add this private helper method at the end of the class (before the closing brace):

```php
    private function buildCurrentStatusResponse( $todayRecord )
    {
        $attendanceDeviceService = app()->make( AttendanceDeviceService::class );

        return [
            'status' => 'success',
            'data' => [
                'is_clocked_in' => $todayRecord instanceof Attendance
                    && in_array( $todayRecord->status, [
                        Attendance::STATUS_CLOCKED_IN, Attendance::STATUS_ON_BREAK,
                    ] ),
                'is_on_break' => $todayRecord instanceof Attendance
                    && $todayRecord->status === Attendance::STATUS_ON_BREAK,
                'record' => $todayRecord,
                'device_verified' => $attendanceDeviceService->canBypassDeviceCheck()
                    ? true
                    : $attendanceDeviceService->requestHasValidDevice(),
            ],
        ];
    }
```

- [ ] **Step 2: Verify no syntax errors**

Run: `php -l app/Services/AttendanceService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Services/AttendanceService.php
git commit -m "feat: record device id on clock actions and expose device_verified status"
```

---

### Task 7: Feature Tests

**Files:**
- Create: `tests/Feature/AttendanceDeviceEnforcementTest.php`

The suite runs against a pre-migrated SQLite DB without `RefreshDatabase` (dominant pattern). Tests create their own records with unique data and clean them up.

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature;

use App\Exceptions\NotAllowedException;
use App\Models\AttendanceDevice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceDeviceEnforcementTest extends TestCase
{
    private array $createdUsers = [];

    private array $createdDevices = [];

    private function makeUser( array $roles = [] ): User
    {
        $username = 'devtest_' . uniqid();

        $user = new User;
        $user->username = $username;
        $user->email = $username . '@example.test';
        $user->password = Hash::make( 'password' );
        $user->active = true;
        $user->save();

        foreach ( $roles as $role ) {
            $user->assignRole( $role );
        }

        $this->createdUsers[] = $user->id;

        return $user;
    }

    private function makeDevice( string $deviceId, string $secret, bool $active = true ): AttendanceDevice
    {
        $device = new AttendanceDevice;
        $device->device_id = $deviceId;
        $device->secret_hash = Hash::make( $secret );
        $device->label = 'Test Tablet';
        $device->active = $active;
        $device->save();

        $this->createdDevices[] = $device->id;

        return $device;
    }

    protected function tearDown(): void
    {
        AttendanceDevice::whereIn( 'id', $this->createdDevices )->delete();
        User::whereIn( 'id', $this->createdUsers )->delete();

        parent::tearDown();
    }

    // Note: attendance rows created by these tests are intentionally left in
    // place — each test uses a unique user, so they never collide. If the test
    // DB grows too large, wipe tests/database.sqlite and re-seed.

    public function test_admin_bypasses_device_check_without_headers()
    {
        $user = $this->makeUser( [ 'admin' ] );

        $response = $this->actingAs( $user )->postJson( '/api/attendance/clock-in' );

        $this->assertNotEquals( 403, $response->getStatusCode() );
    }

    public function test_non_admin_without_device_is_rejected()
    {
        $user = $this->makeUser( [ 'cashier' ] );

        $response = $this->actingAs( $user )->postJson( '/api/attendance/clock-in' );

        $response->assertStatus( 403 );
        $response->assertJsonPath( 'message', "Clock in/out is only available on the store's registered device." );
    }

    public function test_non_admin_with_unknown_device_is_rejected()
    {
        $user = $this->makeUser( [ 'cashier' ] );

        $response = $this->actingAs( $user )->postJson(
            '/api/attendance/clock-in',
            [],
            [ 'X-Device-Id' => 'unknown-device', 'X-Device-Secret' => 'secret' ]
        );

        $response->assertStatus( 403 );
    }

    public function test_non_admin_with_wrong_secret_is_rejected()
    {
        $user = $this->makeUser( [ 'cashier' ] );
        $this->makeDevice( 'device-abc', 'right-secret' );

        $response = $this->actingAs( $user )->postJson(
            '/api/attendance/clock-in',
            [],
            [ 'X-Device-Id' => 'device-abc', 'X-Device-Secret' => 'wrong-secret' ]
        );

        $response->assertStatus( 403 );
    }

    public function test_non_admin_with_valid_device_is_allowed()
    {
        $user = $this->makeUser( [ 'cashier' ] );
        $this->makeDevice( 'device-valid', 'valid-secret' );

        $response = $this->actingAs( $user )->postJson(
            '/api/attendance/clock-in',
            [],
            [ 'X-Device-Id' => 'device-valid', 'X-Device-Secret' => 'valid-secret' ]
        );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'status', 'success' );
    }

    public function test_deactivated_device_is_rejected()
    {
        $user = $this->makeUser( [ 'cashier' ] );
        $this->makeDevice( 'device-off', 'off-secret', false );

        $response = $this->actingAs( $user )->postJson(
            '/api/attendance/clock-in',
            [],
            [ 'X-Device-Id' => 'device-off', 'X-Device-Secret' => 'off-secret' ]
        );

        $response->assertStatus( 403 );
    }

    public function test_enrollment_with_valid_code_creates_device()
    {
        $user = $this->makeUser( [ 'cashier' ] );
        $code = '123456';
        Cache::put( 'attendance-enrollment-code-' . $code, now()->addMinutes( 10 )->timestamp, now()->addMinutes( 10 ) );

        $response = $this->actingAs( $user )->postJson( '/api/attendance/enroll-device', [
            'code' => $code,
            'device_id' => 'enrolled-device-1',
            'device_secret' => 'enrolled-secret-1',
            'label' => 'Store Tablet',
        ] );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'status', 'success' );

        $device = AttendanceDevice::forDeviceId( 'enrolled-device-1' )->first();
        $this->assertNotNull( $device );
        $this->assertTrue( Hash::check( 'enrolled-secret-1', $device->secret_hash ) );

        $this->createdDevices[] = $device->id;
    }

    public function test_enrollment_with_expired_code_is_rejected()
    {
        $user = $this->makeUser( [ 'cashier' ] );
        $code = '999999';
        Cache::put( 'attendance-enrollment-code-' . $code, now()->subMinute()->timestamp, now()->addMinutes( 10 ) );

        $response = $this->actingAs( $user )->postJson( '/api/attendance/enroll-device', [
            'code' => $code,
            'device_id' => 'enrolled-device-2',
            'device_secret' => 'enrolled-secret-2',
        ] );

        $response->assertStatus( 403 );
    }

    public function test_enrollment_code_is_single_use()
    {
        $user = $this->makeUser( [ 'cashier' ] );
        $code = '111222';
        Cache::put( 'attendance-enrollment-code-' . $code, now()->addMinutes( 10 )->timestamp, now()->addMinutes( 10 ) );

        $this->actingAs( $user )->postJson( '/api/attendance/enroll-device', [
            'code' => $code,
            'device_id' => 'enrolled-device-3',
            'device_secret' => 'enrolled-secret-3',
        ] )->assertStatus( 200 );

        $device = AttendanceDevice::forDeviceId( 'enrolled-device-3' )->first();
        $this->assertNotNull( $device );
        $this->createdDevices[] = $device->id;

        $second = $this->actingAs( $user )->postJson( '/api/attendance/enroll-device', [
            'code' => $code,
            'device_id' => 'enrolled-device-4',
            'device_secret' => 'enrolled-secret-4',
        ] );

        $second->assertStatus( 403 );
    }

    public function test_current_status_reports_device_verified_for_valid_device()
    {
        $user = $this->makeUser( [ 'cashier' ] );
        $this->makeDevice( 'device-status', 'status-secret' );

        $response = $this->actingAs( $user )->getJson(
            '/api/attendance/current-status',
            [ 'X-Device-Id' => 'device-status', 'X-Device-Secret' => 'status-secret' ]
        );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.device_verified', true );
    }

    public function test_current_status_reports_device_unverified_for_missing_device()
    {
        $user = $this->makeUser( [ 'cashier' ] );

        $response = $this->actingAs( $user )->getJson( '/api/attendance/current-status' );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.device_verified', false );
    }
}
```

- [ ] **Step 2: Run the tests**

Run: `vendor/bin/phpunit tests/Feature/AttendanceDeviceEnforcementTest.php`
Expected: all tests pass (green). If a test fails, fix and re-run.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/AttendanceDeviceEnforcementTest.php
git commit -m "test: cover attendance device enforcement"
```

---

### Task 8: Frontend — Device Identity + Header Injection

**Files:**
- Create: `resources/ts/libraries/device-identity.ts`
- Modify: `resources/ts/bootstrap.ts`

- [ ] **Step 1: Write the device identity helper**

```typescript
const STORAGE_KEY = "ns-device-identity";

interface DeviceIdentity {
    device_id: string;
    device_secret: string;
}

function generateSecret(): string {
    const bytes = new Uint8Array( 32 );
    crypto.getRandomValues( bytes );
    let base64 = btoa( String.fromCharCode( ...bytes ) );
    return base64.replace( /[^a-zA-Z0-9]/g, "" ).slice( 0, 43 );
}

export function getDeviceIdentity(): DeviceIdentity {
    const raw = localStorage.getItem( STORAGE_KEY );

    if ( raw ) {
        try {
            const parsed = JSON.parse( raw );
            if ( parsed && parsed.device_id && parsed.device_secret ) {
                return parsed;
            }
        } catch ( e ) {
            // fall through and regenerate
        }
    }

    const identity: DeviceIdentity = {
        device_id: crypto.randomUUID(),
        device_secret: generateSecret(),
    };

    localStorage.setItem( STORAGE_KEY, JSON.stringify( identity ) );

    return identity;
}
```

- [ ] **Step 2: Inject headers on every request**

Modify: `resources/ts/bootstrap.ts`

1. Add `import { getDeviceIdentity } from "./libraries/device-identity";` to the existing import block at the top of the file (do not create a second import block).
2. Right after the `nsHttpClient.defineClient( axios );` line (~line 125), add:

```typescript
axios.interceptors.request.use( ( config ) => {
    const identity = getDeviceIdentity();

    if ( identity ) {
        config.headers[ "X-Device-Id" ] = identity.device_id;
        config.headers[ "X-Device-Secret" ] = identity.device_secret;
    }

    return config;
} );
```

- [ ] **Step 3: Type-check**

Run: `npx tsc --noEmit` (if the repo supports it; otherwise run `npm run build` and confirm no TS errors for `bootstrap.ts`)
Expected: no new type errors.

- [ ] **Step 4: Commit**

```bash
git add resources/ts/libraries/device-identity.ts resources/ts/bootstrap.ts
git commit -m "feat: send device identity headers on all API requests"
```

---

### Task 9: Frontend — Clock Page Enrollment Prompt

**Files:**
- Modify: `resources/ts/pages/dashboard/attendance/attendance-clock.vue`

- [ ] **Step 1: Add enrollment UI state to the template**

In the `<template>`, immediately after the opening `<div class="ns-attendance-clock ...">` (line 4), insert:

```html
<!-- Unregistered device prompt -->
<div v-if="!deviceVerified && !isAdminUser" class="mb-6 border border-orange-300 bg-orange-50 rounded-lg p-4">
    <h3 class="font-bold text-orange-700 mb-1">{{ __( "Device Not Registered" ) }}</h3>
    <p class="text-sm text-orange-600 mb-3">
        {{ __( "Clock in/out is only available on the store's registered device. Ask the owner for the enrollment code." ) }}
    </p>
    <div class="flex flex-col gap-2">
        <input
            v-model="enrollmentCode"
            :placeholder="__('6-digit enrollment code')"
            class="ns-input w-full border rounded p-2 text-sm text-primary bg-surface"
        />
        <input
            v-model="deviceLabel"
            :placeholder="__('Device name (optional)')"
            class="ns-input w-full border rounded p-2 text-sm text-primary bg-surface"
        />
        <button
            @click="enrollDevice"
            :disabled="enrolling"
            class="bg-orange-500 hover:bg-orange-600 text-white transition-colors duration-200 px-4 py-2 rounded-lg text-sm font-semibold"
        >
            {{ enrolling ? __( "Processing..." ) : __( "Register This Device" ) }}
        </button>
    </div>
</div>
```

- [ ] **Step 2: Add data + methods to the script**

In the `<script>` `data()` return object, add:

```javascript
deviceVerified: true,
isAdminUser: false,
enrollmentCode: "",
deviceLabel: "",
enrolling: false,
```

In `fetchStatus()` (the `try` block after `this.lastRecord = response.data.record;`), add:

```javascript
this.deviceVerified = response.data.device_verified ?? true;
this.isAdminUser = response.data.is_admin ?? false;
```

Add the enrollment method in `methods` (and import `getDeviceIdentity` at the top of the `<script>` block next to the existing `nsHttpClient` import):

```javascript
async enrollDevice() {
    if ( ! this.enrollmentCode ) {
        nsSnackBar.error( __( "Please enter the enrollment code." ) );
        return;
    }

    this.enrolling = true;

    try {
        const identity = getDeviceIdentity();

        const response = await new Promise( ( resolve, reject ) => {
            nsHttpClient
                .post( "/api/attendance/enroll-device", {
                    code: this.enrollmentCode,
                    device_id: identity.device_id,
                    device_secret: identity.device_secret,
                    label: this.deviceLabel,
                } )
                .subscribe( {
                    next: ( response ) => resolve( response ),
                    error: ( error ) => reject( error ),
                } );
        } );

        nsSnackBar.success( response.message || __( "Device registered." ) );
        this.deviceVerified = true;
        this.enrollmentCode = "";
        this.deviceLabel = "";
    } catch ( error ) {
        nsSnackBar.error( error.message || __( "An error occurred." ) );
    } finally {
        this.enrolling = false;
    }
},
```

The import line to add at the top of `<script>`:

```javascript
import { getDeviceIdentity } from "~/libraries/device-identity";
```

- [ ] **Step 3: Handle 403 from clock actions**

In `toggleClock()` and `toggleBreak()` catch blocks, before the existing snackbar line, add:

```javascript
if ( error.message && error.message.includes( "registered device" ) ) {
    this.deviceVerified = false;
}
```

- [ ] **Step 4: Expose `is_admin` from the status endpoint**

Modify: `app/Services/AttendanceService.php` — in `buildCurrentStatusResponse()`, add to the `data` array:

```php
'is_admin' => $attendanceDeviceService->canBypassDeviceCheck(),
```

- [ ] **Step 5: Commit**

```bash
git add resources/ts/pages/dashboard/attendance/attendance-clock.vue app/Services/AttendanceService.php
git commit -m "feat: show enrollment prompt on unregistered clock devices"
```

---

### Task 10: Frontend — Admin Clock-in Devices Page

**Files:**
- Create: `resources/views/pages/dashboard/attendance/devices.blade.php`
- Create: `resources/ts/pages/dashboard/attendance/attendance-devices.vue`
- Modify: `resources/ts/app-init.ts`

- [ ] **Step 1: Write the blade view**

```blade
@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div>
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div id="dashboard-content" class="px-4">
        @include( 'common.dashboard.title' )
        <ns-attendance-devices></ns-attendance-devices>
    </div>
</div>
@endsection
```

- [ ] **Step 2: Write the Vue page component**

```vue
<template>
    <div class="ns-attendance-devices ns-box rounded-lg border shadow bg-surface p-6 max-w-3xl mx-auto my-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-primary">{{ __( "Clock-in Devices" ) }}</h2>
            <button
                @click="generateCode"
                :disabled="generating"
                class="bg-blue-500 hover:bg-blue-600 text-white transition-colors duration-200 px-4 py-2 rounded-lg text-sm font-semibold"
            >
                {{ generating ? __( "Generating..." ) : __( "Generate Enrollment Code" ) }}
            </button>
        </div>

        <div v-if="enrollmentCode" class="mb-4 border border-blue-300 bg-blue-50 rounded-lg p-4">
            <h3 class="font-bold text-blue-700 mb-1">{{ __( "Enrollment Code" ) }}</h3>
            <p class="text-3xl font-mono font-bold text-blue-800 tracking-widest mb-1">{{ enrollmentCode }}</p>
            <p class="text-xs text-blue-600">{{ __( "Expires" ) }}: {{ expiresAt }}</p>
            <p class="text-xs text-blue-600">{{ __( "Enter this on the tablet to register it. Single use." ) }}</p>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-secondary">
                    <th class="text-left py-2">{{ __( "Device" ) }}</th>
                    <th class="text-left py-2">{{ __( "Enrolled" ) }}</th>
                    <th class="text-left py-2">{{ __( "Last Used" ) }}</th>
                    <th class="text-left py-2">{{ __( "Status" ) }}</th>
                    <th class="text-right py-2">{{ __( "Actions" ) }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="devices.length === 0">
                    <td colspan="5" class="py-6 text-center text-secondary">
                        {{ __( "No devices enrolled yet." ) }}
                    </td>
                </tr>
                <tr v-for="device in devices" :key="device.id" class="border-b">
                    <td class="py-2">
                        <span class="font-semibold">{{ device.label }}</span>
                        <span class="block text-xs text-secondary">{{ device.device_id }}</span>
                    </td>
                    <td class="py-2 text-secondary">{{ device.enrolled_at }}</td>
                    <td class="py-2 text-secondary">{{ device.last_used_at || __( "Never" ) }}</td>
                    <td class="py-2">
                        <span
                            :class="device.active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            class="px-2 py-1 rounded-full text-xs font-semibold"
                        >
                            {{ device.active ? __( "Active" ) : __( "Deactivated" ) }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <button
                            @click="toggleDevice( device )"
                            class="text-blue-500 hover:text-blue-700 mr-3"
                        >
                            {{ device.active ? __( "Deactivate" ) : __( "Activate" ) }}
                        </button>
                        <button
                            @click="deleteDevice( device )"
                            class="text-red-500 hover:text-red-700"
                        >
                            {{ __( "Delete" ) }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from "~/bootstrap";
import { __ } from "~/libraries/lang";

export default {
    name: "nsAttendanceDevices",
    data() {
        return {
            devices: [],
            enrollmentCode: "",
            expiresAt: "",
            generating: false,
        };
    },
    mounted() {
        this.fetchDevices();
    },
    methods: {
        __,
        async fetchDevices() {
            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .get( "/api/attendance/devices" )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                this.devices = response.data || [];
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "Unable to load devices." ) );
            }
        },
        async generateCode() {
            this.generating = true;
            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .post( "/api/attendance/generate-enrollment-code" )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                this.enrollmentCode = response.data.code;
                this.expiresAt = response.data.expires_at;
                nsSnackBar.success( response.message || __( "Code generated." ) );
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "An error occurred." ) );
            } finally {
                this.generating = false;
            }
        },
        async toggleDevice( device ) {
            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .post( "/api/attendance/devices/" + device.id + "/toggle" )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                nsSnackBar.success( response.message || __( "Device updated." ) );
                this.fetchDevices();
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "An error occurred." ) );
            }
        },
        async deleteDevice( device ) {
            if ( ! confirm( __( "Remove this device? It will no longer be able to clock in." ) ) ) {
                return;
            }

            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .delete( "/api/attendance/devices/" + device.id )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                nsSnackBar.success( response.message || __( "Device removed." ) );
                this.fetchDevices();
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "An error occurred." ) );
            }
        },
    },
};
</script>
```

- [ ] **Step 3: Register the component**

Modify: `resources/ts/app-init.ts` — add the async component import near `nsAttendanceClock` (line 98-100):

```typescript
const nsAttendanceDevices = defineAsyncComponent(
    () => import("~/pages/dashboard/attendance/attendance-devices.vue"),
);
```

And add `nsAttendanceDevices,` to the `components` object (after `nsAttendanceClock,` on line 166).

- [ ] **Step 4: Commit**

```bash
git add resources/views/pages/dashboard/attendance/devices.blade.php resources/ts/pages/dashboard/attendance/attendance-devices.vue resources/ts/app-init.ts
git commit -m "feat: add admin clock-in devices management page"
```

---

### Task 11: Documentation

**Files:**
- Modify: `docs/PAYROLL_ATTENDANCE_USER_MANUAL.md`

- [ ] **Step 1: Append an enrollment section**

Append to the manual:

```markdown
## Clock-in Device Enrollment

Clock in/out/break is only available on the store's registered device (the tablet).
Admins and developers can always clock in from any device.

To register a tablet:

1. Sign in as an administrator.
2. Open **Attendance → Clock-in Devices**.
3. Click **Generate Enrollment Code** (valid 10 minutes, single use).
4. On the tablet, open the Attendance Clock page. It shows "Device Not Registered".
5. Enter the code (and an optional device name), then click **Register This Device**.

If the tablet's browser data is cleared, the device loses its registration —
repeat the steps above to re-enroll it. If a device is lost or a phone was
mistakenly enrolled, an administrator can deactivate or delete it from the
Clock-in Devices page.
```

- [ ] **Step 2: Commit**

```bash
git add docs/PAYROLL_ATTENDANCE_USER_MANUAL.md
git commit -m "docs: document clock-in device enrollment"
```

---

### Task 12: End-to-End Verification

- [ ] **Step 1: Run the full attendance test file**

Run: `vendor/bin/phpunit tests/Feature/AttendanceDeviceEnforcementTest.php`
Expected: all green.

- [ ] **Step 2: Build frontend assets**

Run: `npm run build`
Expected: build succeeds without errors.

- [ ] **Step 3: Manual QA checklist (record results in the commit message or a PR description)**

1. Sign in as a cashier on the store tablet → clock page shows normal state, clock-in succeeds.
2. Sign in as the same cashier on a personal phone → clock page shows "Device Not Registered", clock-in returns 403 with the message.
3. Sign in as an admin on the personal phone → clock-in succeeds.
4. Admin generates a code in Clock-in Devices → enter it on a phone → phone becomes registered and can clock in.
5. Admin deactivates that phone's device → phone clock-in fails again.
6. Clear the tablet's localStorage → tablet shows "Device Not Registered" → re-enroll with a fresh code.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: final verification"
```

---

## Self-Review Notes

- **Spec coverage:** Enrollment (Tasks 1-5, 9), enforcement middleware + admin/developer bypass (Tasks 4, 7), device id audit columns (Tasks 1, 6), admin management page (Task 10), re-enrollment path (Tasks 9, 11), docs (Task 11). The QR-code option from the spec is intentionally dropped — plain-text code is sufficient and QR adds no value here (noted in spec as optional).
- **Consistency:** `device_verified`, `is_admin`, `X-Device-Id`, `X-Device-Secret`, cache key prefix `attendance-enrollment-code-`, and the table name `nexopos_attendance_devices` are used consistently across all tasks.