<?php

namespace App\Services;

use App\Exceptions\NotAllowedException;
use App\Models\GcashSession;
use App\Models\GcashTransaction;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Register;
use Illuminate\Support\Facades\Auth;

class GcashService
{
    public function __construct(
        protected CashRegistersService $cashRegistersService,
        protected DateService $dateService
    ) {
        //
    }

    public function computeFee( float $amount ): float
    {
        return ceil( $amount / 500 ) * 10;
    }

    public function getActiveSession( Register $register ): ?GcashSession
    {
        return GcashSession::where( 'register_id', $register->id )
            ->where( 'status', 'opened' )
            ->first();
    }

    public function openSession( Register $register, float $openingBalance ): GcashSession
    {
        $existing = $this->getActiveSession( $register );

        if ( $existing ) {
            throw new NotAllowedException( __( 'A GCash session is already opened for this register.' ) );
        }

        $session = new GcashSession;
        $session->register_id = $register->id;
        $session->cashier_id = Auth::id();
        $session->opening_balance = $openingBalance;
        $session->current_balance = $openingBalance;
        $session->status = 'opened';
        $session->opened_at = ns()->date->now()->toDateTimeString();
        $session->save();

        return $session;
    }

    public function closeSession( GcashSession $session, float $closingBalance ): GcashSession
    {
        if ( $session->status !== 'opened' ) {
            throw new NotAllowedException( __( 'This GCash session is not opened.' ) );
        }

        $session->closing_balance = $closingBalance;
        $session->status = 'closed';
        $session->closed_at = ns()->date->now()->toDateTimeString();
        $session->save();

        return $session;
    }

    public function processCashIn( GcashSession $session, float $amount, ?string $phone = null, ?string $reference = null ): array
    {
        if ( $session->status !== 'opened' ) {
            throw new NotAllowedException( __( 'The GCash session is not opened.' ) );
        }

        if ( $amount <= 0 ) {
            throw new NotAllowedException( __( 'The amount must be greater than zero.' ) );
        }

        $fee = $this->computeFee( $amount );
        $totalCashIn = ns()->currency->define( $amount )->additionateBy( $fee )->toFloat();

        $transaction = new GcashTransaction;
        $transaction->gcash_session_id = $session->id;
        $transaction->register_id = $session->register_id;
        $transaction->cashier_id = Auth::id();
        $transaction->type = 'cash_in';
        $transaction->customer_amount = $amount;
        $transaction->service_fee = $fee;
        $transaction->customer_phone = $phone;
        $transaction->reference_no = $reference;
        $transaction->save();

        $session->current_balance = ns()->currency->define( $session->current_balance )->subtractBy( $amount )->toFloat();
        $session->total_cash_in = ns()->currency->define( $session->total_cash_in )->additionateBy( $amount )->toFloat();
        $session->total_fees = ns()->currency->define( $session->total_fees )->additionateBy( $fee )->toFloat();
        $session->save();

        $register = Register::find( $session->register_id );
        if ( $register && $register->status === Register::STATUS_OPENED ) {
            $this->cashRegistersService->cashIng(
                $register,
                $totalCashIn,
                sprintf( __( 'GCash Cash-In: %s + %s fee' ), $amount, $fee )
            );
        }

        return [
            'status' => 'success',
            'message' => __( 'The GCash cash-in has been processed.' ),
            'data' => compact( 'transaction', 'session' ),
        ];
    }

