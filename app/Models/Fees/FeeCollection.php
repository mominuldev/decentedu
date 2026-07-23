<?php

namespace App\Models\Fees;

use App\Models\Accounting\Voucher;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeCollection extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'student_id', 'receipt_no', 'collected_at', 'total_amount', 'payment_method', 'note', 'voucher_id', 'collected_by',
    ];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime', 'total_amount' => 'decimal:2'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeCollectionItem::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
