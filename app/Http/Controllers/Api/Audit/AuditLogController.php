<?php

namespace App\Http\Controllers\Api\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $query = AuditLog::with('user:id,name')
            ->where('branch_id', $branchId)
            ->when($request->filled('auditable_type'), fn ($q) => $q->where('auditable_type', $request->query('auditable_type')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->latest('created_at');

        $perPage = min((int) $request->query('per_page', 25), 200);
        $logs = $query->paginate($perPage);

        return ApiResponse::success(
            $logs->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'action' => $log->action,
                'changes' => $log->changes,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]),
            'Audit log retrieved.',
            ['pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ]],
        );
    }
}
