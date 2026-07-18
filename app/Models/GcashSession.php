<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class GcashSession extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_gcash_sessions';

    public function register()
    {
        return $this->belongsTo( Register::class );
    }

    public function cashier()
    {
        return $this->belongsTo( User::class, 'cashier_id' );
    }

    public function transactions()
    {
        return $this->hasMany( GcashTransaction::class, 'gcash_session_id' );
    }
}
