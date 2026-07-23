<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an AuditLog row on create/update/delete (doc 08: marks, fees, vouchers, users,
 * permissions). Modeled on BelongsToBranch's static-boot pattern. Timestamp-only updates
 * (e.g. a touch()) are skipped since they carry no business-meaningful change.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => static::recordAudit(
            $model, 'created', null, static::redact($model, $model->getAttributes()),
        ));

        static::updated(function (Model $model): void {
            $changes = collect($model->getChanges())->except(['updated_at'])->all();
            if (empty($changes)) {
                return;
            }
            $before = collect($model->getOriginal())->only(array_keys($changes))->all();
            static::recordAudit($model, 'updated', static::redact($model, $before), static::redact($model, $changes));
        });

        static::deleted(fn (Model $model) => static::recordAudit(
            $model, 'deleted', static::redact($model, $model->getOriginal()), null,
        ));
    }

    /** Models may define protected array $auditExcept = [...] for fields like password hashes. */
    private static function redact(Model $model, array $attributes): array
    {
        $except = property_exists($model, 'auditExcept') ? $model->auditExcept : [];

        return collect($attributes)->except($except)->all();
    }

    private static function recordAudit(Model $model, string $action, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'branch_id' => $model->branch_id ?? app(BranchContext::class)->id(),
            'user_id' => auth()->id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'changes' => array_filter(['before' => $before, 'after' => $after], fn ($v) => $v !== null),
        ]);
    }
}
