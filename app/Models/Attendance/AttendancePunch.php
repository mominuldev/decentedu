<?php

namespace App\Models\Attendance;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    use BelongsToBranch, HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'attendance_device_id', 'external_user_id', 'punched_at', 'direction',
        'raw_payload', 'processed', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'processed_at' => 'datetime',
            'processed' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }
}
