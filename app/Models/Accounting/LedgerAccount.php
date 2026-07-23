<?php

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerAccount extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'type', 'parent_id', 'is_system', 'opening_balance', 'status'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'status' => 'boolean', 'opening_balance' => 'decimal:2'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class);
    }

    /**
     * Find-or-create one of the fixed, auto-provisioned system accounts (Cash, Bank, per-head
     * fee income, fine income) so posting never depends on an admin having set up a mapping.
     */
    public static function systemAccount(int $branchId, string $code, string $name, string $type): self
    {
        return static::firstOrCreate(
            ['branch_id' => $branchId, 'code' => $code],
            ['name' => $name, 'type' => $type, 'is_system' => true, 'status' => true],
        );
    }
}
