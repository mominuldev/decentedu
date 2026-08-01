<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\Cms\ContentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Publishing semantics shared by pages and posts. A record is publicly
 * visible when it is published, or scheduled with a past publish date —
 * scheduling resolves in the query scope, no cron mutation required.
 */
trait HasPublishing
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('status', ContentStatus::Published)
                    ->where(fn (Builder $q): Builder => $q
                        ->whereNull('published_at')
                        ->orWhere('published_at', '<=', now())))
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('status', ContentStatus::Scheduled)
                    ->where('published_at', '<=', now()));
        });
    }

    public function isPublished(): bool
    {
        $status = $this->getAttribute('status');
        $statusEnum = $status instanceof ContentStatus ? $status : (is_string($status) ? ContentStatus::tryFrom($status) : null);
        $publishedAt = $this->getAttribute('published_at');
        $carbonDate = $publishedAt instanceof Carbon ? $publishedAt : ($publishedAt ? Carbon::parse($publishedAt) : null);

        if ($statusEnum === ContentStatus::Published) {
            return $carbonDate === null || $carbonDate->isPast();
        }

        return $statusEnum === ContentStatus::Scheduled
            && $carbonDate !== null
            && $carbonDate->isPast();
    }
}
