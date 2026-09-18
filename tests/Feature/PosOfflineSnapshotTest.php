<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\WithAuthentication;

class PosOfflineSnapshotTest extends TestCase
{
    use WithAuthentication;

    public function test_snapshot_requires_authentication()
    {
        $this->getJson( 'api/pos/offline-snapshot' )->assertStatus( 401 );
    }

    public function test_snapshot_returns_catalog_payload()
    {
        $this->attemptAuthenticate();

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->getJson( 'api/pos/offline-snapshot' );

        $response->assertOk();
        $response->assertJsonStructure( [
            'generated_at',
            'server_time',
            'products',
            'categories',
            'payment_types',
            'customers',
            'register',
            'options',
        ] );

        $products = collect( $response->json( 'products' ) );
        $this->assertNotEmpty( $products );
        $this->assertTrue(
            $products->contains( fn( $product ) => ! empty( $product[ 'unit_quantities' ] ) )
        );

        $productWithUnits = $products->first( fn( $product ) => ! empty( $product[ 'unit_quantities' ] ) );
        $this->assertArrayHasKey( 'sale_price', $productWithUnits[ 'unit_quantities' ][ 0 ] );
        $this->assertArrayHasKey( 'unit', $productWithUnits[ 'unit_quantities' ][ 0 ] );
        $this->assertArrayHasKey( 'barcode', $productWithUnits );
        $this->assertArrayHasKey( 'category_id', $productWithUnits );
        $this->assertArrayHasKey( 'pinned', $productWithUnits );
    }
}
