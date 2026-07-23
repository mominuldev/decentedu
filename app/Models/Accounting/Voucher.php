<?php

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['type', 'voucher_no', 'date', 'note', 'total', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'total' => 'decimal:2'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Next sequential voucher number for a branch+type, e.g. "RV-000001". */
    public static function nextNumber(int $branchId, string $type): string
    {
        $prefix = ['receive' => 'RV', 'payment' => 'PV', 'contra' => 'CV', 'journal' => 'JV'][$type];
        $count = static::withoutBranchScope()->where('branch_id', $branchId)->where('type', $type)->count();

        return sprintf('%s-%06d', $prefix, $count + 1);
    }
}
