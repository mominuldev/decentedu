<?php

namespace App\Models\Students;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Category;
use App\Models\Academic\Group;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'student_enrollments';

    protected $fillable = [
        'branch_id',
        'student_id',
        'academic_year_id',
        'class_config_id',
        'group_id',
        'category_id',
        'roll',
        'is_current',
        'enrolled_at',
        'left_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'enrolled_at' => 'date',
            'left_at' => 'date',
        ];
    }

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

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scope for current enrollments
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    // Scope for specific academic year
    public function scopeForYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    // Scope for specific class
    public function scopeForClass($query, $classConfigId)
    {
        return $query->where('class_config_id', $classConfigId);
    }
}