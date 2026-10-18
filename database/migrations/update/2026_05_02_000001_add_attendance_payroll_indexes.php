<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table( 'nexopos_attendance', function ( Blueprint $table ) {
            $table->index( [ 'user_id', 'clock_in_at' ], 'idx_attendance_user_clockin' );
        } );
    }

    public function down()
    {
        Schema::table( 'nexopos_attendance', function ( Blueprint $table ) {
            $table->dropIndex( 'idx_attendance_user_clockin' );
        } );
    }
};
