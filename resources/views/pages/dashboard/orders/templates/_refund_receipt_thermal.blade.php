<?php
use App\Classes\Hook;
use App\Models\PaymentType;
use App\Services\OrdersService;

$ordersService  =   app()->make( OrdersService::class );
$paperWidth     =   ns()->option->get( 'ns_invoice_receipt_template' ) === 'thermal_58' ? '58mm' : '80mm';
$storeName      =   ns()->option->get( 'ns_store_name' );
$storeLogo      =   ns()->option->get( 'ns_invoice_receipt_logo' );
?>
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Courier New', Courier, monospace; font-size: 10px; line-height: 1.3; color: #000; width: {{ $paperWidth }}; margin: 0 auto; padding: 4px 0; }
    .header { text-align: center; margin-bottom: 6px; }
    .header h2 { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
    .header img { max-width: 80%; height: auto; margin-bottom: 4px; }
    .divider { border: none; border-top: 1px dashed #000; margin: 4px 0; }
    .columns { display: flex; justify-content: space-between; font-size: 9px; padding: 0 4px; }
    .columns .col { width: 48%; }
    table { width: 100%; border-collapse: collapse; font-size: 9px; }
    thead th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 2px; text-align: left; font-size: 9px; }
    thead th.right { text-align: right; }
    td { padding: 2px 2px; vertical-align: top; }
    td.right { text-align: right; }
    td.bold { font-weight: bold; }
    .total-row td { border-top: 1px solid #000; padding-top: 3px; }
    .footer { text-align: center; font-size: 9px; margin-top: 8px; padding: 0 4px; }
    .product-name { font-size: 9px; }
    .product-meta { font-size: 8px; }
</style>
<div class="header">
    @if ( empty( $storeLogo ) )
    <h2>{{ $storeName }}</h2>
    @else
    <img src="{{ $storeLogo }}" alt="{{ $storeName }}">
    @endif
    <div class="header" style="font-size: 10px; margin-top: 4px;"><strong>{{ __( 'REFUND RECEIPT' ) }}</strong></div>
</div>
<div class="columns">
    <div class="col">{!! nl2br( $ordersService->orderTemplateMapping( 'ns_invoice_receipt_column_a', $refund->order ) ) !!}</div>
    <div class="col">{!! nl2br( $ordersService->orderTemplateMapping( 'ns_invoice_receipt_column_b', $refund->order ) ) !!}</div>
</div>
<hr class="divider">
<table>
    <thead>
        <tr>
            <th>{{ __( 'Item' ) }}</th>
            <th class="right">{{ __( 'Total' ) }}</th>
        </tr>
    </thead>
    <tbody>
        <?php $products = Hook::filter( 'ns-refund-receipt-products', $refund->refunded_products ); ?>
        @foreach( $products as $product )
        <tr>
            <td>
                <div class="product-name">{{ $product->product->name }} (x{{ $product->quantity }})</div>
                <div class="product-meta">{{ $product->unit->name }} — {{ __( 'Condition:' ) }} {{ $ordersService->getRefundedOrderProductLabel( $product->condition ) }}</div>
            </td>
            <td class="right">{{ Currency::raw( $product->total_price - $product->tax_value ) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tbody>
        @if ( $refund->tax_value > 0 )
        <tr>
            <td class="bold">{{ __( 'Sub Total' ) }}</td>
            <td class="right">{{ ns()->currency->define( $products->map( fn( $product ) => Currency::raw( $product->total_price - $product->tax_value ) )->sum() ) }}</td>
        </tr>
        <tr>
            <td class="bold">{{ __( 'Tax' ) }}</td>
            <td class="right">{{ ns()->currency->define( $refund->tax_value ) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td class="bold pad-top">{{ __( 'Total' ) }}</td>
            <td class="bold right pad-top">{{ ns()->currency->define( $refund->total ) }}</td>
        </tr>
        @if ( $refund->shipping > 0 )
        <tr>
            <td class="bold">{{ __( 'Shipping' ) }}</td>
            <td class="right">{{ ns()->currency->define( $refund->shipping ) }}</td>
        </tr>
        @endif
        <?php
        $paymentType    =   PaymentType::where( 'identifier', $refund->payment_method )->first();
        $paymentName    =   $paymentType instanceof PaymentType ? $paymentType->label : __( 'Unknown Payment' );
        ?>
        <tr>
            <td class="bold">{{ $paymentName }}</td>
            <td class="right">{{ ns()->currency->define( $refund->total ) }}</td>
        </tr>
    </tbody>
</table>
<div class="footer">
    {{ ns()->option->get( 'ns_invoice_receipt_footer' ) }}
</div>
@includeWhen( request()->query( 'autoprint' ) === 'true', '/pages/dashboard/orders/templates/_autoprint' )
