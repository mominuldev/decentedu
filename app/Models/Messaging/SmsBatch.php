<?php

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsBatch extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'template_id', 'audience_type', 'audience_filter', 'message', 'total_recipients',
        'sent_count', 'failed_count', 'status', 'unit_cost', 'total_cost', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience_filter' => 'array',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'template_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'batch_id');
    }
}
