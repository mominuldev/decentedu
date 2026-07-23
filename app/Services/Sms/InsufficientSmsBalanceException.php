<?php

namespace App\Services\Sms;

use RuntimeException;

class InsufficientSmsBalanceException extends RuntimeException
{
    public function __construct(public readonly float $required, public readonly float $available)
    {
        parent::__construct("SMS balance too low: need {$required}, have {$available}.");
    }
}
