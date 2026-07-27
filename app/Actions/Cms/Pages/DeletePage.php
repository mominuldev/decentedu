<?php

declare(strict_types=1);

namespace App\Actions\Cms\Pages;

use App\Models\Cms\Page;
use Illuminate\Support\Facades\DB;

class DeletePage
{
    /**
     * Soft-deletes the page and its whole subtree — orphaned child paths
     * would otherwise keep resolving publicly.
     */
    public function handle(Page $page): void
    {
        DB::transaction(function () use ($page): void {
            $this->deleteSubtree($page);
        });
    }

    private function deleteSubtree(Page $page): void
    {
        foreach ($page->children()->get() as $child) {
            $this->deleteSubtree($child);
        }

        $page->delete();
    }
}
