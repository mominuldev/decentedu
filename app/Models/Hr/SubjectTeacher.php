<?php

namespace App\Models\Hr;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubjectTeacher extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'subject_teacher';

    protected $fillable = [
        'branch_id',
        'employee_id',
        'subject_id',
        'class_config_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scope for active assignments
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for specific class
    public function scopeForClass($query, $classConfigId)
    {
        return $query->where('class_config_id', $classConfigId);
    }

    // Scope for specific teacher
    public function scopeForTeacher($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // Scope for specific subject
    public function scopeForSubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }
}