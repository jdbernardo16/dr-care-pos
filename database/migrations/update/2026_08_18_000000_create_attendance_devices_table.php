<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create( 'nexopos_attendance_devices', function ( Blueprint $table ) {
            $table->bigIncrements( 'id' );
            $table->string( 'device_id' )->unique();
            $table->string( 'secret_hash' );
            $table->string( 'label' )->nullable();
            $table->unsignedBigInteger( 'enrolled_by' )->nullable();
            $table->datetime( 'enrolled_at' )->nullable();
            $table->datetime( 'last_used_at' )->nullable();
            $table->boolean( 'active' )->default( true );
            $table->timestamps();
        } );

        Schema::table( 'nexopos_attendance', function ( Blueprint $table ) {
            $table->string( 'clock_in_device_id' )->nullable()->after( 'clock_in_ip' );
            $table->string( 'clock_out_device_id' )->nullable()->after( 'clock_out_ip' );
        } );
    }

    public function down()
    {
        Schema::table( 'nexopos_attendance', function ( Blueprint $table ) {
            $table->dropColumn( [ 'clock_in_device_id', 'clock_out_device_id' ] );
        } );

        Schema::dropIfExists( 'nexopos_attendance_devices' );
    }
};