    public function processCashOut( GcashSession $session, float $amount, ?string $phone = null, ?string $reference = null ): array
    {
        if ( $session->status !== 'opened' ) {
            throw new NotAllowedException( __( 'The GCash session is not opened.' ) );
        }

        if ( $amount <= 0 ) {
            throw new NotAllowedException( __( 'The amount must be greater than zero.' ) );
        }

        $fee = $this->computeFee( $amount );
        $totalReceived = ns()->currency->define( $amount )->additionateBy( $fee )->toFloat();

        $transaction = new GcashTransaction;
        $transaction->gcash_session_id = $session->id;
        $transaction->register_id = $session->register_id;
        $transaction->cashier_id = Auth::id();
        $transaction->type = 'cash_out';
        $transaction->customer_amount = $amount;
        $transaction->service_fee = $fee;
        $transaction->customer_phone = $phone;
        $transaction->reference_no = $reference;
        $transaction->save();

        $session->current_balance = ns()->currency->define( $session->current_balance )->additionateBy( $totalReceived )->toFloat();
        $session->total_cash_out = ns()->currency->define( $session->total_cash_out )->additionateBy( $amount )->toFloat();
        $session->total_fees = ns()->currency->define( $session->total_fees )->additionateBy( $fee )->toFloat();
        $session->save();

        $register = Register::find( $session->register_id );
        if ( $register && $register->status === Register::STATUS_OPENED ) {
            $this->cashRegistersService->cashOut(
                $register,
                $amount,
                sprintf( __( 'GCash Cash-Out: %s given to customer, %s fee in GCash' ), $amount, $fee )
            );
        }

        return [
            'status' => 'success',
            'message' => __( 'The GCash cash-out has been processed.' ),
            'data' => compact( 'transaction', 'session' ),
        ];
    }

    public function getSessionReport( GcashSession $session ): object
    {
        $transactions = GcashTransaction::where( 'gcash_session_id', $session->id )
            ->orderBy( 'created_at', 'desc' )
            ->get();

        $cashInTotal = $transactions->where( 'type', 'cash_in' )->sum( 'customer_amount' );
        $cashOutTotal = $transactions->where( 'type', 'cash_out' )->sum( 'customer_amount' );
        $cashInCount = $transactions->where( 'type', 'cash_in' )->count();
        $cashOutCount = $transactions->where( 'type', 'cash_out' )->count();
        $totalFees = $transactions->sum( 'service_fee' );

        $orders = Order::paid()
            ->where( 'register_id', $session->register_id )
            ->where( 'created_at', '>=', $session->opened_at )
            ->when( $session->closed_at, function ( $query ) use ( $session ) {
                return $query->where( 'created_at', '<=', $session->closed_at );
            } )
            ->get();

        $gcashPaymentsTotal = OrderPayment::whereIn( 'order_id', $orders->pluck( 'id' ) )
            ->where( 'identifier', 'gcash-payment' )
            ->sum( 'value' );

        $gcashPaymentsCount = OrderPayment::whereIn( 'order_id', $orders->pluck( 'id' ) )
            ->where( 'identifier', 'gcash-payment' )
            ->count();

        $cashier = $session->cashier;
        $cashierName = $cashier ? ( $cashier->first_name . ' ' . $cashier->last_name . ' (' . $cashier->username . ')' ) : __( 'Unknown' );

        $expectedClosing = ns()->currency->define( $session->opening_balance )
            ->additionateBy( $gcashPaymentsTotal )
            ->subtractBy( $cashInTotal )
            ->additionateBy( $cashOutTotal )
            ->toFloat();

        $difference = ns()->currency->define( $session->closing_balance ?? 0 )
            ->subtractBy( $expectedClosing )
            ->toFloat();

        $computedBalance = ns()->currency->define( $session->current_balance )
            ->additionateBy( $gcashPaymentsTotal )
            ->toFloat();

        $openedOn = ns()->date->getFormatted( $session->opened_at );
        $closedOn = $session->closed_at ? ns()->date->getFormatted( $session->closed_at ) : __( 'Session Ongoing' );

        return (object) compact(
            'session',
            'transactions',
            'cashInTotal',
            'cashOutTotal',
            'cashInCount',
            'cashOutCount',
            'totalFees',
            'gcashPaymentsTotal',
            'gcashPaymentsCount',
            'computedBalance',
            'cashierName',
            'expectedClosing',
            'difference',
            'openedOn',
            'closedOn',
        );
    }
}
