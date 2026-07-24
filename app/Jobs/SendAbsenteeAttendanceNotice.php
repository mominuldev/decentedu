<?php

namespace App\Jobs;

use App\Models\Attendance\StudentAttendance;
use App\Models\Messaging\SmsTemplate;
use App\Services\Sms\InsufficientSmsBalanceException;
use App\Services\Sms\SmsSender;
use App\Support\BranchContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Absentee guardian notification hook (doc 02: "Absentee SMS template integration"). Uses the
 * branch's active 'attendance'-type SmsTemplate if one exists, otherwise a generic default
 * message — sent through the same SmsSender path (and balance guardrail) as manual sends.
 */
class SendAbsenteeAttendanceNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $studentAttendanceId) {}

    public function handle(SmsSender $sender): void
    {
        $attendance = StudentAttendance::withoutBranchScope()->with('student')->find($this->studentAttendanceId);
        if (! $attendance || $attendance->status !== 'absent' || ! $attendance->student) {
            return;
        }

        app(BranchContext::class)->set($attendance->branch_id);

        $student = $attendance->student;
        $phone = $student->father_mobile ?: $student->mother_mobile ?: $student->mobile;
        if (! $phone) {
            return;
        }

        $template = SmsTemplate::where('type', 'attendance')->where('status', true)->first();
        $message = $template
            ? str_replace(
                ['{student_name}', '{date}'],
                [$student->name, $attendance->date->toDateString()],
                $template->message,
            )
            : "Dear Guardian, {$student->name} was absent on {$attendance->date->toDateString()}.";

        try {
            $sender->send(
                branchId: $attendance->branch_id,
                audienceType: 'custom_numbers',
                recipients: [['phone' => $phone, 'name' => $student->name, 'student_id' => $student->id]],
                message: $message,
                templateId: $template?->id,
            );
        } catch (InsufficientSmsBalanceException $e) {
            Log::warning('Absentee SMS skipped: insufficient balance', ['student_id' => $student->id, 'error' => $e->getMessage()]);
        }
    }
}
