<?php

namespace App\Models\Reporting;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportArtifact extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'report_key', 'format', 'params', 'status',
        'file_path', 'error_message', 'requested_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['params' => 'array', 'completed_at' => 'datetime'];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
