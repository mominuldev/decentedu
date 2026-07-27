<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Models\Cms\Event;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->published()->with(['terms:id,name,slug', 'featuredAsset.media']);

        if ($request->filled('category')) {
            $slug = $request->string('category')->value();
            $query->whereHas('terms', fn ($t) => $t->where('slug', $slug));
        }
        if ($request->boolean('upcoming')) {
            $query->where('starts_at', '>=', now()->startOfDay());
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where('title', 'like', "%{$search}%");
        }

        $events = $query->orderBy('starts_at')->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 15), 50));

        return ApiResponse::success(
            collect($events->items())->map(fn (Event $e): array => $this->payload($e))->all(),
            'Events retrieved.',
            ['pagination' => [
                'total' => $events->total(), 'per_page' => $events->perPage(),
                'current_page' => $events->currentPage(), 'last_page' => $events->lastPage(),
            ]],
        );
    }

    public function show(string $slug): JsonResponse
    {
        $event = Event::query()->published()->where('slug', $slug)
            ->with(['terms:id,name,slug', 'featuredAsset.media'])->firstOrFail();

        return ApiResponse::success([...$this->payload($event), 'body' => $event->body], 'Event retrieved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'location' => $event->location,
            'categories' => $event->terms->map(fn ($t): array => ['name' => $t->name, 'slug' => $t->slug])->all(),
            'featured_asset' => $event->featuredAsset?->toApiPayload(),
        ];
    }
}
