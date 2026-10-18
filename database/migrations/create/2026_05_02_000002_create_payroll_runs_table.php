<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create( 'nexopos_payroll_runs', function ( Blueprint $table ) {
            $table->bigIncrements( 'id' );
            $table->unsignedInteger( 'user_id' );
            $table->date( 'period_start' );
            $table->date( 'period_end' );
            $table->string( 'period_type', 20 )->default( 'bi-weekly' )->comment( 'weekly | bi-weekly | monthly' );
            $table->decimal( 'total_hours', 10, 2 )->default( 0.00 );
            $table->decimal( 'hourly_rate', 10, 2 );
            $table->decimal( 'gross_pay', 10, 2 )->default( 0.00 );
            $table->string( 'status', 20 )->default( 'draft' )->comment( 'draft | posted | void' );
            $table->unsignedBigInteger( 'transaction_id' )->nullable();
            $table->text( 'notes' )->nullable();
            $table->unsignedInteger( 'author_id' );
            $table->string( 'uuid' )->nullable();
            $table->timestamps();

            $table->foreign( 'user_id' )->references( 'id' )->on( 'nexopos_users' )->onDelete( 'restrict' );
            $table->foreign( 'transaction_id' )->references( 'id' )->on( 'nexopos_transactions' )->onDelete( 'set null' );
            $table->foreign( 'author_id' )->references( 'id' )->on( 'nexopos_users' )->onDelete( 'restrict' );
            $table->unique( [ 'user_id', 'period_start', 'period_end', 'status' ], 'uq_payroll_user_period_status' );
            $table->index( 'status' );
            $table->index( [ 'period_start', 'period_end' ] );
        } );
    }

    public function down()
    {
        Schema::dropIfExists( 'nexopos_payroll_runs' );
    }
};
