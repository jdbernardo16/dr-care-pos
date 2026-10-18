<?php

namespace App\Services;

use App\Classes\Hook;
use App\Models\Order;

class RawbtPrintService
{
    /**
     * Printable width in dots for a 58mm printer (48mm @ 203dpi).
     */
    private int $width = 384;

    /**
     * Build a complete ESC/POS receipt for the provided order.
     */
    public function makeReceipt( Order $order, OrdersService $ordersService, array|object $paymentTypes ): string
    {
        $out = $this->cmd( 0x1B, 0x40 ); // ESC @ — initialize printer
        $out .= $this->feed( 2 );

        $logoUrl = ns()->option->get( 'ns_invoice_receipt_logo' ) ?: asset( 'images/doctorcare.jpg' );
        $storeName = ns()->option->get( 'ns_store_name' );
        $storeAddress = ns()->option->get( 'ns_store_address' );
        $storeTin = ns()->option->get( 'ns_store_tin' );

        if ( $raster = $this->rasterize( $logoUrl ) ) {
            $out .= $raster;
            $out .= $this->feed( 1 );
        }

        $out .= $this->line( $storeName, 'center', bold: true, size: 0x10 );

        if ( $storeAddress ) {
            $out .= $this->line( $storeAddress, 'center' );
        }

        if ( $storeTin ) {
            $out .= $this->line( 'TIN: ' . $storeTin . ( ns()->option->isVatEnabled() ? ' - ' . ns()->option->getVatStatusLabel() : '' ), 'center' );
        }

        $out .= $this->feed( 1 );
        $out .= $this->line( strtoupper( __( 'Sales Receipt' ) ), 'center', bold: true );
        $out .= $this->divider( 'center' );
        $out .= $this->feed( 1 );

        foreach ( [ 'ns_invoice_receipt_column_a', 'ns_invoice_receipt_column_b' ] as $column ) {
            foreach ( $this->splitLines( $ordersService->orderTemplateMapping( $column, $order ) ) as $line ) {
                $out .= $this->line( $line, 'center' );
            }
        }

        $out .= $this->feed( 1 );
        $out .= $this->divider();
        $out .= $this->row( __( 'Item' ), __( 'Amount' ), bold: true );
        $out .= $this->divider();

        foreach ( Hook::filter( 'ns-receipt-products', $order->combinedProducts ) as $product ) {
            $out .= $this->line( $this->clean( $product->name ), 'left' );
            $out .= $this->row(
                ' ' . $product->quantity . ' x ' . $this->money( $product->unit_price ),
                $this->money( $product->total_price )
            );
        }

        $out .= $this->divider();

        $pos_vat = $order->settings?->where( 'key', 'ns_pos_vat' )->first()?->value;
        $prefered_price = $order->settings?->where( 'key', 'ns_pos_prefered_price' )->first()?->value;

        if ( $pos_vat === 'products_vat' ) {
            $out .= $this->row( __( 'Product Taxes' ), $this->money( $order->products_tax_value ) );
        }

        $out .= $this->row( __( 'Subtotal' ), $this->money( $order->subtotal ) );

        if ( $order->discount > 0 ) {
            $out .= $this->row(
                $order->discount_type === 'percentage'
                    ? sprintf( '%s (%s%%)', __( 'Discount' ), $order->discount_percentage )
                    : __( 'Discount' ),
                '-' . $this->money( $order->discount )
            );
        }

        if ( $order->total_coupons > 0 ) {
            $out .= $this->row( __( 'Coupons' ), '-' . $this->money( $order->total_coupons ) );
        }

        if ( ns()->option->get( 'ns_invoice_display_tax_breakdown' ) === 'yes' ) {
            foreach ( $order->taxes as $tax ) {
                $out .= $this->row( $tax->tax_name, $this->money( $tax->tax_value ) );
            }

            if ( $order->products_tax_value > 0 ) {
                $out .= $this->row( __( 'Prod. Tax' ), $this->money( $order->products_tax_value ) );
            }
        } elseif ( $order->tax_value > 0 ) {
            $out .= $this->row( $order->tax_group?->name ?? __( 'Tax' ), $this->money( $order->tax_value ) );
        }

        if ( $order->shipping > 0 ) {
            $out .= $this->row( __( 'Shipping' ), $this->money( $order->shipping ) );
        }

        $out .= $this->feed( 1 );
        $out .= $this->row( __( 'TOTAL' ), $this->money( $order->total ), bold: true );
        $out .= $this->divider();
        $out .= $this->feed( 1 );

        $out .= $this->line( __( 'Payments' ), 'left', bold: true );
        $out .= $this->feed( 1 );

        foreach ( $order->payments as $payment ) {
            $out .= $this->row( $paymentTypes[ $payment[ 'identifier' ] ] ?? __( 'Unknown' ), $this->money( $payment[ 'value' ] ) );
        }

        $out .= $this->row( __( 'Total Paid' ), $this->money( $order->tendered ) );

        if ( in_array( $order->payment_status, [ 'refunded', 'partially_refunded' ] ) ) {
            foreach ( $order->refund as $refund ) {
                $out .= $this->row( __( 'Refunded' ), '-' . $this->money( $refund->total ) );
            }
        }

        switch ( $order->payment_status ) {
            case Order::PAYMENT_PAID:
                $out .= $this->row( __( 'Change' ), $this->money( $order->change ) );
                break;
            case Order::PAYMENT_PARTIALLY:
                $out .= $this->row( __( 'Balance Due' ), $this->money( abs( $order->change ) ) );
                break;
        }

        if ( $order->note_visibility === 'visible' && $order->note ) {
            $out .= $this->feed( 1 );
            $out .= $this->line( __( 'Note:' ) . ' ' . $order->note, 'left' );
        }

        $out .= $this->feed( 1 );

        foreach ( $this->splitLines( ns()->option->get( 'ns_invoice_receipt_footer' ) ) as $line ) {
            $out .= $this->line( $line, 'center' );
        }

        $out .= $this->feed( 4 );
        $out .= $this->cmd( 0x1D, 0x56, 0x42, 0x00 ); // GS V 66 0 — partial cut

        return $out;
    }

