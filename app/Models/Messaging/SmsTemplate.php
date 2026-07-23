<?php

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsTemplate extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'type', 'message', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }
}
