<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use App\Models\Cms\Event;
use App\Models\Cms\Notice;
use Illuminate\Validation\Rule;

class NoticeBoardBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::NoticeBoard;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'cta_variant' => ['nullable', Rule::in(['primary', 'secondary', 'outline', 'ghost'])],
            'cta_target' => ['nullable', Rule::in(['self', 'blank'])],
            'event_box_title' => ['nullable', 'string', 'max:255'],
            'notices_mode' => ['nullable', Rule::in(['latest', 'important', 'selected'])],
            'notices_limit' => ['nullable', 'integer', 'between:1,20'],
            'notice_ids' => ['nullable', 'array'],
            'notice_ids.*' => ['integer', Rule::exists('notices', 'id')],
            'events_mode' => ['nullable', Rule::in(['upcoming', 'latest', 'selected'])],
            'events_limit' => ['nullable', 'integer', 'between:1,20'],
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['integer', Rule::exists('events', 'id')],
        ];
    }

    public function toResource(array $payload): array
    {
        // Resolve notices based on mode
        $noticesMode = $payload['notices_mode'] ?? 'latest';
        $noticesLimit = (int) ($payload['notices_limit'] ?? 5);

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
            ->limit($noticesLimit)
            ->get();

        // Resolve events based on mode
        $eventsMode = $payload['events_mode'] ?? 'upcoming';
        $eventsLimit = (int) ($payload['events_limit'] ?? 5);

        $eventsQuery = Event::query()
            ->with(['featuredAsset.media'])
            ->published();

        if ($eventsMode === 'upcoming') {
            $eventsQuery->where('starts_at', '>=', now());
        } elseif ($eventsMode === 'selected' && !empty($payload['event_ids'])) {
            $eventsQuery->whereIn('id', $payload['event_ids']);
        }

        $events = $eventsQuery
            ->orderBy('starts_at')
            ->limit($eventsLimit)
            ->get();

        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'heading' => $payload['heading'] ?? null,
            'description' => $payload['description'] ?? null,
            'cta_label' => $payload['cta_label'] ?? null,
            'cta_url' => $payload['cta_url'] ?? null,
            'cta_variant' => $payload['cta_variant'] ?? 'primary',
            'cta_target' => $payload['cta_target'] ?? 'self',
            'event_box_title' => $payload['event_box_title'] ?? null,
            'notices_mode' => $noticesMode,
            'notices' => $notices->map(fn (Notice $notice): array => [
                'id' => $notice->id,
                'title' => $notice->title,
                'slug' => $notice->slug,
                'notice_date' => $notice->notice_date?->toDateString(),
                'is_important' => $notice->is_important,
                'excerpt' => $notice->body ? strip_tags(str($notice->body)->words(30)) : null,
                'attachment' => $notice->attachment?->toApiPayload(),
            ])->all(),
            'events_mode' => $eventsMode,
            'events' => $events->map(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'location' => $event->location,
                'excerpt' => $event->body ? strip_tags(str($event->body)->words(30)) : null,
                'featured_image' => $event->featuredAsset?->toApiPayload(),
            ])->all(),
        ];
    }
}