    /**
     * Render a centered, monochrome raster image (GS v 0) at
     * full printable width, or null when the image can't be loaded.
     */
    private function rasterize( string $url ): ?string
    {
        if ( ! extension_loaded( 'gd' ) ) {
            return null;
        }

        if ( ! preg_match( '#^https?://#', $url ) ) {
            $url = url( $url );
        }

        try {
            $path = parse_url( $url, PHP_URL_PATH );
            $localPath = public_path( ltrim( (string) $path, '/' ) );

            /**
             * Read local files directly from disk: fetching them over
             * HTTP would deadlock single-threaded dev servers (the
             * request would wait on itself).
             */
            if ( is_file( $localPath ) ) {
                $data = file_get_contents( $localPath );
            } elseif ( preg_match( '#^https?://#', $url ) ) {
                $context = stream_context_create( [ 'http' => [ 'timeout' => 3 ] ] );
                $data = @file_get_contents( $url, false, $context );
            } else {
                return null;
            }

            if ( $data === false ) {
                return null;
            }

            $source = imagecreatefromstring( $data );

            if ( ! $source ) {
                return null;
            }

            $sourceWidth = imagesx( $source );
            $sourceHeight = imagesy( $source );

            $targetWidth = $this->width;
            $targetHeight = min( (int) round( $sourceHeight * $targetWidth / $sourceWidth ), 220 );

            $image = imagecreatetruecolor( $targetWidth, $targetHeight );
            imagealphablending( $image, false );
            imagesavealpha( $image, true );
            imagecopyresampled( $image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight );

            $bytesPerLine = (int) ceil( $targetWidth / 8 );
            $data = '';

            for ( $y = 0; $y < $targetHeight; $y++ ) {
                $row = str_repeat( "\x00", $bytesPerLine );

                for ( $x = 0; $x < $targetWidth; $x++ ) {
                    $rgb = imagecolorat( $image, $x, $y );
                    $alpha = ( $rgb >> 24 ) & 0x7F;
                    $r = ( $rgb >> 16 ) & 0xFF;
                    $g = ( $rgb >> 8 ) & 0xFF;
                    $b = $rgb & 0xFF;
                    $gray = (int) ( 0.299 * $r + 0.587 * $g + 0.114 * $b );

                    /**
                     * Print a black dot when the pixel is dark
                     * (or transparent, since transparent areas are
                     * usually outside the logo).
                     */
                    if ( $alpha > 64 || $gray < 128 ) {
                        $row[ intdiv( $x, 8 ) ] = chr( ord( $row[ intdiv( $x, 8 ) ] ) | ( 0x80 >> ( $x % 8 ) ) );
                    }
                }

                $data .= $row;
            }

            imagedestroy( $image );
            imagedestroy( $source );

            $xl = $bytesPerLine & 0xFF;
            $xh = ( $bytesPerLine >> 8 ) & 0xFF;
            $yl = $targetHeight & 0xFF;
            $yh = ( $targetHeight >> 8 ) & 0xFF;

            return $this->cmd( 0x1D, 0x76, 0x30, 0x00, $xl, $xh, $yl, $yh ) . $data;
        } catch ( \Throwable $e ) {
            return null;
        }
    }

