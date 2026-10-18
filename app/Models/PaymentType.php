<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int            $id
 * @property string         $identifier
 * @property int            $author_id
 * @property string         $description
 * @property bool           $readonly
 * @property bool           $is_cash
 * @property \Carbon\Carbon $updated_at
 */
class PaymentType extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_' . 'payments_types';

    protected $casts = [
        'is_cash' => 'boolean',
        'active' => 'boolean',
        'readonly' => 'boolean',
    ];

    public function scopeActive( $query )
    {
        return $query->where( 'active', true );
    }

    public function scopeIdentifier( $query, $identifier )
    {
        return $query->where( 'identifier', $identifier );
    }

    public function user()
    {
        return $this->belongsTo( User::class, 'author_id' );
    }
}
