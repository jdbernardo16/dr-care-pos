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
        if ( ! Schema::hasTable( 'nexopos_attendance' ) ) {
            Schema::createIfMissing( 'nexopos_attendance', function ( Blueprint $table ) {
                $table->bigIncrements( 'id' );
                $table->integer( 'user_id' ); // FK to nexopos_users
                $table->datetime( 'clock_in_at' ); // timestamp when employee clocks in
                $table->datetime( 'clock_out_at' )->nullable(); // timestamp when employee clocks out
                $table->string( 'clock_in_ip', 45 )->nullable(); // IP at clock-in
                $table->string( 'clock_out_ip', 45 )->nullable(); // IP at clock-out
                $table->text( 'clock_in_note' )->nullable(); // note at clock-in
                $table->text( 'clock_out_note' )->nullable(); // note at clock-out
                $table->decimal( 'total_hours', 10, 2 )->nullable(); // auto-calculated hours
                $table->string( 'status' )->default( 'clocked_in' ); // clocked_in, clocked_out, absent, on_break
                $table->integer( 'author_id' ); // who recorded this
                $table->string( 'uuid' )->nullable(); // for replication
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
        if ( Schema::hasTable( 'nexopos_attendance' ) ) {
            Schema::dropIfExists( 'nexopos_attendance' );
        }
    }
};
