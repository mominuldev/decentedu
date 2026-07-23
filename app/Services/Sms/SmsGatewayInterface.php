<?php

namespace App\Services\Sms;

interface SmsGatewayInterface
{
    /**
     * Send a single SMS. Returns ['status' => 'sent'|'failed', 'response' => string].
     *
     * @return array{status: string, response: string}
     */
    public function send(string $phone, string $message): array;
}
