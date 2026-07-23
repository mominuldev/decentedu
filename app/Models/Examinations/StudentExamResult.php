<?php

namespace App\Models\Examinations;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExamResult extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'student_id', 'exam_id', 'subject_id', 'class_config_id',
        'total_marks', 'obtained_marks', 'grade_id', 'grade_point', 'is_pass', 'is_absent', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'obtained_marks' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'is_pass' => 'boolean',
            'is_absent' => 'boolean',
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
