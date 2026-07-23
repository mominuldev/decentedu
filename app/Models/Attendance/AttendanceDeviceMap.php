<?php

namespace App\Models\Attendance;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceDeviceMap extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'attendance_device_id', 'external_user_id', 'mappable_type', 'mappable_id', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function mappable(): MorphTo
    {
        return $this->morphTo();
    }
}
