<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\DashboardController;
use App\Models\Customer;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Register;

class PosController extends DashboardController
{
    public function heartbeat()
    {
        $now = ns()->date->getNow();

        return [
            'status' => 'success',
            'time' => $now->toDateTimeString(),
            'timestamp' => $now->getTimestamp() * 1000,
        ];
    }

    public function offlineSnapshot()
    {
        $enableReordering = ns()->option->get( 'ns_pos_enable_reordering' ) === 'yes';

        $products = Product::with( [
            'unit_quantities' => function ( $query ) {
                $query->where( 'visible', true )->with( 'unit' );
            },
            'category',
            'tax_group.taxes',
            'galleries',
        ] )
            ->onSale()
            ->where( function ( $query ) {
                $this->applyHideProducts( $query );
            } )
            ->trackingDisabled()
            ->get()
            ->map( function ( Product $product ) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'type' => $product->type,
                    'category_id' => $product->category_id,
                    'pinned' => (bool) $product->pinned,
                    'tax_group_id' => $product->tax_group_id,
                    'tax_type' => $product->tax_type,
                    'position' => $product->position,
                    'created_at' => $product->created_at,
                    'tax_group' => [
                        'id' => $product->tax_group?->id,
                        'name' => $product->tax_group?->name,
                        'taxes' => $product->tax_group
                            ? $product->tax_group->taxes->map( fn( $tax ) => [
                                'id' => $tax->id,
                                'name' => $tax->name,
                                'rate' => (float) $tax->rate,
                            ] )->values()
                            : [],
                    ],
                    'unit_quantities' => $product->unit_quantities->map( function ( $unitQuantity ) {
                        return [
                            'id' => $unitQuantity->id,
                            'unit_id' => $unitQuantity->unit_id,
                            'unit_name' => $unitQuantity->unit?->name,
                            'unit' => $unitQuantity->unit ? [
                                'id' => $unitQuantity->unit->id,
                                'name' => $unitQuantity->unit->name,
                            ] : null,
                            'sale_price' => (float) $unitQuantity->sale_price,
                            'sale_price_net' => (float) $unitQuantity->sale_price_net,
                            'sale_price_gross' => (float) $unitQuantity->sale_price_gross,
                            'quantity' => (float) $unitQuantity->quantity,
                        ];
                    } )->values(),
                    'galleries' => $product->galleries->map( fn( $gallery ) => [
                        'id' => $gallery->id,
                        'url' => $gallery->url,
                        'featured' => (bool) $gallery->featured,
                    ] )->values(),
                ];
            } );

        $categories = ProductCategory::where( function ( $query ) {
            $this->applyHideCategories( $query );
        } )
            ->displayOnPOS()
            ->when(
                $enableReordering,
                fn( $query ) => $query->orderBy( 'position', 'asc' ),
                fn( $query ) => $query->orderBy( 'created_at', 'asc' )
            )
            ->get()
            ->map( fn( ProductCategory $category ) => [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'preview' => $category->preview,
                'position' => $category->position,
                'created_at' => $category->created_at,
            ] );

        return [
            'generated_at' => ns()->date->getNow()->toDateTimeString(),
            'server_time' => ns()->date->getNow()->getTimestamp() * 1000,
            'products' => $products,
            'categories' => $categories,
            'payment_types' => PaymentType::active()->get( [ 'id', 'label', 'identifier' ] ),
            'customers' => Customer::get( [ 'id', 'first_name', 'last_name', 'phone', 'group_id' ] ),
            'register' => [
                'open_register_id' => Register::opened()->first()?->id,
            ],
            'options' => $this->offlineOptions(),
        ];
    }

    /**
     * The POS options needed while offline. Kept as an explicit list
     * so the snapshot stays small (some options hold large payloads).
     */
    private function offlineOptions(): array
    {
        $keys = [
            'ns_pos_enable_reordering',
            'ns_pos_hide_exhausted_products',
            'ns_pos_hide_empty_categories',
            'ns_pos_force_autofocus',
            'ns_pos_show_quantity',
            'ns_pos_complete_sale_audio',
            'ns_pos_printing_gateway',
            'ns_pos_registers_enabled',
            'ns_orders_allow_partial',
            'ns_orders_allow_incomplete',
        ];

        return collect( $keys )
            ->mapWithKeys( fn( $key ) => [ $key => ns()->option->get( $key ) ] )
            ->toArray();
    }

    private function applyHideProducts( $query )
    {
        $exhaustedHidden = ns()->option->get( 'ns_pos_hide_exhausted_products' );

        if ( $exhaustedHidden === 'yes' ) {
            $query->where( 'stock_management', Product::STOCK_MANAGEMENT_DISABLED );

            $query->orWhere( function ( $query ) {
                $query->where( 'stock_management', Product::STOCK_MANAGEMENT_ENABLED );

                $query->whereHas( 'unit_quantities', function ( $query ) {
                    $query->where( 'quantity', '>', 0 );
                } );
            } );
        }
    }

    private function applyHideCategories( $query )
    {
        $exhaustedHidden = ns()->option->get( 'ns_pos_hide_empty_categories' );

        if ( $exhaustedHidden === 'yes' ) {
            $query->whereHas( 'products', function ( $query ) {
                $query->where( 'stock_management', Product::STOCK_MANAGEMENT_DISABLED );
            } );

            $query->orWhereHas( 'products', function ( $query ) {
                $query->where( 'stock_management', Product::STOCK_MANAGEMENT_ENABLED );

                $query->whereHas( 'unit_quantities', function ( $query ) {
                    $query->where( 'quantity', '>', 0 );
                } );
            } );
        }
    }
}
