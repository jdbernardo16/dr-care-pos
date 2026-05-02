<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create( 'nexopos_payroll_run_items', function ( Blueprint $table ) {
            $table->bigIncrements( 'id' );
            $table->unsignedBigInteger( 'payroll_run_id' );
            $table->unsignedBigInteger( 'attendance_id' );
            $table->unsignedInteger( 'user_id' );
            $table->date( 'date' );
            $table->datetime( 'clock_in' );
            $table->datetime( 'clock_out' )->nullable();
            $table->decimal( 'hours_worked', 10, 2 )->default( 0.00 );
            $table->decimal( 'hourly_rate', 10, 2 );
            $table->decimal( 'pay_amount', 10, 2 )->default( 0.00 );
            $table->timestamps();

            $table->foreign( 'payroll_run_id' )->references( 'id' )->on( 'nexopos_payroll_runs' )->onDelete( 'cascade' );
            $table->foreign( 'attendance_id' )->references( 'id' )->on( 'nexopos_attendance' )->onDelete( 'restrict' );
            $table->foreign( 'user_id' )->references( 'id' )->on( 'nexopos_users' )->onDelete( 'restrict' );
            $table->unique( [ 'attendance_id' ], 'uq_payroll_item_attendance' );
            $table->index( [ 'payroll_run_id' ] );
            $table->index( [ 'user_id', 'date' ] );
        } );
    }

    public function down()
    {
        Schema::dropIfExists( 'nexopos_payroll_run_items' );
    }
};
