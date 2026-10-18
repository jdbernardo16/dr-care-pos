<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int            $id
 * @property string         $name
 * @property string         $date
 * @property string         $type
 * @property float          $multiplier
 * @property bool           $is_recurring
 * @property string|null    $description
 * @property int            $author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Holiday extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_' . 'holidays';

    const TYPE_REGULAR = 'regular_holiday';
    const TYPE_SPECIAL = 'special_non_working';

    protected $fillable = [
        'name',
        'date',
        'type',
        'multiplier',
        'is_recurring',
        'description',
        'author_id',
    ];

    protected $casts = [
        'date' => 'date',
        'multiplier' => 'decimal:2',
        'is_recurring' => 'boolean',
    ];

    public function author()
    {
        return $this->belongsTo( User::class, 'author_id' );
    }

    public function scopeForDate( $query, $date )
    {
        return $query->whereDate( 'date', $date );
    }

    public function scopeRegular( $query )
    {
        return $query->where( 'type', self::TYPE_REGULAR );
    }

    public function scopeSpecial( $query )
    {
        return $query->where( 'type', self::TYPE_SPECIAL );
    }
}
