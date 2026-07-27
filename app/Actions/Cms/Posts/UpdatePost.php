<?php

declare(strict_types=1);

namespace App\Actions\Cms\Posts;

use App\Actions\Cms\Blocks\SyncBlocks;
use App\Enums\Cms\ContentStatus;
use App\Models\Cms\Post;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

class UpdatePost
{
    public function __construct(private readonly SyncBlocks $syncBlocks) {}

    /**
     * @param  array<string, mixed>  $data  Validated post attributes plus optional "seo", "blocks", "terms", "tags".
     */
    public function handle(Post $post, array $data, User $user): Post
    {
        return DB::transaction(function () use ($post, $data, $user): Post {
            $post->fill(collect($data)->except(['seo', 'blocks', 'terms', 'tags'])->all());
            $post->updated_by = $user->id;

            if (! empty($post->body)) {
                $post->body = HtmlSanitizer::clean($post->body);
            }

            if ($post->status === ContentStatus::Published && $post->published_at === null) {
                $post->published_at = now();
            }

            $post->reading_time = $post->computeReadingTime();
            $post->save();

            if (array_key_exists('seo', $data)) {
                $post->syncSeo($data['seo']);
            }

            if (array_key_exists('terms', $data)) {
                $post->terms()->sync($data['terms'] ?? []);
            }

            if (array_key_exists('tags', $data)) {
                $post->syncTags($data['tags'] ?? []);
            }

            if (isset($data['blocks'])) {
                $this->syncBlocks->handle($post, $data['blocks']);
            }

            return $post;
        });
    }
}
