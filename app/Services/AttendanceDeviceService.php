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
