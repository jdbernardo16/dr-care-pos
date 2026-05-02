<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int            $id
 * @property int            $user_id
 * @property string         $period_start
 * @property string         $period_end
 * @property string         $period_type
 * @property float          $total_hours
 * @property float          $hourly_rate
 * @property float          $gross_pay
 * @property string         $status
 * @property int|null       $transaction_id
 * @property string|null    $notes
 * @property int            $author_id
 * @property mixed          $uuid
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PayrollRun extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_' . 'payroll_runs';

    const STATUS_DRAFT = 'draft';
    const STATUS_POSTED = 'posted';
    const STATUS_VOID = 'void';

    const PERIOD_WEEKLY = 'weekly';
    const PERIOD_BI_WEEKLY = 'bi-weekly';
    const PERIOD_MONTHLY = 'monthly';

    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'period_type',
        'total_hours',
        'hourly_rate',
        'gross_pay',
        'status',
        'transaction_id',
        'notes',
        'author_id',
    ];

    protected $casts = [
        'total_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo( User::class, 'user_id' );
    }

    public function author()
    {
        return $this->belongsTo( User::class, 'author_id' );
    }

    public function items()
    {
        return $this->hasMany( PayrollRunItem::class, 'payroll_run_id' );
    }

    public function transaction()
    {
        return $this->belongsTo( Transaction::class, 'transaction_id' );
    }
}
