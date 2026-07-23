<?php

namespace App\Models\Fees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeCollectionItem extends Model
{
    protected $fillable = ['fee_collection_id', 'student_fee_id', 'amount', 'fine_paid'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'fine_paid' => 'decimal:2'];
    }

    public function feeCollection(): BelongsTo
    {
        return $this->belongsTo(FeeCollection::class);
    }

    public function studentFee(): BelongsTo
    {
        return $this->belongsTo(StudentFee::class);
    }
}
