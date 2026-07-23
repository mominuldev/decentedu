<?php

namespace App\Models\Cms;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'title', 'slug', 'body', 'description', 'keywords', 'image_path',
        'meta', 'status', 'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'published_at' => 'datetime'];
    }
}
