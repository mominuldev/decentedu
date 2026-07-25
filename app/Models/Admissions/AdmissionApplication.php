<?php

namespace App\Models\Admissions;

use App\Models\Academic\ClassConfig;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdmissionApplication extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'admission_year_id',
        'class_config_id',
        'quota_id',
        'application_no',
        'name',
        'name_bn',
        'sex',
        'religion',
        'blood_group',
        'dob',
        'fathers_name',
        'mothers_name',
        'mobile',
        'guardian_mobile',
        'photo_path',
        'present_address',
        'permanent_address',
        'score',
        'status',
        'student_id',
        'applied_at',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'applied_at' => 'date',
            'score' => 'decimal:2',
        ];
    }

    public function admissionYear(): BelongsTo
    {
        return $this->belongsTo(AdmissionYear::class);
    }

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }

    public function quota(): BelongsTo
    {
        return $this->belongsTo(Quota::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Applicants who have not yet been converted into a student.
    public function scopeUnconverted($query)
    {
        return $query->whereNull('student_id');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%")
                ->orWhere('application_no', 'like', "%{$term}%")
                ->orWhere('fathers_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%");
        });
    }
}
