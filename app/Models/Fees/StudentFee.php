<?php

namespace App\Models\Fees;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFee extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'student_id', 'enrollment_id', 'class_config_id', 'fee_sub_head_id', 'academic_year_id',
        'payable_amount', 'waiver_amount', 'fine_amount', 'paid_amount', 'due_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'payable_amount' => 'decimal:2',
            'waiver_amount' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }

    public function feeSubHead(): BelongsTo
    {
        return $this->belongsTo(FeeSubHead::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function collectionItems(): HasMany
    {
        return $this->hasMany(FeeCollectionItem::class);
    }

    /** Outstanding balance: payable + fine (once charged) - waiver - paid so far. */
    public function dueAmount(): float
    {
        return round((float) $this->payable_amount + (float) $this->fine_amount - (float) $this->waiver_amount - (float) $this->paid_amount, 2);
    }
}
