<?php

namespace App\Jobs;

use App\Models\Attendance\AttendanceDeviceMap;
use App\Models\Attendance\AttendancePunch;
use App\Models\Attendance\AttendanceTimeConfig;
use App\Models\Attendance\EmployeeAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Models\Hr\Employee;
use App\Models\Students\Student;
use App\Support\BranchContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Resolves raw device punches into daily student/employee attendance: groups a
 * person's punches for a day into first-in/last-out, applies the matching time
 * config to decide present vs late, then upserts the attendance row.
 */
class ProcessAttendancePunches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $branchId)
    {
    }

    public function handle(): void
    {
        app(BranchContext::class)->set($this->branchId);

        $punches = AttendancePunch::where('processed', false)->orderBy('punched_at')->get();
        if ($punches->isEmpty()) {
            return;
        }

        $maps = AttendanceDeviceMap::where('status', true)->get()
            ->keyBy(fn (AttendanceDeviceMap $m) => $m->attendance_device_id.':'.$m->external_user_id);

        $groups = $punches->groupBy(fn (AttendancePunch $p) => $p->attendance_device_id.':'.$p->external_user_id.':'.$p->punched_at->toDateString());

        $processedIds = [];

        foreach ($groups as $key => $group) {
            [$deviceId, $externalUserId, $date] = explode(':', $key, 3);
            $map = $maps->get($deviceId.':'.$externalUserId);

            if (! $map) {
                continue; // Unmapped device user — left unprocessed for the mapping screen to surface.
            }

            $processedIds = array_merge($processedIds, $group->pluck('id')->all());

            $inTime = $group->min('punched_at');
            $outTime = $group->max('punched_at');

            if ($map->mappable_type === 'student') {
                $this->resolveStudent($map->mappable_id, $date, $inTime, $outTime);
            } else {
                $this->resolveEmployee($map->mappable_id, $date, $inTime, $outTime);
            }
        }

        AttendancePunch::whereIn('id', $processedIds)->update(['processed' => true, 'processed_at' => now()]);
    }

    private function resolveStudent(int $studentId, string $date, $inTime, $outTime): void
    {
        $student = Student::find($studentId);
        if (! $student) {
            return;
        }

        $enrollment = $student->currentEnrollment;
        $classConfigId = $enrollment?->class_config_id;

        $timeConfig = AttendanceTimeConfig::where('applicable_to', 'student')
            ->where('status', true)
            ->where(fn ($q) => $q->where('class_config_id', $classConfigId)->orWhereNull('class_config_id'))
            ->orderByRaw('class_config_id IS NULL')
            ->first();

        StudentAttendance::updateOrCreate(
            ['student_id' => $studentId, 'date' => $date],
            [
                'enrollment_id' => $enrollment?->id,
                'class_config_id' => $classConfigId,
                'status' => $this->status($inTime, $timeConfig),
                'in_time' => $inTime->format('H:i:s'),
                'out_time' => $outTime->format('H:i:s'),
                'source' => 'device',
            ],
        );
    }

    private function resolveEmployee(int $employeeId, string $date, $inTime, $outTime): void
    {
        if (! Employee::find($employeeId)) {
            return;
        }

        $timeConfig = AttendanceTimeConfig::where('applicable_to', 'employee')->where('status', true)->first();

        EmployeeAttendance::updateOrCreate(
            ['employee_id' => $employeeId, 'date' => $date],
            [
                'status' => $this->status($inTime, $timeConfig),
                'in_time' => $inTime->format('H:i:s'),
                'out_time' => $outTime->format('H:i:s'),
                'source' => 'device',
            ],
        );
    }

    private function status($inTime, ?AttendanceTimeConfig $timeConfig): string
    {
        if (! $timeConfig) {
            return 'present';
        }

        return $inTime->format('H:i:s') > $timeConfig->late_after ? 'late' : 'present';
    }
}
