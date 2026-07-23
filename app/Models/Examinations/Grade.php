<?php

namespace App\Models\Examinations;

use App\Models\Academic\SchoolClass;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'class_id', 'name', 'grade_point', 'mark_from', 'mark_to', 'serial', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'serial' => 'integer',
            'grade_point' => 'decimal:2',
            'mark_from' => 'decimal:2',
            'mark_to' => 'decimal:2',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** Look up the grade whose [mark_from, mark_to] range contains this percentage. */
    public static function forPercentage(int $classId, float $percentage): ?self
    {
        return static::where('class_id', $classId)
            ->where('status', true)
            ->where('mark_from', '<=', $percentage)
            ->where('mark_to', '>=', $percentage)
            ->orderByDesc('mark_from')
            ->first();
    }
}
