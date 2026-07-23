<?php

namespace App\Models\Fees;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeWaiver extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'type', 'value', 'serial', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'serial' => 'integer', 'value' => 'decimal:2'];
    }

    /** Waiver amount for a given payable, capped so it never exceeds the payable itself. */
    public function amountFor(float $payable): float
    {
        $raw = $this->type === 'percentage' ? $payable * ((float) $this->value / 100) : (float) $this->value;

        return min($raw, $payable);
    }
}
