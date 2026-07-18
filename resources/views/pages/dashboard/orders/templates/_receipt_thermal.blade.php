<?php
use App\Models\Order;
use App\Classes\Hook;
use Illuminate\Support\Facades\View;

$prefered_price     =   $order->settings?->where( 'key', 'ns_pos_prefered_price' )->first()?->value;
$pos_vat          =   $order->settings?->where( 'key', 'ns_pos_vat' )->first()?->value;
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
    .header .info { font-size: 9px; }
    .divider { border: none; border-top: 1px dashed #000; margin: 4px 0; }
    .divider-solid { border: none; border-top: 1px solid #000; margin: 4px 0; }
    .columns { display: flex; justify-content: space-between; font-size: 9px; padding: 0 4px; }
    .columns .col { width: 48%; }
    table { width: 100%; border-collapse: collapse; font-size: 9px; }
    thead th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 2px; text-align: left; font-size: 9px; }
    thead th.right { text-align: right; }
    td { padding: 2px 2px; vertical-align: top; }
    td.right { text-align: right; }
    td.bold { font-weight: bold; }
    td.pad-top { padding-top: 4px; }
    .total-row td { border-top: 1px solid #000; padding-top: 3px; }
    .footer { text-align: center; font-size: 9px; margin-top: 8px; padding: 0 4px; }
    .note { text-align: center; font-size: 9px; margin: 6px 0; padding: 0 4px; }
    .product-name { font-size: 9px; }
    .product-meta { font-size: 8px; }
</style>
<div class="header">
    @if ( empty( $storeLogo ) )
    <h2>{{ $storeName }}</h2>
    @else
    <img src="{{ $storeLogo }}" alt="{{ $storeName }}">
    @endif
</div>
<div class="columns">
    <div class="col">{!! nl2br( $ordersService->orderTemplateMapping( 'ns_invoice_receipt_column_a', $order ) ) !!}</div>
    <div class="col">{!! nl2br( $ordersService->orderTemplateMapping( 'ns_invoice_receipt_column_b', $order ) ) !!}</div>
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
        @foreach( Hook::filter( 'ns-receipt-products', $order->combinedProducts ) as $product )
        <tr>
            <td>
                <div class="product-name">{{ $product->name }}</div>
                <div class="product-meta">x{{ $product->quantity }} @ {{ ns()->currency->define( $product->unit_price ) }}</div>
            </td>
            <td class="right">{{ ns()->currency->define( $product->total_price ) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tbody>
        @if( $pos_vat === 'products_vat' )
            @if( $prefered_price === 'net_prices' )
            <tr>
                <td class="bold">{{ __( 'Product Taxes' ) }}</td>
                <td class="right">{{ ns()->currency->define( $order->products_tax_value ) }}</td>
            </tr>
            @else
            <tr>
                <td class="bold">{{ __( 'Product Taxes (Included)' ) }}</td>
                <td class="right">{{ ns()->currency->define( $order->products_tax_value ) }}</td>
            </tr>
            @endif
        @endif
        <tr>
            <td class="bold">{{ __( 'Sub Total' ) }}</td>
            <td class="right">{{ ns()->currency->define( $order->subtotal ) }}</td>
        </tr>
        @if ( $order->discount > 0 )
        <tr>
            <td class="bold">
                {{ __( 'Discount' ) }}
                @if ( $order->discount_type === 'percentage' )
                ({{ $order->discount_percentage }}%)
                @endif
            </td>
            <td class="right">{{ ns()->currency->define( $order->discount ) }}</td>
        </tr>
        @endif
        @if ( $order->total_coupons > 0 )
        <tr>
            <td class="bold">{{ __( 'Coupons' ) }}</td>
            <td class="right">{{ ns()->currency->define( $order->total_coupons ) }}</td>
        </tr>
        @endif
        @if ( ns()->option->get( 'ns_invoice_display_tax_breakdown' ) === 'yes' )
            @foreach( $order->taxes as $tax )
            <tr>
                <td class="bold">{{ $tax->tax_name }} — {{ $order->tax_type === 'inclusive' ? __( 'Inclusive' ) : __( 'Exclusive' ) }}</td>
                <td class="right">{{ ns()->currency->define( $tax->tax_value ) }}</td>
            </tr>
            @endforeach
            @if ( $order->products_tax_value > 0 )
            <tr>
                <td class="bold">{{ $order->tax_type === 'inclusive' ? __( 'Inclusive Product Taxes' ) : __( 'Exclusive Product Taxes' ) }}</td>
                <td class="right">{{ ns()->currency->define( $order->products_tax_value ) }}</td>
            </tr>
            @endif
        @else
            @if ( $order->tax_value > 0 )
            <tr>
                <td class="bold">{{ $order->tax_group?->name ?? __( 'Unassigned Tax Group' ) }} ({{ $order->tax_type === 'inclusive' ? __( 'Inclusive' ) : '' }})</td>
                <td class="right">{{ ns()->currency->define( $order->tax_value ) }}</td>
            </tr>
            @endif
        @endif
        @if ( $order->shipping > 0 )
        <tr>
            <td class="bold">{{ __( 'Shipping' ) }}</td>
            <td class="right">{{ ns()->currency->define( $order->shipping ) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td class="bold pad-top">{{ __( 'Total' ) }}</td>
            <td class="bold right pad-top">{{ ns()->currency->define( $order->total ) }}</td>
        </tr>
        @foreach( $order->payments as $payment )
        <tr>
            <td class="bold">{{ $paymentTypes[ $payment[ 'identifier' ] ] ?? __( 'Unknown Payment' ) }}</td>
            <td class="right">{{ ns()->currency->define( $payment[ 'value' ] ) }}</td>
        </tr>
        @endforeach
        <tr>
            <td class="bold">{{ __( 'Paid' ) }}</td>
            <td class="right">{{ ns()->currency->define( $order->tendered ) }}</td>
        </tr>
        @if ( in_array( $order->payment_status, [ 'refunded', 'partially_refunded' ]) )
            @foreach( $order->refund as $refund )
            <tr>
                <td class="bold">{{ __( 'Refunded' ) }}</td>
                <td class="right">{{ ns()->currency->define( - $refund->total ) }}</td>
            </tr>
            @endforeach
        @endif
        @switch( $order->payment_status )
            @case( Order::PAYMENT_PAID )
            <tr>
                <td class="bold">{{ __( 'Change' ) }}</td>
                <td class="right">{{ ns()->currency->define( $order->change ) }}</td>
            </tr>
            @break
            @case( Order::PAYMENT_PARTIALLY )
            <tr>
                <td class="bold">{{ __( 'Due' ) }}</td>
                <td class="right">{{ ns()->currency->define( abs( $order->change ) ) }}</td>
            </tr>
            @break
        @endswitch
    </tbody>
</table>
@if( $order->note_visibility === 'visible' )
<div class="note">
    <strong>{{ __( 'Note: ' ) }}</strong> {{ $order->note }}
</div>
@endif
<div class="footer">
    {{ ns()->option->get( 'ns_invoice_receipt_footer' ) }}
</div>
@includeWhen( request()->query( 'autoprint' ) === 'true', '/pages/dashboard/orders/templates/_autoprint' )
