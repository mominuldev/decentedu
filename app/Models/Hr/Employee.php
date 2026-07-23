<?php

namespace App\Models\Hr;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'employee_uid',
        'name',
        'name_bn',
        'designation_id',
        'hr_section_id',
        'sex',
        'religion',
        'blood_group',
        'dob',
        'mobile',
        'email',
        'nid',
        'photo_path',
        'present_address',
        'permanent_address',
        'joining_date',
        'leaving_date',
        'employment_type',
        'status',
        'qualifications',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'joining_date' => 'date',
            'leaving_date' => 'date',
            'qualifications' => 'array',
        ];
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function hrSection(): BelongsTo
    {
        return $this->belongsTo(HrSection::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'employee_id', 'subject_id')
            ->withPivot('class_config_id', 'is_active')
            ->withTimestamps();
    }

    public function subjectTeachers()
    {
        return $this->hasMany(SubjectTeacher::class, 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scope for active employees
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope for teachers (employees assigned to subjects)
    public function scopeTeachers($query)
    {
        return $query->whereHas('subjectTeachers');
    }

    // Scope for specific employment type
    public function scopeEmploymentType($query, $type)
    {
        return $query->where('employment_type', $type);
    }

    // Scope for searching employees
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%")
                ->orWhere('employee_uid', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%");
        });
    }

    // Get classes this employee teaches
    public function getClassesAttribute()
    {
        return $this->subjectTeachers()
            ->with('classConfig')
            ->get()
            ->pluck('classConfig')
            ->unique('id');
    }
}