<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up()
    {
        if ( ! Schema::hasColumn( 'nexopos_payments_types', 'is_cash' ) ) {
            Schema::table( 'nexopos_payments_types', function ( Blueprint $table ) {
                $table->boolean( 'is_cash' )->default( false )->after( 'readonly' );
            } );
        }
    }

    public function down()
    {
        if ( Schema::hasColumn( 'nexopos_payments_types', 'is_cash' ) ) {
            Schema::table( 'nexopos_payments_types', function ( Blueprint $table ) {
                $table->dropColumn( 'is_cash' );
            } );
        }
    }
};
