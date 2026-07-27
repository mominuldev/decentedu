<?php

declare(strict_types=1);

namespace App\Actions\Cms\Pages;

use App\Actions\Cms\Blocks\SyncBlocks;
use App\Enums\Cms\ContentStatus;
use App\Models\Cms\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePage
{
    public function __construct(private readonly SyncBlocks $syncBlocks) {}

    /**
     * @param  array<string, mixed>  $data  Validated page attributes plus optional "seo" and "blocks" arrays.
     */
    public function handle(Page $page, array $data, User $user): Page
    {
        return DB::transaction(function () use ($page, $data, $user): Page {
            $this->guardAgainstCircularParent($page, $data);

            $page->fill(collect($data)->except(['seo', 'blocks'])->all());
            $page->updated_by = $user->id;

            if ($page->status === ContentStatus::Published && $page->published_at === null) {
                $page->published_at = now();
            }

            if ($page->isDirty(['slug', 'parent_id'])) {
                $page->path = $page->computePath();
            }

            $pathChanged = $page->isDirty('path');
            $page->save();

            if ($pathChanged) {
                $this->recomputeDescendantPaths($page);
            }

            if (array_key_exists('seo', $data)) {
                $page->syncSeo($data['seo']);
            }

            if (isset($data['blocks'])) {
                $this->syncBlocks->handle($page, $data['blocks']);
            }

            return $page;
        });
    }

    /**
     * A page cannot be moved under itself or one of its descendants.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardAgainstCircularParent(Page $page, array $data): void
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId === null) {
            return;
        }

        $current = Page::query()->find($parentId);

        while ($current !== null) {
            if ($current->id === $page->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A page cannot be nested under itself or one of its descendants.'],
                ]);
            }

            $current = $current->parent_id !== null ? Page::query()->find($current->parent_id) : null;
        }
    }

    private function recomputeDescendantPaths(Page $page): void
    {
        foreach ($page->children()->get() as $child) {
            $child->path = $page->path.'/'.$child->slug;
            $child->save();

            $this->recomputeDescendantPaths($child);
        }
    }
}
