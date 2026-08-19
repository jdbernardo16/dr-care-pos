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
