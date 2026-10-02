<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int            $id
 * @property int            $user_id
 * @property string         $date
 * @property string         $start_time
 * @property string         $end_time
 * @property float          $total_hours
 * @property string         $reason
 * @property string         $status
 * @property int|null       $approved_by
 * @property string|null    $approved_at
 * @property int            $author_id
 * @property string|null    $admin_note
 * @property mixed          $uuid
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class OvertimeRequest extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_' . 'overtime_requests';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'date',
        'start_time',
        'end_time',
        'total_hours',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'author_id',
        'admin_note',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_hours' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo( User::class, 'user_id' );
    }

    public function approver()
    {
        return $this->belongsTo( User::class, 'approved_by' );
    }

    public function author()
    {
        return $this->belongsTo( User::class, 'author_id' );
    }

    public function scopePending( $query )
    {
        return $query->where( 'status', self::STATUS_PENDING );
    }

    public function scopeApproved( $query )
    {
        return $query->where( 'status', self::STATUS_APPROVED );
    }

    public function scopeForPeriod( $query, $start, $end )
    {
        return $query->whereBetween( 'date', [ $start, $end ] );
    }
}
