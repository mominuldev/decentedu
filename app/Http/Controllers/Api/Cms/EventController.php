<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Enums\Cms\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Events\EventRequest;
use App\Models\Cms\Event;
use App\Models\Cms\Term;
use App\Support\ApiResponse;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->with(['terms:id,name', 'featuredAsset.media']);

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }
        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->string('search')->value().'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $sortable = ['title', 'status', 'starts_at', 'created_at'];
        $sort = $request->string('sort')->value();
        if (in_array($sort, $sortable, true)) {
            $dir = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sort, $dir)->orderByDesc('id');
        } else {
            $query->orderByDesc('starts_at')->orderByDesc('id');
        }

        $events = $query->paginate(min($request->integer('per_page', 50), 200));

        return ApiResponse::success(
            collect($events->items())->map(fn (Event $e): array => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'status' => $e->status->value,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'location' => $e->location,
                'categories' => $e->terms->pluck('name')->all(),
                'featured_asset' => $e->featuredAsset?->toApiPayload(),
                'deleted_at' => $e->deleted_at?->toIso8601String(),
            ])->all(),
            'Events retrieved.',
            ['pagination' => [
                'total' => $events->total(), 'per_page' => $events->perPage(),
                'current_page' => $events->currentPage(), 'last_page' => $events->lastPage(),
            ]],
        );
    }

    public function meta(): JsonResponse
    {
        return ApiResponse::success([
            'statuses' => ContentStatus::options(),
            'terms' => Term::query()->with('taxonomy:id,name')->orderBy('name')
                ->whereHas('taxonomy', fn ($q) => $q->forObjectType('event'))
                ->get(['id', 'name', 'taxonomy_id'])
                ->map(fn (Term $t): array => ['id' => $t->id, 'name' => $t->name, 'taxonomy' => $t->taxonomy?->name])->all(),
        ], 'Event editor metadata.');
    }

    public function show(int $id): JsonResponse
    {
        $event = Event::query()->with(['terms:id,name', 'featuredAsset.media'])->findOrFail($id);

        return ApiResponse::success($this->editorPayload($event), 'Event retrieved.');
    }

    public function store(EventRequest $request): JsonResponse
    {
        $event = $this->save(new Event, $request);
        $event->load(['terms:id,name', 'featuredAsset.media']);

        return ApiResponse::success($this->editorPayload($event), 'Event created.', status: 201);
    }

    public function update(EventRequest $request, int $id): JsonResponse
    {
        $event = $this->save(Event::findOrFail($id), $request);
        $event->load(['terms:id,name', 'featuredAsset.media']);

        return ApiResponse::success($this->editorPayload($event), 'Event updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Event::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Event deleted.');
    }

    public function restore(int $id): JsonResponse
    {
        Event::onlyTrashed()->findOrFail($id)->restore();

        return ApiResponse::success(null, 'Event restored.');
    }

    private function save(Event $event, EventRequest $request): Event
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        return DB::transaction(function () use ($event, $data, $userId): Event {
            $event->fill(collect($data)->except(['terms'])->all());
            $event->created_by ??= $userId;
            $event->updated_by = $userId;

            if (! empty($event->body)) {
                $event->body = HtmlSanitizer::clean($event->body);
            }

            if ($event->status === ContentStatus::Published && $event->published_at === null) {
                $event->published_at = now();
            }

            $event->save();

            if (array_key_exists('terms', $data)) {
                $event->terms()->sync($data['terms'] ?? []);
            }

            return $event;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function editorPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'body' => $event->body,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'location' => $event->location,
            'status' => $event->status->value,
            'published_at' => $event->published_at?->toIso8601String(),
            'featured_asset_id' => $event->featured_asset_id,
            'featured_asset' => $event->featuredAsset?->toApiPayload(),
            'terms' => $event->terms->pluck('id')->all(),
        ];
    }
}
