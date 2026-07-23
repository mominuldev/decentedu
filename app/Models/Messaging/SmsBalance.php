<?php

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class SmsBalance extends Model
{
    use BelongsToBranch;

    protected $fillable = ['balance'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }
}
