<?php

namespace App\Models\Fees;

use App\Models\Academic\AcademicYear;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeTimeConfig extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['fee_sub_head_id', 'academic_year_id', 'due_date', 'fine_amount', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'fine_amount' => 'decimal:2'];
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
