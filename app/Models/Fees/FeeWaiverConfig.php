<?php

namespace App\Models\Fees;

use App\Models\Academic\AcademicYear;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeWaiverConfig extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['student_id', 'fee_waiver_id', 'fee_sub_head_id', 'academic_year_id', 'created_by'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeWaiver(): BelongsTo
    {
        return $this->belongsTo(FeeWaiver::class);
    }

    public function feeSubHead(): BelongsTo
    {
        return $this->belongsTo(FeeSubHead::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
