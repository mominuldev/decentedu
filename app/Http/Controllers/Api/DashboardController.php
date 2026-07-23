<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance\StudentAttendance;
use App\Models\Fees\FeeCollection;
use App\Models\Students\Student;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Top-line counters only (the richer charts/notices on the dashboard are still frontend
     * placeholder data — a full real-data rebuild of those is a separate feature, not a
     * performance-phase caching task). Short TTL: cheap to recompute, doesn't need to be exact
     * to the second.
     */
    public function index(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $today = now()->toDateString();

        $data = Cache::remember("dashboard:{$branchId}:{$today}", 300, function () use ($today) {
            $attendanceToday = StudentAttendance::where('date', $today)->get()->countBy('status');

            return [
                'total_students' => Student::where('status', 'active')->count(),
                'present_today' => ($attendanceToday->get('present') ?? 0) + ($attendanceToday->get('late') ?? 0),
                'absent_today' => ($attendanceToday->get('absent') ?? 0) + ($attendanceToday->get('leave') ?? 0) + ($attendanceToday->get('half_day') ?? 0),
                'collection_today' => (float) FeeCollection::whereDate('collected_at', $today)->sum('total_amount'),
            ];
        });

        return ApiResponse::success($data, 'Dashboard summary');
    }
}
