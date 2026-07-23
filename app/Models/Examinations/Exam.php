<?php

namespace App\Models\Examinations;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'name_bn', 'type', 'serial', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'serial' => 'integer'];
    }
}
