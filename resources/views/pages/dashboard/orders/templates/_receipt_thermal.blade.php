<?php
use App\Models\Order;
use App\Classes\Hook;
use Illuminate\Support\Facades\View;

$prefered_price = $order->settings?->where('key', 'ns_pos_prefered_price')->first()?->value;
$pos_vat = $order->settings?->where('key', 'ns_pos_vat')->first()?->value;
$isThermal58 = ns()->option->get('ns_invoice_receipt_template') === 'thermal_58';
$storeName = ns()->option->get('ns_store_name');
$storeLogo = ns()->option->get('ns_invoice_receipt_logo');
$storeAddress = ns()->option->get('ns_store_address');
$storeTin = ns()->option->get('ns_store_tin');
?>
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        line-height: 1.35;
        color: #000;
        width: {{ $isThermal58 ? '48mm' : '76mm' }};
        margin: 0 auto;
        padding: 8px 3px 28px;
    }
    .header { text-align: center; margin-bottom: 6px; }
    .header h2 { font-size: 14px; font-weight: bold; margin-bottom: 2px; letter-spacing: 0.5px; }
    .header img { max-width: 70%; height: auto; margin-bottom: 4px; }
    .header .info { font-size: 9px; line-height: 1.3; word-break: break-word; }
    .receipt-type {
        text-align: center;
        font-size: 11px;
        font-weight: bold;
        letter-spacing: 2px;
        margin: 4px 0 2px;
        border-top: 1px dashed #000;
        border-bottom: 1px dashed #000;
        padding: 4px 0;
    }
    .divider { border: none; border-top: 1px dashed #000; margin: 4px 0; }
    .divider-solid { border: none; border-top: 1px solid #000; margin: 4px 0; }
    .columns { display: flex; justify-content: space-between; font-size: 9px; }
    .columns .col { width: 48%; }
    table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 10px; }
    thead th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 2px; text-align: left; font-size: 9px; font-weight: bold; }
    thead th:first-child { width: 60%; }
    thead th.right { width: 40%; text-align: right; }
    td { padding: 2px; vertical-align: top; }
    td.right { text-align: right; white-space: nowrap; }
    td.bold { font-weight: bold; }
    td.pad-top { padding-top: 4px; }
    td.pad-bottom { padding-bottom: 4px; }
    .total-row td { border-top: 1px solid #000; padding-top: 3px; font-weight: bold; }
    .total-row td.right { font-size: 12px; }
    .payment-row td { border-top: 1px dashed #000; padding-top: 3px; }
    .footer { text-align: center; font-size: 9px; margin-top: 8px; white-space: pre-line; }
    .note { text-align: center; font-size: 9px; margin: 6px 0; }
    .product-line { margin-bottom: 1px; }
    .product-name { font-size: 10px; word-break: break-word; }
    .product-meta { font-size: 9px; color: #333; }
    .spacer { line-height: 1.5; }
</style>
<div class="spacer">&nbsp;</div>
<div class="header">
    @if (empty($storeLogo))
    <h2>{{ $storeName }}</h2>
    @else
    <img src="{{ $storeLogo }}" alt="{{ $storeName }}">
    @endif
    @if ($storeAddress)
    <div class="info">{{ $storeAddress }}</div>
    @endif
    @if ($storeTin)
    <div class="info">{{ __('TIN:') }} {{ $storeTin }}@if (ns()->option->isVatEnabled()) &mdash; {{ ns()->option->getVatStatusLabel() }}@endif</div>
    @endif
</div>
<div class="receipt-type">{{ strtoupper(__('SALES RECEIPT')) }}</div>
<div class="columns">
    <div class="col">{!! nl2br($ordersService->orderTemplateMapping('ns_invoice_receipt_column_a', $order)) !!}</div>
    <div class="col">{!! nl2br($ordersService->orderTemplateMapping('ns_invoice_receipt_column_b', $order)) !!}</div>
</div>
<hr class="divider">
<table>
    <thead>
        <tr>
            <th>{{ __('Item') }}</th>
            <th class="right">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach (Hook::filter('ns-receipt-products', $order->combinedProducts) as $product)
        <tr>
            <td>
                <div class="product-name">{{ $product->name }}</div>
                <div class="product-meta">{{ $product->quantity }} &times; {{ ns()->currency->define($product->unit_price) }}</div>
            </td>
            <td class="right">{{ ns()->currency->define($product->total_price) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tbody>
        @if ($pos_vat === 'products_vat')
            @if ($prefered_price === 'net_prices')
            <tr>
                <td class="bold">{{ __('Product Taxes') }}</td>
                <td class="right">{{ ns()->currency->define($order->products_tax_value) }}</td>
            </tr>
            @else
            <tr>
                <td class="bold">{{ __('Tax (incl.)') }}</td>
                <td class="right">{{ ns()->currency->define($order->products_tax_value) }}</td>
            </tr>
            @endif
        @endif
        <tr>
            <td class="bold">{{ __('Subtotal') }}</td>
            <td class="right">{{ ns()->currency->define($order->subtotal) }}</td>
        </tr>
        @if ($order->discount > 0)
        <tr>
            <td class="bold">
                {{ __('Discount') }}
                @if ($order->discount_type === 'percentage')
                ({{ $order->discount_percentage }}%)
                @endif
            </td>
            <td class="right">&minus; {{ ns()->currency->define($order->discount) }}</td>
        </tr>
        @endif
        @if ($order->total_coupons > 0)
        <tr>
            <td class="bold">{{ __('Coupons') }}</td>
            <td class="right">&minus; {{ ns()->currency->define($order->total_coupons) }}</td>
        </tr>
        @endif
        @if (ns()->option->get('ns_invoice_display_tax_breakdown') === 'yes')
            @foreach ($order->taxes as $tax)
            <tr>
                <td class="bold">{{ $tax->tax_name }} &mdash; {{ $order->tax_type === 'inclusive' ? __('Incl.') : __('Excl.') }}</td>
                <td class="right">{{ ns()->currency->define($tax->tax_value) }}</td>
            </tr>
            @endforeach
            @if ($order->products_tax_value > 0)
            <tr>
                <td class="bold">{{ $order->tax_type === 'inclusive' ? __('Prod. Tax (incl.)') : __('Prod. Tax (excl.)') }}</td>
                <td class="right">{{ ns()->currency->define($order->products_tax_value) }}</td>
            </tr>
            @endif
        @else
            @if ($order->tax_value > 0)
            <tr>
                <td class="bold">{{ $order->tax_group?->name ?? __('Tax') }} @if ($order->tax_type === 'inclusive')({{ __('incl.') }})@endif</td>
                <td class="right">{{ ns()->currency->define($order->tax_value) }}</td>
            </tr>
            @endif
        @endif
        @if ($order->shipping > 0)
        <tr>
            <td class="bold">{{ __('Shipping') }}</td>
            <td class="right">{{ ns()->currency->define($order->shipping) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td class="pad-top">{{ __('TOTAL') }}</td>
            <td class="right pad-top">{{ ns()->currency->define($order->total) }}</td>
        </tr>
        <tr class="payment-row">
            <td colspan="2" style="font-size: 9px; font-weight: bold; padding-top: 4px;">{{ __('Payments') }}</td>
        </tr>
        @foreach ($order->payments as $payment)
        <tr>
            <td>{{ $paymentTypes[$payment['identifier']] ?? __('Unknown') }}</td>
            <td class="right">{{ ns()->currency->define($payment['value']) }}</td>
        </tr>
        @endforeach
        <tr>
            <td class="bold">{{ __('Total Paid') }}</td>
            <td class="right">{{ ns()->currency->define($order->tendered) }}</td>
        </tr>
        @if (in_array($order->payment_status, ['refunded', 'partially_refunded']))
            @foreach ($order->refund as $refund)
            <tr>
                <td class="bold">{{ __('Refunded') }}</td>
                <td class="right">&minus; {{ ns()->currency->define($refund->total) }}</td>
            </tr>
            @endforeach
        @endif
        @switch ($order->payment_status)
            @case (Order::PAYMENT_PAID)
            <tr>
                <td class="bold">{{ __('Change') }}</td>
                <td class="right">{{ ns()->currency->define($order->change) }}</td>
            </tr>
            @break
            @case (Order::PAYMENT_PARTIALLY)
            <tr>
                <td class="bold">{{ __('Balance Due') }}</td>
                <td class="right">{{ ns()->currency->define(abs($order->change)) }}</td>
            </tr>
            @break
        @endswitch
    </tbody>
</table>
@if ($order->note_visibility === 'visible')
<div class="note">
    <strong>{{ __('Note:') }}</strong> {{ $order->note }}
</div>
@endif
<div class="footer">
    {{ ns()->option->get('ns_invoice_receipt_footer') }}
</div>
<div class="spacer">&nbsp;</div>
@includeWhen(request()->query('autoprint') === 'true', '/pages/dashboard/orders/templates/_autoprint')
