<?php
use App\Classes\Hook;

$isThermal58 = ns()->option->get( 'ns_invoice_receipt_template' ) === 'thermal_58';
$pos_vat = $order->settings?->where( 'key', 'ns_pos_vat' )->first()?->value;
$width = $isThermal58 ? 32 : 42;
$rightWidth = 13;
$leftWidth = $width - $rightWidth;
$storeName = ns()->option->get( 'ns_store_name' );
$storeAddress = ns()->option->get( 'ns_store_address' );
$storeTin = ns()->option->get( 'ns_store_tin' );

$pad = function ( $text, $length, $side = STR_PAD_RIGHT ) {
    $text = (string) $text;
    $padLength = max( 0, $length - mb_strlen( $text ) );

    if ( $padLength === 0 ) {
        return mb_substr( $text, 0, $length );
    }

    $spaces = str_repeat( ' ', $padLength );

    if ( $side === STR_PAD_LEFT ) {
        return $spaces . $text;
    }

    if ( $side === STR_PAD_BOTH ) {
        $leftSpaces = (int) floor( $padLength / 2 );

        return str_repeat( ' ', $leftSpaces ) . $text . str_repeat( ' ', $padLength - $leftSpaces );
    }

    return $text . $spaces;
};

$center = fn( $text ) => $pad( $text, $width, STR_PAD_BOTH );
$divider = str_repeat( '-', $width );
$row = function ( $left, $right = '', $reserved = null ) use ( $width, $rightWidth, $leftWidth, $pad ) {
    $left = (string) $left;
    $right = (string) $right;
    $available = $width;

    if ( $right !== '' ) {
        $available = $reserved ?: $rightWidth;
        $left = mb_substr( $left, 0, $width - $available );

        return $pad( $left, $width - $available ) . $pad( $right, $available, STR_PAD_LEFT );
    }

    return $pad( $left, $width );
};

$money = fn( $amount ) => trim( preg_replace( '/[^\x20-\x7E]/u', 'P', ns()->currency->define( $amount ) ) );

$columnA = trim( strip_tags( str_replace( [ '<br>', '<br />', '<br/>' ], "\n", $ordersService->orderTemplateMapping( 'ns_invoice_receipt_column_a', $order ) ) ) );
$columnB = trim( strip_tags( str_replace( [ '<br>', '<br />', '<br/>' ], "\n", $ordersService->orderTemplateMapping( 'ns_invoice_receipt_column_b', $order ) ) ) );

$lines = [];

if ( empty( $storeLogo = ns()->option->get( 'ns_invoice_receipt_logo' ) ) ) {
    $lines[] = $center( $storeName );
} else {
    $lines[] = $center( $storeName );
}

if ( $storeAddress ) {
    $lines[] = $center( $storeAddress );
}

if ( $storeTin ) {
    $lines[] = $center( sprintf( 'TIN: %s - Non-VAT', $storeTin ) );
}

$lines[] = '';
$lines[] = $center( strtoupper( __( 'SALES RECEIPT' ) ) );
$lines[] = '';

foreach ( preg_split( '/\R/', $columnA ) ?: [] as $line ) {
    if ( trim( $line ) !== '' ) {
        $lines[] = $center( trim( $line ) );
    }
}

foreach ( preg_split( '/\R/', $columnB ) ?: [] as $line ) {
    if ( trim( $line ) !== '' ) {
        $lines[] = $center( trim( $line ) );
    }
}

$lines[] = $divider;
$lines[] = $row( __( 'Item' ), __( 'Amount' ) );
$lines[] = $divider;

foreach ( Hook::filter( 'ns-receipt-products', $order->combinedProducts ) as $product ) {
    $lines[] = $row( $product->name, '' );
    $lines[] = $row(
        sprintf( ' %s x %s', $product->quantity, $money( $product->unit_price ) ),
        $money( $product->total_price )
    );
}

$lines[] = $divider;

if ( $pos_vat === 'products_vat' ) {
    $lines[] = $row( __( 'Product Taxes' ), $money( $order->products_tax_value ) );
}

$lines[] = $row( __( 'Subtotal' ), $money( $order->subtotal ) );

if ( $order->discount > 0 ) {
    $lines[] = $row(
        $order->discount_type === 'percentage'
            ? sprintf( '%s (%s%%)', __( 'Discount' ), $order->discount_percentage )
            : __( 'Discount' ),
        '-' . $money( $order->discount )
    );
}

if ( $order->total_coupons > 0 ) {
    $lines[] = $row( __( 'Coupons' ), '-' . $money( $order->total_coupons ) );
}

if ( ns()->option->get( 'ns_invoice_display_tax_breakdown' ) === 'yes' ) {
    foreach ( $order->taxes as $tax ) {
        $lines[] = $row( $tax->tax_name, $money( $tax->tax_value ) );
    }

    if ( $order->products_tax_value > 0 ) {
        $lines[] = $row( __( 'Prod. Tax' ), $money( $order->products_tax_value ) );
    }
} elseif ( $order->tax_value > 0 ) {
    $lines[] = $row( $order->tax_group?->name ?? __( 'Tax' ), $money( $order->tax_value ) );
}

if ( $order->shipping > 0 ) {
    $lines[] = $row( __( 'Shipping' ), $money( $order->shipping ) );
}

$lines[] = '';
$lines[] = $row( __( 'TOTAL' ), $money( $order->total ) );
$lines[] = $divider;
$lines[] = __( 'Payments' );
$lines[] = '';

foreach ( $order->payments as $payment ) {
    $lines[] = $row( $paymentTypes[ $payment[ 'identifier' ] ] ?? __( 'Unknown' ), $money( $payment[ 'value' ] ) );
}

$lines[] = $row( __( 'Total Paid' ), $money( $order->tendered ) );

if ( in_array( $order->payment_status, [ 'refunded', 'partially_refunded' ] ) ) {
    foreach ( $order->refund as $refund ) {
        $lines[] = $row( __( 'Refunded' ), '-' . $money( $refund->total ) );
    }
}

switch ( $order->payment_status ) {
    case App\Models\Order::PAYMENT_PAID:
        $lines[] = $row( __( 'Change' ), $money( $order->change ) );
        break;
    case App\Models\Order::PAYMENT_PARTIALLY:
        $lines[] = $row( __( 'Balance Due' ), $money( abs( $order->change ) ) );
        break;
}

if ( $order->note_visibility === 'visible' && $order->note ) {
    $lines[] = '';
    $lines[] = __( 'Note:' ) . ' ' . $order->note;
}

$lines[] = '';

foreach ( preg_split( '/\R/', ns()->option->get( 'ns_invoice_receipt_footer' ) ) ?: [] as $line ) {
    if ( trim( $line ) !== '' ) {
        $lines[] = $center( trim( $line ) );
    }
}

$lines[] = '';
$lines[] = '';
$lines[] = '';
$lines[] = '';
?>
@php
echo implode( "\n", $lines );
@endphp
