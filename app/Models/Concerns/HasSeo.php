<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Cms\Seo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSeo
{
    /**
     * @return MorphOne<Seo, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(Seo::class, 'seoable');
    }

    /**
     * Create, update, or remove the SEO row. The row only exists when at
     * least one field has a value.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function syncSeo(?array $data): void
    {
        $data = array_filter($data ?? [], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($data === []) {
            $this->seo()->delete();

            return;
        }

        $this->seo()->updateOrCreate([], $data);
    }
}
