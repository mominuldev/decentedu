<?php

namespace App\Models\Attendance;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['date', 'title', 'name_bn', 'type', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'status' => 'boolean'];
    }
}
