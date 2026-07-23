<?php

namespace App\Jobs;

use App\Models\Attendance\StudentAttendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Absentee guardian notification hook. Phase 8 (SMS module) will replace the
 * log line below with a real template lookup + gateway send; this placeholder
 * keeps attendance-taking decoupled from that not-yet-built subsystem while
 * still firing at the right moment (per doc 02: "Absentee SMS template
 * integration").
 */
class SendAbsenteeAttendanceNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $studentAttendanceId)
    {
    }

    public function handle(): void
    {
        $attendance = StudentAttendance::withoutBranchScope()->with('student')->find($this->studentAttendanceId);
        if (! $attendance || $attendance->status !== 'absent') {
            return;
        }

        Log::info('Absentee SMS hook (Phase 8 gateway pending)', [
            'student_id' => $attendance->student_id,
            'student_name' => $attendance->student?->name,
            'date' => $attendance->date->toDateString(),
        ]);
    }
}
