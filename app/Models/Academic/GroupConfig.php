<?php

namespace App\Models\Academic;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupConfig extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['class_id', 'group_id', 'serial', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'serial' => 'integer'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
