<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int            $id
 * @property int            $user_id
 * @property \Carbon\Carbon $clock_in_at
 * @property \Carbon\Carbon $clock_out_at
 * @property string         $clock_in_ip
 * @property string         $clock_out_ip
 * @property string         $clock_in_note
 * @property string         $clock_out_note
 * @property float          $total_hours
 * @property string         $status
 * @property int            $author_id
 * @property mixed          $uuid
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Attendance extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_' . 'attendance';

    const STATUS_CLOCKED_IN = 'clocked_in';

    const STATUS_CLOCKED_OUT = 'clocked_out';

    const STATUS_ABSENT = 'absent';

    const STATUS_ON_BREAK = 'on_break';

    protected $fillable = [
        'user_id',
        'clock_in_at',
        'clock_out_at',
        'clock_in_ip',
        'clock_out_ip',
        'clock_in_note',
        'clock_out_note',
        'total_hours',
        'status',
        'author_id',
    ];

    protected $casts = [
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'total_hours' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo( User::class, 'user_id' );
    }

    public function author()
    {
        return $this->belongsTo( User::class, 'author_id' );
    }

    public function payrollItem()
    {
        return $this->hasOne( PayrollRunItem::class, 'attendance_id' );
    }

    public function scopeClockedIn( $query )
    {
        return $query->where( 'status', self::STATUS_CLOCKED_IN );
    }

    public function scopeForUser( $query, $userId )
    {
        return $query->where( 'user_id', $userId );
    }

    public function scopeForToday( $query )
    {
        return $query->whereDate( 'clock_in_at', now() );
    }

    public function scopeForDate( $query, $date )
    {
        return $query->whereDate( 'clock_in_at', $date );
    }

    public function scopeForPayPeriod( $query, $start, $end )
    {
        return $query->whereBetween( 'clock_in_at', [ $start, $end ] );
    }
}
