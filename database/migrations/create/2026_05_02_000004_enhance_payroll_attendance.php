<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Add break columns to attendance
        Schema::table( 'nexopos_attendance', function ( Blueprint $table ) {
            $table->datetime( 'break_start' )->nullable()->after( 'clock_out_at' );
            $table->datetime( 'break_end' )->nullable()->after( 'break_start' );
            $table->decimal( 'break_hours', 10, 2 )->default( 0.00 )->after( 'break_end' );
            $table->decimal( 'net_hours', 10, 2 )->default( 0.00 )->after( 'break_hours' );
        } );

        // 2. Overtime requests table
        Schema::create( 'nexopos_overtime_requests', function ( Blueprint $table ) {
            $table->bigIncrements( 'id' );
            $table->unsignedInteger( 'user_id' );
            $table->date( 'date' );
            $table->datetime( 'start_time' );
            $table->datetime( 'end_time' );
            $table->decimal( 'total_hours', 10, 2 )->default( 0.00 );
            $table->string( 'reason', 500 );
            $table->string( 'status', 20 )->default( 'pending' )->comment( 'pending | approved | rejected' );
            $table->unsignedInteger( 'approved_by' )->nullable();
            $table->datetime( 'approved_at' )->nullable();
            $table->unsignedInteger( 'author_id' );
            $table->text( 'admin_note' )->nullable();
            $table->string( 'uuid' )->nullable();
            $table->timestamps();

            $table->foreign( 'user_id' )->references( 'id' )->on( 'nexopos_users' )->onDelete( 'restrict' );
            $table->foreign( 'approved_by' )->references( 'id' )->on( 'nexopos_users' )->onDelete( 'set null' );
            $table->foreign( 'author_id' )->references( 'id' )->on( 'nexopos_users' )->onDelete( 'restrict' );
            $table->index( [ 'user_id', 'date' ] );
            $table->index( 'status' );
        } );

        // 3. Holidays table
        Schema::create( 'nexopos_holidays', function ( Blueprint $table ) {
            $table->bigIncrements( 'id' );
            $table->string( 'name' );
            $table->date( 'date' );
            $table->string( 'type', 30 )->comment( 'regular_holiday | special_non_working' );
            $table->decimal( 'multiplier', 5, 2 )->default( 2.00 );
            $table->boolean( 'is_recurring' )->default( false );
            $table->text( 'description' )->nullable();
            $table->unsignedInteger( 'author_id' );
            $table->timestamps();

            $table->foreign( 'author_id' )->references( 'id' )->on( 'nexopos_users' )->onDelete( 'restrict' );
            $table->unique( [ 'date', 'type' ] );
            $table->index( 'date' );
        } );

        // 4. Add breakdown columns to payroll_run_items
        Schema::table( 'nexopos_payroll_run_items', function ( Blueprint $table ) {
            $table->decimal( 'regular_hours', 10, 2 )->default( 0.00 )->after( 'pay_amount' );
            $table->decimal( 'regular_pay', 10, 2 )->default( 0.00 )->after( 'regular_hours' );
            $table->decimal( 'overtime_hours', 10, 2 )->default( 0.00 )->after( 'regular_pay' );
            $table->decimal( 'overtime_pay', 10, 2 )->default( 0.00 )->after( 'overtime_hours' );
            $table->decimal( 'holiday_hours', 10, 2 )->default( 0.00 )->after( 'overtime_pay' );
            $table->decimal( 'holiday_pay', 10, 2 )->default( 0.00 )->after( 'holiday_hours' );
            $table->decimal( 'night_diff_hours', 10, 2 )->default( 0.00 )->after( 'holiday_pay' );
            $table->decimal( 'night_diff_pay', 10, 2 )->default( 0.00 )->after( 'night_diff_hours' );
            $table->decimal( 'break_deduction', 10, 2 )->default( 0.00 )->after( 'night_diff_pay' );
        } );
    }

    public function down()
    {
        Schema::table( 'nexopos_attendance', function ( Blueprint $table ) {
            $table->dropColumn( [ 'break_start', 'break_end', 'break_hours', 'net_hours' ] );
        } );

        Schema::dropIfExists( 'nexopos_overtime_requests' );
        Schema::dropIfExists( 'nexopos_holidays' );

        Schema::table( 'nexopos_payroll_run_items', function ( Blueprint $table ) {
            $table->dropColumn( [
                'regular_hours', 'regular_pay',
                'overtime_hours', 'overtime_pay',
                'holiday_hours', 'holiday_pay',
                'night_diff_hours', 'night_diff_pay',
                'break_deduction',
            ] );
        } );
    }
};
