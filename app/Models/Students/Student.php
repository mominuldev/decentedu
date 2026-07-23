<?php

namespace App\Models\Students;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\Group;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'student_uid',
        'name',
        'name_bn',
        'sex',
        'religion',
        'blood_group',
        'dob',
        'fathers_name',
        'mothers_name',
        'mobile',
        'father_mobile',
        'mother_mobile',
        'photo_path',
        'present_address',
        'permanent_address',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'status' => 'string', // active, transferred, left, passed_out
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->orderBy('academic_year_id', 'desc');
    }

    public function currentEnrollment()
    {
        return $this->hasOne(Enrollment::class)->where('is_current', true);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function transferCertificates(): HasMany
    {
        return $this->hasMany(TransferCertificate::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scope for active students
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope for searching students
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%")
                ->orWhere('student_uid', 'like', "%{$term}%")
                ->orWhere('fathers_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%");
        });
    }

    // Get student's class for a specific academic year
    public function getClassInYear($academicYearId)
    {
        return $this->enrollments()
            ->where('academic_year_id', $academicYearId)
            ->first();
    }
}