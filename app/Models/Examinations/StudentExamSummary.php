<?php

namespace App\Models\Examinations;

use App\Models\Academic\ClassConfig;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExamSummary extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'student_id', 'exam_id', 'class_config_id', 'total_marks', 'total_obtained', 'gpa',
        'is_pass', 'failed_subjects_count', 'class_position', 'section_position', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'total_obtained' => 'decimal:2',
            'gpa' => 'decimal:2',
            'is_pass' => 'boolean',
            'failed_subjects_count' => 'integer',
            'class_position' => 'integer',
            'section_position' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }
}
