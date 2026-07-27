<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use App\Models\Cms\Post;
use Illuminate\Validation\Rule;

class PostsListBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::PostsList;
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'mode' => ['required', Rule::in(['latest', 'featured', 'term'])],
            'term_id' => ['nullable', 'integer', Rule::exists('terms', 'id'), 'required_if:mode,term'],
            'limit' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    public function toResource(array $payload): array
    {
        $limit = (int) ($payload['limit'] ?? 3);
        $mode = $payload['mode'] ?? 'latest';

        $posts = Post::query()
            ->published()
            ->with(['featuredAsset.media', 'author'])
            ->when($mode === 'featured', fn ($query) => $query->where('is_featured', true))
            ->when(
                $mode === 'term' && isset($payload['term_id']),
                fn ($query) => $query->whereHas('terms', fn ($terms) => $terms->whereKey($payload['term_id'])),
            )
            ->latest('published_at')
            ->limit($limit)
            ->get();

        return [
            'heading' => $payload['heading'] ?? null,
            'mode' => $mode,
            'posts' => $posts->map(fn (Post $post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'published_at' => $post->published_at?->toIso8601String(),
                'reading_time' => $post->reading_time,
                'author' => $post->author?->name,
                'featured_image' => $post->featuredAsset?->toApiPayload(),
            ])->all(),
        ];
    }
}
