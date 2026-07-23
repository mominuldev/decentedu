<?php

namespace App\Models\Academic;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base for the uniform branch-scoped reference tables
 * (name / name_bn / serial / status).
 */
abstract class SetupModel extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'name_bn', 'serial', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'serial' => 'integer'];
    }
}
