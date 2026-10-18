<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\TestService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuthentication;
use Tests\Traits\WithOrderTest;

class OrderIdempotencyTest extends TestCase
{
    use WithAuthentication, WithOrderTest;

    public function test_duplicate_submission_with_same_checkout_uuid_creates_single_order()
    {
        $this->attemptAuthenticate();

        /**
         * @var TestService $testService
         */
        $testService = app()->make( TestService::class );

        $checkoutUuid = 'checkout-key-' . Str::uuid();

        $order = $testService->prepareOrder();
        $order[ 'uuid' ] = $checkoutUuid;

        $firstResponse = $this->attemptCreateOrder( $order );
        $secondResponse = $this->attemptCreateOrder( $order );

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $this->assertEquals(
            $firstResponse->json( 'data.order.id' ),
            $secondResponse->json( 'data.order.id' ),
            'The second submission should return the order created by the first submission.'
        );

        $this->assertEquals(
            1,
            Order::where( 'uuid', $checkoutUuid )->count(),
            'A single order should be created for a given checkout uuid.'
        );
    }

    public function test_checkout_uuid_cannot_be_stored_twice()
    {
        $checkoutUuid = 'checkout-key-' . Str::uuid();

        $order = new Order;
        $order->code = 'duplicate-uuid-first';
        $order->type = 'takeaway';
        $order->payment_status = Order::PAYMENT_PAID;
        $order->customer_id = 1;
        $order->author_id = 1;
        $order->uuid = $checkoutUuid;
        $order->save();

        $this->expectException( QueryException::class );

        $duplicate = new Order;
        $duplicate->code = 'duplicate-uuid-second';
        $duplicate->type = 'takeaway';
        $duplicate->payment_status = Order::PAYMENT_PAID;
        $duplicate->customer_id = 1;
        $duplicate->author_id = 1;
        $duplicate->uuid = $checkoutUuid;
        $duplicate->save();
    }

    public function test_orders_without_checkout_uuid_are_still_allowed()
    {
        $this->attemptAuthenticate();

        /**
         * @var TestService $testService
         */
        $testService = app()->make( TestService::class );

        $order = $testService->prepareOrder();

        $firstResponse = $this->attemptCreateOrder( $order );
        $secondResponse = $this->attemptCreateOrder( $order );

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $this->assertNotEquals(
            $firstResponse->json( 'data.order.id' ),
            $secondResponse->json( 'data.order.id' ),
            'Without a checkout uuid, each submission should create its own order.'
        );
    }
}