    /**
     * Format a full-width left/right row.
     */
    private function row( string $left, string $right, bool $bold = false ): string
    {
        $leftWidth = 20;
        $rightWidth = 12;
        $left = mb_substr( $left, 0, $leftWidth );
        $right = mb_substr( $right, 0, $rightWidth );

        return $this->line(
            str_pad( $left, $leftWidth ) . str_pad( $right, $rightWidth, ' ', STR_PAD_LEFT ),
            'left',
            bold: $bold
        );
    }

    /**
     * Print a single line with the given alignment, weight and size.
     * Lines longer than the printable width are word-wrapped.
     */
    private function line( string $text, string $align = 'left', bool $bold = false, int $size = 0x00 ): string
    {
        $out = '';

        foreach ( $this->wrapLines( $this->clean( $text ) ) as $wrapped ) {
            $alignment = match ( $align ) {
                'center' => 0x01,
                'right' => 0x02,
                default => 0x00,
            };

            $out .= $this->cmd( 0x1B, 0x61, $alignment );

            if ( $bold ) {
                $out .= $this->cmd( 0x1B, 0x45, 0x01 );
            }

            if ( $size !== 0x00 ) {
                $out .= $this->cmd( 0x1D, 0x21, $size );
            }

            $out .= $wrapped . "\n";

            if ( $size !== 0x00 ) {
                $out .= $this->cmd( 0x1D, 0x21, 0x00 );
            }

            if ( $bold ) {
                $out .= $this->cmd( 0x1B, 0x45, 0x00 );
            }
        }

        return $out;
    }

    /**
     * Wrap a string to the printable width (32 chars), splitting
     * on word boundaries.
     */
    private function wrapLines( string $text ): array
    {
        if ( mb_strlen( $text ) <= 32 ) {
            return [ $text ];
        }

        $lines = [];
        $current = '';

        foreach ( preg_split( '/\s+/', $text ) ?: [] as $word ) {
            if ( $current === '' ) {
                $current = $word;
            } elseif ( mb_strlen( $current ) + 1 + mb_strlen( $word ) <= 32 ) {
                $current .= ' ' . $word;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }

        if ( $current !== '' ) {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Dashed separator line.
     */
    private function divider( string $align = 'left' ): string
    {
        return $this->line( str_repeat( '-', 32 ), $align );
    }

    /**
     * Feed n lines.
     */
    private function feed( int $lines ): string
    {
        return $this->cmd( 0x1B, 0x64, $lines );
    }

    /**
     * Build a raw ESC/POS command byte sequence.
     */
    private function cmd( int ...$bytes ): string
    {
        $out = '';

        foreach ( $bytes as $byte ) {
            $out .= chr( $byte );
        }

        return $out;
    }

    /**
     * Format an amount for thermal printing: strips the unprintable
     * currency glyph (renders as 'P') and trims trailing padding.
     */
    private function money( $amount ): string
    {
        return trim( preg_replace( '/[^\x20-\x7E]/u', 'P', ns()->currency->define( $amount ) ) );
    }

    /**
     * Convert text to the printer code page (CP437) and strip
     * anything that can't be represented.
     */
    private function clean( string $text ): string
    {
        $converted = iconv( 'UTF-8', 'CP437//TRANSLIT', $text );

        return $converted === false ? preg_replace( '/[^\x20-\x7E]/u', '?', $text ) : $converted;
    }

    /**
     * Split a template string (may contain <br> or newlines) into
     * trimmed, non-empty lines.
     */
    private function splitLines( ?string $content ): array
    {
        $content = strip_tags( str_replace( [ '<br>', '<br />', '<br/>' ], "\n", $content ?? '' ) );

        return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $content ) ?: [] ), fn( $line ) => $line !== '' ) );
    }
}
