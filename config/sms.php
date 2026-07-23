<?php

return [
    // Flat cost per SMS debited from the branch balance on send. No real gateway is wired up
    // (see App\Services\Sms\LogSmsGateway), so this only drives the balance-guardrail logic.
    'unit_cost' => (float) env('SMS_UNIT_COST', 0.5),
];
