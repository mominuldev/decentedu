<?php

namespace App\Models\Examinations;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Signature extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['position', 'person_name', 'designation', 'image_path', 'serial', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'serial' => 'integer'];
    }
}
