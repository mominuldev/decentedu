<?php

namespace App\Models\Fees;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeConfig extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['class_config_id', 'fee_sub_head_id', 'academic_year_id', 'amount', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
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
}
