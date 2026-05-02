<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int            $id
 * @property int            $payroll_run_id
 * @property int            $attendance_id
 * @property int            $user_id
 * @property string         $date
 * @property string         $clock_in
 * @property string|null    $clock_out
 * @property float          $hours_worked
 * @property float          $hourly_rate
 * @property float          $pay_amount
 * @property float          $regular_hours
 * @property float          $regular_pay
 * @property float          $overtime_hours
 * @property float          $overtime_pay
 * @property float          $holiday_hours
 * @property float          $holiday_pay
 * @property float          $night_diff_hours
 * @property float          $night_diff_pay
 * @property float          $break_deduction
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PayrollRunItem extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_' . 'payroll_run_items';

    protected $fillable = [
        'payroll_run_id',
        'attendance_id',
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'hours_worked',
        'hourly_rate',
        'pay_amount',
        'regular_hours',
        'regular_pay',
        'overtime_hours',
        'overtime_pay',
        'holiday_hours',
        'holiday_pay',
        'night_diff_hours',
        'night_diff_pay',
        'break_deduction',
    ];

    protected $casts = [
        'hours_worked' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'pay_amount' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'regular_pay' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'holiday_hours' => 'decimal:2',
        'holiday_pay' => 'decimal:2',
        'night_diff_hours' => 'decimal:2',
        'night_diff_pay' => 'decimal:2',
        'break_deduction' => 'decimal:2',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'date' => 'date',
    ];

    public function payrollRun()
    {
        return $this->belongsTo( PayrollRun::class, 'payroll_run_id' );
    }

    public function attendance()
    {
        return $this->belongsTo( Attendance::class, 'attendance_id' );
    }

    public function user()
    {
        return $this->belongsTo( User::class, 'user_id' );
    }
}
