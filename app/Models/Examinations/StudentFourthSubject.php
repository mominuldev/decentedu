<?php

namespace App\Models\Examinations;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFourthSubject extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['student_id', 'academic_year_id', 'class_config_id', 'subject_id', 'created_by'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
