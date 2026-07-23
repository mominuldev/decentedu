<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * No real SMS gateway is configured for this project yet — this logs the send instead of
 * making an HTTP call, and always reports success. Swap the binding in AppServiceProvider
 * for a real provider (e.g. an HTTP-based gateway) when one is available; callers only
 * depend on SmsGatewayInterface so no other code changes.
 */
class LogSmsGateway implements SmsGatewayInterface
{
    public function send(string $phone, string $message): array
    {
        Log::info('sms.send', ['phone' => $phone, 'message' => $message]);

        return ['status' => 'sent', 'response' => 'logged'];
    }
}
