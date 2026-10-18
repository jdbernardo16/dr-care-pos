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
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );

        $response = $this->actingAs( $user )->postJson( '/api/attendance/clock-in' );

        $response->assertStatus( 403 );
        $response->assertJsonPath( 'message', "Clock in/out is only available on the store's registered device." );
    }

    public function test_non_admin_with_unknown_device_is_rejected()
    {
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );

        $response = $this->actingAs( $user )->postJson(
            '/api/attendance/clock-in',
            [],
            [ 'X-Device-Id' => 'unknown-device', 'X-Device-Secret' => 'secret' ]
        );

        $response->assertStatus( 403 );
    }

    public function test_non_admin_with_wrong_secret_is_rejected()
    {
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );
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
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );
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
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );
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
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );
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
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );
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
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );
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
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );
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
        $user = $this->makeUser( [ 'nexopos.store.cashier' ] );

        $response = $this->actingAs( $user )->getJson( '/api/attendance/current-status' );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.device_verified', false );
    }
}
