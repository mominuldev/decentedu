<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row-level multi-branch isolation. Any model using this trait is automatically
 * filtered to the active branch on every query, and stamped with that branch_id
 * on create. Bypass deliberately (e.g. cross-branch reports) with
 * Model::query()->withoutGlobalScope('branch') or the withoutBranchScope() scope.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder): void {
            $branchId = app(BranchContext::class)->id();
            if ($branchId !== null) {
                $builder->where($builder->getModel()->getTable().'.branch_id', $branchId);
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->branch_id)) {
                $model->branch_id = app(BranchContext::class)->id();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}
