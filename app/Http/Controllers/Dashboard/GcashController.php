<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\NotAllowedException;
use App\Http\Controllers\DashboardController;
use App\Models\GcashSession;
use App\Models\Register;
use App\Services\GcashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class GcashController extends DashboardController
{
    public function __construct( protected GcashService $gcashService )
    {
        //
    }

    public function listSessions()
    {
        return View::make( 'pages.dashboard.gcash.gcash' );
    }

    public function getSessionForRegister( Register $register )
    {
        $session = $this->gcashService->getActiveSession( $register );

        if ( ! $session ) {
            return [
                'status' => 'info',
                'message' => __( 'No active GCash session for this register.' ),
                'data' => null,
            ];
        }

        $report = $this->gcashService->getSessionReport( $session );

        return [
            'status' => 'success',
            'data' => $report,
        ];
    }

    public function openSession( Request $request, Register $register )
    {
        if ( $register->status !== Register::STATUS_OPENED ) {
            throw new NotAllowedException( __( 'The cash register must be opened first.' ) );
        }

        $request->validate( [
            'opening_balance' => 'required|numeric|min:0',
        ] );

        $session = $this->gcashService->openSession(
            $register,
            $request->input( 'opening_balance' )
        );

        return [
            'status' => 'success',
            'message' => __( 'The GCash session has been opened.' ),
            'data' => compact( 'session' ),
        ];
    }

    public function closeSession( Request $request, GcashSession $session )
    {
        $request->validate( [
            'closing_balance' => 'required|numeric|min:0',
        ] );

        $session = $this->gcashService->closeSession(
            $session,
            $request->input( 'closing_balance' )
        );

        return [
            'status' => 'success',
            'message' => __( 'The GCash session has been closed.' ),
            'data' => compact( 'session' ),
        ];
    }

    public function processCashIn( Request $request, GcashSession $session )
    {
        $request->validate( [
            'amount' => 'required|numeric|min:1',
            'customer_phone' => 'nullable|string|max:20',
            'reference_no' => 'nullable|string|max:100',
        ] );

        return $this->gcashService->processCashIn(
            $session,
            (float) $request->input( 'amount' ),
            $request->input( 'customer_phone' ),
            $request->input( 'reference_no' )
        );
    }

    public function processCashOut( Request $request, GcashSession $session )
    {
        $request->validate( [
            'amount' => 'required|numeric|min:1',
            'customer_phone' => 'nullable|string|max:20',
            'reference_no' => 'nullable|string|max:100',
        ] );

        return $this->gcashService->processCashOut(
            $session,
            (float) $request->input( 'amount' ),
            $request->input( 'customer_phone' ),
            $request->input( 'reference_no' )
        );
    }

    public function getTransactions( GcashSession $session )
    {
        return GcashTransaction::where( 'gcash_session_id', $session->id )
            ->orderBy( 'created_at', 'desc' )
            ->get();
    }

    public function getSession( GcashSession $session )
    {
        return $this->gcashService->getSessionReport( $session );
    }

    public function getSessionHistory( Register $register )
    {
        return GcashSession::where( 'register_id', $register->id )
            ->orderBy( 'created_at', 'desc' )
            ->get();
    }
}
