<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\WithAuthentication;

class PosHeartbeatTest extends TestCase
{
    use WithAuthentication;

    public function test_heartbeat_requires_authentication()
    {
        $this->getJson( 'api/pos/heartbeat' )->assertStatus( 401 );
    }

    public function test_heartbeat_returns_server_time()
    {
        $this->attemptAuthenticate();

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->getJson( 'api/pos/heartbeat' );

        $response->assertOk();
        $response->assertJsonStructure( [ 'status', 'time', 'timestamp' ] );
        $this->assertIsInt( $response->json( 'timestamp' ) );
    }
}
