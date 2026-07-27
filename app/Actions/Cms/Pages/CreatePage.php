<?php

declare(strict_types=1);

namespace App\Actions\Cms\Pages;

use App\Actions\Cms\Blocks\SyncBlocks;
use App\Enums\Cms\ContentStatus;
use App\Models\Cms\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePage
{
    public function __construct(private readonly SyncBlocks $syncBlocks) {}

    /**
     * @param  array<string, mixed>  $data  Validated page attributes plus optional "seo" and "blocks" arrays.
     */
    public function handle(array $data, User $user): Page
    {
        return DB::transaction(function () use ($data, $user): Page {
            $page = new Page(collect($data)->except(['seo', 'blocks'])->all());
            $page->created_by = $user->id;
            $page->updated_by = $user->id;

            if ($page->status === ContentStatus::Published && $page->published_at === null) {
                $page->published_at = now();
            }

            // computePath() runs before save(), so ensure the slug exists first — Spatie only
            // keeps a client-supplied slug and would otherwise generate it during save().
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }

            $page->path = $page->computePath();
            $page->save();

            $page->syncSeo($data['seo'] ?? null);

            if (isset($data['blocks'])) {
                $this->syncBlocks->handle($page, $data['blocks']);
            }

            return $page;
        });
    }
}
