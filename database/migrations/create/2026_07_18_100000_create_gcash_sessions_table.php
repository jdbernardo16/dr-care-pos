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
        if ( ! Schema::hasTable( 'nexopos_gcash_sessions' ) ) {
            Schema::create( 'nexopos_gcash_sessions', function ( Blueprint $table ) {
                $table->bigIncrements( 'id' );
                $table->unsignedBigInteger( 'register_id' );
                $table->unsignedBigInteger( 'cashier_id' );
                $table->decimal( 'opening_balance', 18, 5 )->default( 0 );
                $table->decimal( 'closing_balance', 18, 5 )->nullable();
                $table->decimal( 'current_balance', 18, 5 )->default( 0 );
                $table->decimal( 'total_cash_in', 18, 5 )->default( 0 );
                $table->decimal( 'total_cash_out', 18, 5 )->default( 0 );
                $table->decimal( 'total_fees', 18, 5 )->default( 0 );
                $table->string( 'status' )->default( 'opened' );
                $table->datetime( 'opened_at' );
                $table->datetime( 'closed_at' )->nullable();
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
        Schema::dropIfExists( 'nexopos_gcash_sessions' );
    }
};
