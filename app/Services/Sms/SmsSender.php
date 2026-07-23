<?php

namespace App\Services\Sms;

use App\Jobs\SendSmsBatch;
use App\Models\Messaging\SmsBalance;
use App\Models\Messaging\SmsBatch;
use App\Models\Messaging\SmsMessage;
use Illuminate\Support\Facades\DB;

/**
 * Shared send path for both the interactive "Send" screen and automated hooks (e.g. absentee
 * attendance notices) — centralizes the balance guardrail (K6) so every SMS, however triggered,
 * is debited and logged the same way. Assumes BranchContext is already set for $branchId by the
 * caller (request middleware, or a queued job that sets it explicitly).
 */
class SmsSender
{
    /**
     * @param  array<int, array{phone: string, name?: string|null, student_id?: int|null, employee_id?: int|null}>  $recipients
     */
    public function send(
        int $branchId,
        string $audienceType,
        array $recipients,
        string $message,
        ?int $templateId = null,
        ?array $audienceFilter = null,
        ?int $createdBy = null,
    ): SmsBatch {
        $recipients = array_values(array_filter($recipients, fn (array $r) => filled($r['phone'] ?? null)));
        $count = count($recipients);

        if ($count === 0) {
            throw new \InvalidArgumentException('No recipients with a phone number.');
        }

        $unitCost = (float) config('sms.unit_cost');
        $totalCost = round($unitCost * $count, 2);

        $batch = DB::transaction(function () use ($branchId, $audienceType, $audienceFilter, $message, $templateId, $createdBy, $count, $unitCost, $totalCost, $recipients) {
            $balance = SmsBalance::firstOrCreate(['branch_id' => $branchId], ['balance' => 0]);

            if ($balance->balance < $totalCost) {
                throw new InsufficientSmsBalanceException($totalCost, (float) $balance->balance);
            }

            $balance->decrement('balance', $totalCost);

            $batch = SmsBatch::create([
                'template_id' => $templateId,
                'audience_type' => $audienceType,
                'audience_filter' => $audienceFilter,
                'message' => $message,
                'total_recipients' => $count,
                'status' => 'processing',
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'created_by' => $createdBy,
            ]);

            foreach ($recipients as $r) {
                SmsMessage::create([
                    'batch_id' => $batch->id,
                    'recipient_phone' => $r['phone'],
                    'recipient_name' => $r['name'] ?? null,
                    'student_id' => $r['student_id'] ?? null,
                    'employee_id' => $r['employee_id'] ?? null,
                    'message' => $message,
                    'status' => 'queued',
                ]);
            }

            return $batch;
        });

        SendSmsBatch::dispatch($batch->id, $branchId);

        return $batch;
    }
}
