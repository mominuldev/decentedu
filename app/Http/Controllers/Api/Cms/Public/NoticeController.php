<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Models\Cms\Notice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notice::query()->published()->with(['terms:id,name,slug', 'attachment.media']);

        if ($request->filled('category')) {
            $slug = $request->string('category')->value();
            $query->whereHas('terms', fn ($t) => $t->where('slug', $slug));
        }
        if ($request->boolean('important')) {
            $query->where('is_important', true);
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where('title', 'like', "%{$search}%");
        }

        $notices = $query->orderByDesc('is_important')->orderByDesc('notice_date')->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 15), 50));

        return ApiResponse::success(
            collect($notices->items())->map(fn (Notice $n): array => $this->payload($n))->all(),
            'Notices retrieved.',
            ['pagination' => [
                'total' => $notices->total(), 'per_page' => $notices->perPage(),
                'current_page' => $notices->currentPage(), 'last_page' => $notices->lastPage(),
            ]],
        );
    }

    public function show(string $slug): JsonResponse
    {
        $notice = Notice::query()->published()->where('slug', $slug)
            ->with(['terms:id,name,slug', 'attachment.media'])->firstOrFail();

        return ApiResponse::success([...$this->payload($notice), 'body' => $notice->body], 'Notice retrieved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Notice $notice): array
    {
        $file = $notice->attachment?->file();

        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'slug' => $notice->slug,
            'notice_date' => $notice->notice_date?->toDateString(),
            'is_important' => $notice->is_important,
            'categories' => $notice->terms->map(fn ($t): array => ['name' => $t->name, 'slug' => $t->slug])->all(),
            'attachment' => $file === null ? null : [
                'name' => $notice->attachment?->name,
                'url' => $file->getUrl(),
                'mime_type' => $file->mime_type,
                'size' => $file->size,
            ],
        ];
    }
}
