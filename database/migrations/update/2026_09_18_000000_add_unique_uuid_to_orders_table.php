<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        /**
         * The uuid column has never been used until now. In case an
         * installation has stored duplicated values (e.g while testing
         * the checkout idempotency), we clear them so the unique index
         * can be created. Sales are not affected by this.
         */
        $duplicatedUuids = DB::table( 'nexopos_orders' )
            ->select( 'uuid' )
            ->whereNotNull( 'uuid' )
            ->groupBy( 'uuid' )
            ->havingRaw( 'COUNT(*) > 1' )
            ->pluck( 'uuid' );

        if ( $duplicatedUuids->isNotEmpty() ) {
            DB::table( 'nexopos_orders' )
                ->whereIn( 'uuid', $duplicatedUuids->all() )
                ->update( [ 'uuid' => null ] );
        }

        Schema::table( 'nexopos_orders', function ( Blueprint $table ) {
            $table->unique( 'uuid' );
        } );
    }

    public function down()
    {
        Schema::table( 'nexopos_orders', function ( Blueprint $table ) {
            $table->dropUnique( [ 'uuid' ] );
        } );
    }
};
