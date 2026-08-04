<?php
use App\Classes\Hook;
use App\Models\PaymentType;
use App\Services\OrdersService;

$ordersService = app()->make(OrdersService::class);
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
    .total-row td { border-top: 1px solid #000; padding-top: 3px; font-weight: bold; }
    .total-row td.right { font-size: 12px; }
    .footer { text-align: center; font-size: 9px; margin-top: 8px; white-space: pre-line; }
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
    <div class="info">{{ __('TIN:') }} {{ $storeTin }} &mdash; {{ __('Non-VAT') }}</div>
    @endif
</div>
<div class="receipt-type">{{ strtoupper(__('REFUND RECEIPT')) }}</div>
<div class="columns">
    <div class="col">{!! nl2br($ordersService->orderTemplateMapping('ns_invoice_receipt_column_a', $refund->order)) !!}</div>
    <div class="col">{!! nl2br($ordersService->orderTemplateMapping('ns_invoice_receipt_column_b', $refund->order)) !!}</div>
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
        <?php $products = Hook::filter('ns-refund-receipt-products', $refund->refunded_products); ?>
        @foreach ($products as $product)
        <tr>
            <td>
                <div class="product-name">{{ $product->product->name }}</div>
                <div class="product-meta">{{ $product->quantity }} &times; {{ $product->unit->name ?? '' }} &mdash; {{ $ordersService->getRefundedOrderProductLabel($product->condition) }}</div>
            </td>
            <td class="right">{{ Currency::raw($product->total_price - $product->tax_value) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tbody>
        @if ($refund->tax_value > 0)
        <tr>
            <td class="bold">{{ __('Subtotal') }}</td>
            <td class="right">{{ ns()->currency->define($products->map(fn($product) => Currency::raw($product->total_price - $product->tax_value))->sum()) }}</td>
        </tr>
        <tr>
            <td class="bold">{{ __('Tax') }}</td>
            <td class="right">{{ ns()->currency->define($refund->tax_value) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td class="pad-top">{{ __('TOTAL REFUND') }}</td>
            <td class="right pad-top">{{ ns()->currency->define($refund->total) }}</td>
        </tr>
        @if ($refund->shipping > 0)
        <tr>
            <td class="bold">{{ __('Shipping') }}</td>
            <td class="right">{{ ns()->currency->define($refund->shipping) }}</td>
        </tr>
        @endif
        <?php
        $paymentType = PaymentType::where('identifier', $refund->payment_method)->first();
        $paymentName = $paymentType instanceof PaymentType ? $paymentType->label : __('Unknown Payment');
        ?>
        <tr>
            <td class="bold">{{ __('Refunded Via') }}</td>
            <td class="right">{{ $paymentName }} &mdash; {{ ns()->currency->define($refund->total) }}</td>
        </tr>
    </tbody>
</table>
<div class="footer">
    {{ ns()->option->get('ns_invoice_receipt_footer') }}
</div>
<div class="spacer">&nbsp;</div>
@includeWhen(request()->query('autoprint') === 'true', '/pages/dashboard/orders/templates/_autoprint')
