<?php

namespace App\Models\Credentials;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IdCardTemplate extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'holder_type', 'fields', 'show_qr', 'primary_color', 'logo_path',
        'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['fields' => 'array', 'show_qr' => 'boolean', 'status' => 'boolean'];
    }
}
