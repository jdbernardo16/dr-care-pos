<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class GcashTransaction extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_gcash_transactions';

    protected $casts = [
        'customer_amount' => 'float',
        'service_fee' => 'float',
    ];

    public function session()
    {
        return $this->belongsTo( GcashSession::class, 'gcash_session_id' );
    }

    public function register()
    {
        return $this->belongsTo( Register::class );
    }

    public function cashier()
    {
        return $this->belongsTo( User::class, 'cashier_id' );
    }
}
