<?php

/**
 * Re-attribute order 1815 (₱7,751 "Marken") from RenzoOng to jane_23
 * and stamp it with the charge date (2026-08-17 21:50:46).
 *
 * Run with: php fix-order-1815.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make( Illuminate\Contracts\Console\Kernel::class );
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

const ORDER_ID       = 1815;
const PAYMENT_ID     = 1969;
const REGISTER_HIST  = 2733;
const RENZO_ID       = 176;
const JANE_ID        = 174;
const CHARGE_TIME    = '2026-08-17 21:50:46';
const CHARGE_AMOUNT  = 7751;

function line( $msg )
{
    echo $msg . PHP_EOL;
}

line( '=== BEFORE ===' );

$order = DB::table( 'nexopos_orders' )->where( 'id', ORDER_ID )->first();
$payment = DB::table( 'nexopos_orders_payments' )->where( 'id', PAYMENT_ID )->first();
$regHist = DB::table( 'nexopos_registers_history' )->where( 'id', REGISTER_HIST )->first();
$renzo = DB::table( 'nexopos_users' )->where( 'id', RENZO_ID )->first();
$jane = DB::table( 'nexopos_users' )->where( 'id', JANE_ID )->first();

line( sprintf( 'Order %d: author=%d created_at=%s', $order->id, $order->author_id, $order->created_at ) );
line( sprintf( 'Payment %d: author=%d', $payment->id, $payment->author_id ) );
line( sprintf( 'RegisterHistory %d: author=%d', $regHist->id, $regHist->author_id ) );
line( sprintf( 'RenzoOng (%d): sales=%.2f count=%d', $renzo->id, $renzo->total_sales, $renzo->total_sales_count ) );
line( sprintf( 'jane_23 (%d): sales=%.2f count=%d', $jane->id, $jane->total_sales, $jane->total_sales_count ) );

line( '' );
line( '=== APPLYING FIXES ===' );

$orderUpdated = DB::table( 'nexopos_orders' )->where( 'id', ORDER_ID )->update( [
    'author_id'  => JANE_ID,
    'created_at' => CHARGE_TIME,
    'updated_at' => CHARGE_TIME,
] );

$paymentUpdated = DB::table( 'nexopos_orders_payments' )->where( 'id', PAYMENT_ID )->update( [
    'author_id' => JANE_ID,
] );

$regHistUpdated = DB::table( 'nexopos_registers_history' )->where( 'id', REGISTER_HIST )->update( [
    'author_id' => JANE_ID,
] );

/**
 * Move the cashier stats: RenzoOng loses the sale, jane_23 gains it.
 * The idempotency guard uses the charge time so running the script
 * twice doesn't double-apply the stats shift.
 */
$alreadyShifted = DB::table( 'nexopos_orders' )
    ->where( 'id', ORDER_ID )
    ->where( 'created_at', CHARGE_TIME )
    ->where( 'author_id', JANE_ID )
    ->exists();

$statsRenzo = 0;
$statsJane = 0;

/**
 * Idempotency guard: only shift the stats when the order is still
 * attributed to RenzoOng. If it's already on jane_23, the stats
 * have been moved and we skip. This prevents double-crediting
 * jane_23 when the script is run more than once.
 */
$stillOnRenzo = DB::table( 'nexopos_orders' )
    ->where( 'id', ORDER_ID )
    ->where( 'author_id', RENZO_ID )
    ->exists();

if ( ! $alreadyShifted || $orderUpdated || $stillOnRenzo ) {
    $statsRenzo = DB::table( 'nexopos_users' )->where( 'id', RENZO_ID )->update( [
        'total_sales'       => DB::raw( 'total_sales - ' . CHARGE_AMOUNT ),
        'total_sales_count' => DB::raw( 'total_sales_count - 1' ),
    ] );

    $statsJane = DB::table( 'nexopos_users' )->where( 'id', JANE_ID )->update( [
        'total_sales'       => DB::raw( 'total_sales + ' . CHARGE_AMOUNT ),
        'total_sales_count' => DB::raw( 'total_sales_count + 1' ),
    ] );
}

line( sprintf( 'Order: %s | Payment: %s | RegisterHistory: %s | RenzoOng stats: %s | jane_23 stats: %s',
    $orderUpdated ? 'UPDATED' : 'no change',
    $paymentUpdated ? 'UPDATED' : 'no change',
    $regHistUpdated ? 'UPDATED' : 'no change',
    $statsRenzo ? 'UPDATED' : 'no change',
    $statsJane ? 'UPDATED' : 'no change'
) );

line( '' );
line( '=== AFTER ===' );

$order = DB::table( 'nexopos_orders' )->where( 'id', ORDER_ID )->first();
$payment = DB::table( 'nexopos_orders_payments' )->where( 'id', PAYMENT_ID )->first();
$regHist = DB::table( 'nexopos_registers_history' )->where( 'id', REGISTER_HIST )->first();
$renzo = DB::table( 'nexopos_users' )->where( 'id', RENZO_ID )->first();
$jane = DB::table( 'nexopos_users' )->where( 'id', JANE_ID )->first();

line( sprintf( 'Order %d: author=%d created_at=%s', $order->id, $order->author_id, $order->created_at ) );
line( sprintf( 'Payment %d: author=%d', $payment->id, $payment->author_id ) );
line( sprintf( 'RegisterHistory %d: author=%d', $regHist->id, $regHist->author_id ) );
line( sprintf( 'RenzoOng (%d): sales=%.2f count=%d', $renzo->id, $renzo->total_sales, $renzo->total_sales_count ) );
line( sprintf( 'jane_23 (%d): sales=%.2f count=%d', $jane->id, $jane->total_sales, $jane->total_sales_count ) );

line( '' );
line( 'Done. Next step: refresh day reports to move ₱7,751 from 08-16 into 08-17:' );
line( '  php artisan ns:report --compute --from=2026-08-16 --type=day' );
line( '  php artisan ns:report --compute --from=2026-08-17 --type=day' );
