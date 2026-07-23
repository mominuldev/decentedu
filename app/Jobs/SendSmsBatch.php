<?php

namespace App\Jobs;

use App\Models\Messaging\SmsBatch;
use App\Models\Messaging\SmsMessage;
use App\Services\Sms\SmsGatewayInterface;
use App\Support\BranchContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $batchId, private readonly int $branchId)
    {
    }

    public function handle(SmsGatewayInterface $gateway): void
    {
        app(BranchContext::class)->set($this->branchId);

        $batch = SmsBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        $sent = 0;
        $failed = 0;

        SmsMessage::where('batch_id', $batch->id)->where('status', 'queued')
            ->each(function (SmsMessage $sms) use ($gateway, &$sent, &$failed) {
                $result = $gateway->send($sms->recipient_phone, $sms->message);
                $ok = ($result['status'] ?? 'failed') === 'sent';

                $sms->update([
                    'status' => $ok ? 'sent' : 'failed',
                    'gateway_response' => $result['response'] ?? null,
                    'sent_at' => now(),
                ]);

                $ok ? $sent++ : $failed++;
            });

        $batch->update([
            'sent_count' => $sent,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'completed' : ($sent === 0 ? 'failed' : 'completed'),
        ]);
    }
}
