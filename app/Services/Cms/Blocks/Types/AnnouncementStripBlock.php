<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use App\Models\Cms\Notice;
use Illuminate\Validation\Rule;

class AnnouncementStripBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::AnnouncementStrip;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'between:1,20'],
            'notices_mode' => ['nullable', Rule::in(['latest', 'important', 'selected'])],
            'notice_ids' => ['nullable', 'array'],
            'notice_ids.*' => ['integer', Rule::exists('notices', 'id')],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function toResource(array $payload): array
    {
        $noticesMode = $payload['notices_mode'] ?? 'latest';
        $limit = (int) ($payload['limit'] ?? 3);

        $noticesQuery = Notice::query()
            ->with(['attachment.media'])
            ->published();

        if ($noticesMode === 'important') {
            $noticesQuery->where('is_important', true);
        } elseif ($noticesMode === 'selected' && !empty($payload['notice_ids'])) {
            $noticesQuery->whereIn('id', $payload['notice_ids']);
        }

        $notices = $noticesQuery
            ->orderByDesc('notice_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return [
            'title' => $payload['title'] ?? 'Announcements',
            'limit' => $limit,
            'notices_mode' => $noticesMode,
            'cta_label' => $payload['cta_label'] ?? null,
            'cta_url' => $payload['cta_url'] ?? null,
            'notices' => $notices->map(fn (Notice $notice): array => [
                'id' => $notice->id,
                'title' => $notice->title,
                'slug' => $notice->slug,
                'notice_date' => $notice->notice_date?->toDateString(),
                'is_important' => $notice->is_important,
                'excerpt' => $notice->body ? strip_tags((string) str($notice->body)->words(30)) : null,
                'attachment' => $notice->attachment?->toApiPayload(),
            ])->all(),
        ];
    }
}
