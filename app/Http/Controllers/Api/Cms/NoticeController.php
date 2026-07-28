<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Enums\Cms\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Notices\NoticeRequest;
use App\Models\Cms\Notice;
use App\Models\Cms\Term;
use App\Support\ApiResponse;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notice::query()->with(['terms:id,name', 'attachment.media']);

        if ($request->boolean('trashed') || $request->input('status') === 'trashed') {
            $query->onlyTrashed();
        } elseif ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $sortable = ['title', 'status', 'is_important', 'notice_date', 'created_at'];
        $sort = $request->string('sort')->value();
        if (in_array($sort, $sortable, true)) {
            $dir = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sort, $dir)->orderByDesc('id');
        } else {
            $query->orderByDesc('notice_date')->orderByDesc('id');
        }

        $notices = $query->paginate(min($request->integer('per_page', 50), 200));

        return ApiResponse::success(
            collect($notices->items())->map(fn (Notice $n): array => [
                'id' => $n->id,
                'title' => $n->title,
                'slug' => $n->slug,
                'status' => $n->status->value,
                'is_important' => $n->is_important,
                'notice_date' => $n->notice_date?->toDateString(),
                'published_at' => $n->published_at?->toIso8601String(),
                'categories' => $n->terms->pluck('name')->all(),
                'attachment' => $n->attachment?->toApiPayload(),
                'deleted_at' => $n->deleted_at?->toIso8601String(),
            ])->all(),
            'Notices retrieved.',
            ['pagination' => [
                'total' => $notices->total(), 'per_page' => $notices->perPage(),
                'current_page' => $notices->currentPage(), 'last_page' => $notices->lastPage(),
            ]],
        );
    }

    public function meta(): JsonResponse
    {
        return ApiResponse::success([
            'statuses' => ContentStatus::options(),
            'terms' => Term::query()->with('taxonomy:id,name')->orderBy('name')
                ->whereHas('taxonomy', fn ($q) => $q->forObjectType('notice'))
                ->get(['id', 'name', 'taxonomy_id'])
                ->map(fn (Term $t): array => ['id' => $t->id, 'name' => $t->name, 'taxonomy' => $t->taxonomy?->name])->all(),
        ], 'Notice editor metadata.');
    }

    public function show(int|string $id): JsonResponse
    {
        $notice = $this->findNotice($id)->load(['terms:id,name', 'attachment.media']);

        return ApiResponse::success($this->editorPayload($notice), 'Notice retrieved.');
    }

    public function store(NoticeRequest $request): JsonResponse
    {
        $notice = $this->save(new Notice, $request);
        $notice->load(['terms:id,name', 'attachment.media']);

        return ApiResponse::success($this->editorPayload($notice), 'Notice created.', status: 201);
    }

    public function update(NoticeRequest $request, int|string $id): JsonResponse
    {
        $notice = $this->save($this->findNotice($id), $request);
        $notice->load(['terms:id,name', 'attachment.media']);

        return ApiResponse::success($this->editorPayload($notice), 'Notice updated.');
    }

    public function destroy(int|string $id): JsonResponse
    {
        $this->findNotice($id)->delete();

        return ApiResponse::success(null, 'Notice deleted.');
    }

    public function restore(int|string $id): JsonResponse
    {
        $this->findNotice($id, onlyTrashed: true)->restore();

        return ApiResponse::success(null, 'Notice restored.');
    }

    public function forceDelete(int|string $id): JsonResponse
    {
        $this->findNotice($id, onlyTrashed: true)->forceDelete();

        return ApiResponse::success(null, 'Notice permanently deleted.');
    }

    private function findNotice(int|string $idOrSlug, bool $onlyTrashed = false): Notice
    {
        $query = $onlyTrashed ? Notice::onlyTrashed() : Notice::query();

        return $query->where(fn ($q) => $q->where('id', $idOrSlug)->orWhere('slug', $idOrSlug))->firstOrFail();
    }

    private function save(Notice $notice, NoticeRequest $request): Notice
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        return DB::transaction(function () use ($notice, $data, $userId): Notice {
            $notice->fill(collect($data)->except(['terms'])->all());
            $notice->created_by ??= $userId;
            $notice->updated_by = $userId;

            if (! empty($notice->body)) {
                $notice->body = HtmlSanitizer::clean($notice->body);
            }

            if ($notice->status === ContentStatus::Published && $notice->published_at === null) {
                $notice->published_at = now();
            }

            $notice->save();

            if (array_key_exists('terms', $data)) {
                $notice->terms()->sync($data['terms'] ?? []);
            }

            return $notice;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function editorPayload(Notice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'slug' => $notice->slug,
            'body' => $notice->body,
            'notice_date' => $notice->notice_date?->toDateString(),
            'is_important' => $notice->is_important,
            'status' => $notice->status->value,
            'published_at' => $notice->published_at?->toIso8601String(),
            'attachment_asset_id' => $notice->attachment_asset_id,
            'attachment' => $notice->attachment?->toApiPayload(),
            'terms' => $notice->terms->pluck('id')->all(),
        ];
    }
}
