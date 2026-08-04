<?php

namespace Tests\Feature;

use App\Models\Order;
use Tests\TestCase;
use Tests\Traits\WithAuthentication;

class RawbtReceiptTest extends TestCase
{
    use WithAuthentication;

    public function test_receipt_is_still_rendered_as_html_by_default()
    {
        $this->attemptAuthenticate();

        $order = Order::with( [ 'customer', 'products', 'shipping_address', 'billing_address', 'user', 'settings', 'tax_group' ] )->latest()->first();

        $this->assertNotNull( $order, 'No order found to test against.' );

        $response = $this->get( '/dashboard/orders/receipt/' . $order->id );

        $response->assertStatus( 200 );
        $response->assertSee( 'SALES RECEIPT', false );
    }

    public function test_receipt_is_rendered_as_escpos_stream_for_rawbt_format()
    {
        $this->attemptAuthenticate();

        $order = Order::with( [ 'customer', 'products', 'shipping_address', 'billing_address', 'user', 'settings', 'tax_group' ] )->latest()->first();

        $this->assertNotNull( $order, 'No order found to test against.' );

        $response = $this->get( '/dashboard/orders/receipt/' . $order->id . '?format=rawbt' );

        $response->assertStatus( 200 );
        $response->assertHeader( 'Content-Type', 'application/octet-stream' );

        $content = $response->getContent();

        // ESC @ initialization command
        $this->assertStringStartsWith( "\x1B\x40", $content );
        // plain text is embedded in the stream
        $this->assertStringContainsString( 'SALES RECEIPT', $content );
        $this->assertStringContainsString( 'TOTAL', $content );
        // cut command at the end (GS V 66 0)
        $this->assertStringEndsWith( "\x1D\x56\x42\x00", $content );
    }

    public function test_receipt_is_rendered_as_plain_text_for_rawbt_text_format()
    {
        $this->attemptAuthenticate();

        $order = Order::with( [ 'customer', 'products', 'shipping_address', 'billing_address', 'user', 'settings', 'tax_group' ] )->latest()->first();

        $this->assertNotNull( $order, 'No order found to test against.' );

        $response = $this->get( '/dashboard/orders/receipt/' . $order->id . '?format=rawbt-text' );

        $response->assertStatus( 200 );
        $response->assertHeader( 'Content-Type', 'text/plain; charset=utf-8' );
        $response->assertSee( 'SALES RECEIPT', false );
        $response->assertSee( 'TOTAL', false );
        $response->assertSee( ns()->option->get( 'ns_store_name' ), false );
        $response->assertDontSee( '<html', false );
    }
}
