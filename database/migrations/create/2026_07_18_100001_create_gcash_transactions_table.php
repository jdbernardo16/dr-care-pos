<?php

/**
 * Table Migration
 **/

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable( 'nexopos_gcash_transactions' ) ) {
            Schema::create( 'nexopos_gcash_transactions', function ( Blueprint $table ) {
                $table->bigIncrements( 'id' );
                $table->unsignedBigInteger( 'gcash_session_id' );
                $table->unsignedBigInteger( 'register_id' );
                $table->unsignedBigInteger( 'cashier_id' );
                $table->string( 'type' );
                $table->decimal( 'customer_amount', 18, 5 );
                $table->decimal( 'service_fee', 18, 5 );
                $table->string( 'customer_phone', 20 )->nullable();
                $table->string( 'reference_no', 100 )->nullable();
                $table->timestamps();
            } );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists( 'nexopos_gcash_transactions' );
    }
};
