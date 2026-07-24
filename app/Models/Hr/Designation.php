<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'name_bn',
        'serial',
        'status',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'serial' => 'integer',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    // Scope for active designations
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
