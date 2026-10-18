<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\ProductUnitQuantity;
use App\Services\ProductService;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\WithAuthentication;
use Tests\Traits\WithOrderTest;

/**
 * Reproduces the cashier reports around hold orders:
 *
 * 1. Removing a product from the cart before charging a held order must
 *    not change its stock (a hold never deducted stock in the first place).
 *    Currently a "return-adjustment" credits stock back that was never
 *    deducted, inflating inventory.
 *
 * 2. A product added to the cart while charging a held order must have its
 *    stock deducted. Currently no "sold" transaction is recorded for
 *    products that are inserted during the charge.
 *
 * 3. Reducing the quantity of a product before charging must deduct the
 *    final quantity (not the stale one recorded from the held order).
 */
class HoldOrderRemovedProductTest extends TestCase
{
    use WithAuthentication, WithOrderTest;

    /**
     * @var ProductService
     */
    protected $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app()->make( ProductService::class );
    }

    public function test_removing_product_before_charging_hold_order_keeps_stock()
    {
        $this->attemptAuthenticate();

        [ $first, $second ] = $this->getProductsWithStock();

        $order = $this->createHoldOrder( [
            $this->makeLine( $first, 5 ),
            $this->makeLine( $second, 5 ),
        ] );

        $removedProduct = collect( $order[ 'products' ] )->last();
        $keptProduct = collect( $order[ 'products' ] )->first();

        $removedStockBefore = $this->productService->getQuantity( $removedProduct[ 'product_id' ], $removedProduct[ 'unit_id' ] );

        /**
         * Charge the order with the second product removed from the cart.
         */
        $response = $this->chargeOrder( $order, [ $keptProduct ] );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.order.payment_status', Order::PAYMENT_PAID );

        $removedStockAfter = $this->productService->getQuantity( $removedProduct[ 'product_id' ], $removedProduct[ 'unit_id' ] );

        $this->assertEquals(
            $removedStockBefore,
            $removedStockAfter,
            sprintf(
                'Removing a product from a held order before charging it must not change its stock (was %s, now %s).',
                $removedStockBefore,
                $removedStockAfter
            )
        );

        $phantomHistory = ProductHistory::where( 'order_id', $order[ 'id' ] )
            ->where( 'order_product_id', $removedProduct[ 'id' ] )
            ->where( 'operation_type', ProductHistory::ACTION_ADJUSTMENT_RETURN )
            ->count();

        $this->assertEquals(
            0,
            $phantomHistory,
            'No "return-adjustment" should be recorded for a product that was removed from a hold order before charging.'
        );

        /**
         * The product that remained on the order is expected to have been
         * sold (stock deducted).
         */
        $keptStockAfter = $this->productService->getQuantity( $keptProduct[ 'product_id' ], $keptProduct[ 'unit_id' ] );

        $this->assertTrue(
            $keptStockAfter < $removedStockBefore,
            'The product that remained on the charged order should have been sold and its stock deducted.'
        );
    }

    public function test_product_added_while_charging_hold_order_is_deducted()
    {
        $this->attemptAuthenticate();

        [ $first, $second ] = $this->getProductsWithStock();

        $order = $this->createHoldOrder( [
            $this->makeLine( $first, 3 ),
        ] );

        $keptProduct = collect( $order[ 'products' ] )->first();

        $addedStockBefore = $this->productService->getQuantity( $second->product_id, $second->unit_id );

        /**
         * Charge the order with a new product added to the cart. The added
         * line has no order-product id, mirroring what the POS sends when a
         * product is added to a held order before it's charged.
         */
        $addedLine = [
            'product_id' => $second->product_id,
            'quantity' => 4,
            'unit_price' => $second->sale_price,
            'unit_quantity_id' => $second->id,
            'total_price' => $second->sale_price * 4,
        ];

        $response = $this->chargeOrder( $order, [ $keptProduct, $addedLine ] );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.order.payment_status', Order::PAYMENT_PAID );

        $addedStockAfter = $this->productService->getQuantity( $second->product_id, $second->unit_id );

        $this->assertEquals(
            $addedStockBefore - 4,
            $addedStockAfter,
            sprintf(
                'A product added while charging a hold order must be deducted from stock (was %s, now %s).',
                $addedStockBefore,
                $addedStockAfter
            )
        );
    }

    public function test_quantity_reduced_before_charging_hold_order_deducts_final_quantity()
    {
        $this->attemptAuthenticate();

        [ $first ] = $this->getProductsWithStock();

        $order = $this->createHoldOrder( [
            $this->makeLine( $first, 5 ),
        ] );

        $line = collect( $order[ 'products' ] )->first();

        $stockBefore = $this->productService->getQuantity( $line[ 'product_id' ], $line[ 'unit_id' ] );

        /**
         * The cashier reduces the quantity from 5 to 3 before charging.
         */
        $line[ 'quantity' ] = 3;
        $line[ 'total_price' ] = $line[ 'unit_price' ] * 3;

        $response = $this->chargeOrder( $order, [ $line ] );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.order.payment_status', Order::PAYMENT_PAID );

        $stockAfter = $this->productService->getQuantity( $line[ 'product_id' ], $line[ 'unit_id' ] );

        $this->assertEquals(
            $stockBefore - 3,
            $stockAfter,
            sprintf(
                'Reducing the quantity from 5 to 3 before charging must deduct exactly 3 items (was %s, now %s).',
                $stockBefore,
                $stockAfter
            )
        );

        $soldHistory = ProductHistory::where( 'order_id', $order[ 'id' ] )
            ->where( 'order_product_id', $line[ 'id' ] )
            ->where( 'operation_type', ProductHistory::ACTION_SOLD )
            ->first();

        $this->assertNotNull( $soldHistory, 'The charged order product should have a "sold" stock transaction.' );

        $this->assertEquals(
            3,
            (float) $soldHistory->quantity,
            'The recorded "sold" quantity should reflect the final ordered quantity.'
        );
    }

    public function test_editing_partially_paid_order_does_not_double_adjust_stock()
    {
        $this->attemptAuthenticate();

        [ $first ] = $this->getProductsWithStock();

        $order = $this->createHoldOrder( [
            $this->makeLine( $first, 5 ),
        ] );

        $line = collect( $order[ 'products' ] )->first();

        $stockBefore = $this->productService->getQuantity( $line[ 'product_id' ], $line[ 'unit_id' ] );

        /**
         * Charge the order with a partial payment (about 20% of the total).
         */
        $payment = PaymentType::first();
        $partialPayment = (float) $line[ 'total_price' ] * 0.2;

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'PUT', 'api/orders/' . $order[ 'id' ], [
                'customer_id' => $order[ 'customer_id' ],
                'type' => [ 'identifier' => 'takeaway' ],
                'addresses' => $this->addressPayload(),
                'subtotal' => $line[ 'total_price' ],
                'shipping' => 0,
                'payments' => [
                    [
                        'identifier' => $payment->identifier,
                        'value' => $partialPayment,
                    ],
                ],
                'products' => [ $line ],
            ] );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.order.payment_status', Order::PAYMENT_PARTIALLY );

        /**
         * The whole quantity was deducted when the order became partially
         * paid, so the stock is now stockBefore - 5.
         */
        $this->assertEquals(
            $stockBefore - 5,
            $this->productService->getQuantity( $line[ 'product_id' ], $line[ 'unit_id' ] ),
            'Making a partial payment must deduct the whole quantity.'
        );

        $orderPayment = OrderProduct::find( $line[ 'id' ] )->order->payments->first();

        /**
         * The cashier now edits the order and reduces the quantity from 5 to 2.
         * Only the difference (3) should be returned to the stock.
         */
        $editedLine = $line;
        $editedLine[ 'quantity' ] = 2;
        $editedLine[ 'total_price' ] = $line[ 'unit_price' ] * 2;

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'PUT', 'api/orders/' . $order[ 'id' ], [
                'customer_id' => $order[ 'customer_id' ],
                'type' => [ 'identifier' => 'takeaway' ],
                'addresses' => $this->addressPayload(),
                'subtotal' => $editedLine[ 'total_price' ],
                'shipping' => 0,
                'payments' => [
                    [
                        'id' => $orderPayment->id,
                        'identifier' => $orderPayment->identifier,
                        'value' => (float) $orderPayment->value,
                    ],
                ],
                'products' => [ $editedLine ],
            ] );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.order.payment_status', Order::PAYMENT_PARTIALLY );

        $this->assertEquals(
            $stockBefore - 2,
            $this->productService->getQuantity( $line[ 'product_id' ], $line[ 'unit_id' ] ),
            'Editing a partially paid order must adjust the stock only by the quantity difference.'
        );
    }

    /**
     * The tests reuse an existing customer to avoid the faker based
     * customer creation which can produce duplicate emails.
     *
     * @return int
     */
    private function getCustomerId()
    {
        $customerId = Customer::inRandomOrder()->value( 'id' );

        $this->assertNotNull( $customerId, 'The test requires at least one existing customer.' );

        return (int) $customerId;
    }

    /**
     * @return array{ProductUnitQuantity, ProductUnitQuantity}
     */
    private function getProductsWithStock()    {
        $products = Product::withStockEnabled()
            ->notGrouped()
            ->with( 'unit_quantities' )
            ->get()
            ->filter( fn( $product ) => $product->unit_quantities->isNotEmpty() )
            ->values()
            ->take( 2 );

        $this->assertCount( 2, $products, 'The test requires at least two products with stock management.' );

        $unitQuantities = $products->map( function ( $product ) {
            $unitQuantity = $product->unit_quantities->first();
            $unitQuantity->quantity = 1000;
            $unitQuantity->save();

            return $unitQuantity;
        } )->values();

        return [ $unitQuantities[ 0 ], $unitQuantities[ 1 ] ];
    }

    private function makeLine( ProductUnitQuantity $unitQuantity, float $quantity ): array
    {
        return [
            'product_id' => $unitQuantity->product_id,
            'quantity' => $quantity,
            'unit_price' => $unitQuantity->sale_price,
            'unit_quantity_id' => $unitQuantity->id,
            'total_price' => $unitQuantity->sale_price * $quantity,
        ];
    }

    private function createHoldOrder( array $lines ): array
    {
        $subtotal = collect( $lines )->sum( fn( $line ) => $line[ 'total_price' ] );

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'POST', 'api/orders', [
                'customer_id' => $this->getCustomerId(),
                'type' => [ 'identifier' => 'takeaway' ],
                'addresses' => $this->addressPayload(),
                'payment_status' => Order::PAYMENT_HOLD,
                'subtotal' => $subtotal,
                'shipping' => 0,
                'products' => $lines,
            ] );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.order.payment_status', Order::PAYMENT_HOLD );

        return $response->json( 'data.order' );
    }

    private function chargeOrder( array $order, array $lines ): TestResponse
    {
        $total = collect( $lines )->sum( fn( $line ) => $line[ 'total_price' ] ?? ( $line[ 'unit_price' ] * $line[ 'quantity' ] ) );

        $payment = PaymentType::first();

        return $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'PUT', 'api/orders/' . $order[ 'id' ], [
                'customer_id' => $order[ 'customer_id' ],
                'type' => [ 'identifier' => 'takeaway' ],
                'addresses' => $this->addressPayload(),
                'subtotal' => $total,
                'shipping' => 0,
                'payments' => [
                    [
                        'identifier' => $payment->identifier,
                        'value' => (float) $total,
                    ],
                ],
                'products' => $lines,
            ] );
    }

    private function addressPayload(): array
    {
        return [
            'shipping' => [
                'name' => 'First Name Delivery',
                'surname' => 'Surname',
                'country' => 'Cameroon',
            ],
            'billing' => [
                'name' => 'EBENE Voundi',
                'surname' => 'Antony Hervé',
                'country' => 'United State Seattle',
            ],
        ];
    }
}
