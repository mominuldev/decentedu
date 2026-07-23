<?php

namespace App\Models\Examinations;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\Group;
use App\Models\Academic\Subject;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarkConfig extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'class_config_id', 'group_id', 'exam_id', 'subject_id', 'short_code_id',
        'total_marks', 'pass_mark', 'acceptance', 'sc_merge', 'serial', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sc_merge' => 'boolean',
            'serial' => 'integer',
            'total_marks' => 'decimal:2',
            'pass_mark' => 'decimal:2',
            'acceptance' => 'decimal:2',
        ];
    }

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function shortCode(): BelongsTo
    {
        return $this->belongsTo(ShortCode::class);
    }
}
