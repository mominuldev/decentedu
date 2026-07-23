<?php

namespace App\Models\Academic;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassConfig extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['class_id', 'shift_id', 'section_id', 'serial', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'serial' => 'integer'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Human label e.g. "Six · A · Day". */
    public function label(): string
    {
        return collect([
            $this->schoolClass?->name,
            $this->section?->name,
            $this->shift?->name,
        ])->filter()->implode(' · ');
    }
}
