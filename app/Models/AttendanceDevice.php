<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceDevice extends NsModel
{
    use HasFactory;

    protected $table = 'nexopos_attendance_devices';

    protected $fillable = [
        'device_id',
        'secret_hash',
        'label',
        'enrolled_by',
        'enrolled_at',
        'last_used_at',
        'active',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'last_used_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function scopeActive( $query )
    {
        return $query->where( 'active', true );
    }

    public function scopeForDeviceId( $query, $deviceId )
    {
        return $query->where( 'device_id', $deviceId );
    }
}
