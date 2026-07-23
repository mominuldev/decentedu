<?php

namespace App\Events;

use App\Models\Fees\FeeCollection;
use Illuminate\Foundation\Events\Dispatchable;

class FeeCollected
{
    use Dispatchable;

    public function __construct(public FeeCollection $collection) {}
}
