<?php

namespace App\Models\Fees;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeSubHead extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['fee_head_id', 'name', 'name_bn', 'serial', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'serial' => 'integer'];
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }
}
