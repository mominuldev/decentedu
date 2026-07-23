<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\Reporting\Definitions\AdmitCardReport;
use App\Support\Reporting\Definitions\AttendanceSheetReport;
use App\Support\Reporting\Definitions\SeatPlanReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admit card, seat plan and attendance sheet data for one class_config x exam.
 * [inferred: legacy's seat allocation algorithm was flagged as a gap even in the
 * source analysis (docs/02 §"Roll/seat uniqueness... 🔧") — this is a simple
 * sequential-by-roll placeholder, not a reproduction of a confirmed legacy algorithm.]
 */
class AdmitController extends Controller
{
    public function admitCard(Request $request): JsonResponse
    {
        $definition = app(AdmitCardReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success([
            'students' => $data['students'],
            'routine' => $data['routine'],
            'instructions' => $data['instructions'],
            'signatures' => $data['signatures'],
        ], 'Admit card data retrieved.');
    }

    /** Sequential seat allocation by roll, filling one room to capacity before moving to the next. */
    public function seatPlan(Request $request): JsonResponse
    {
        $definition = app(SeatPlanReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success($data['rows'], 'Seat plan generated.');
    }

    public function attendanceSheet(Request $request): JsonResponse
    {
        $definition = app(AttendanceSheetReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success($data['rows'], 'Attendance sheet retrieved.');
    }
}
