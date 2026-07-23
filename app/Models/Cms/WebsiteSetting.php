<?php

namespace App\Models\Cms;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'site_title', 'tagline', 'logo_path', 'favicon_path', 'address', 'phone',
        'email', 'social_links', 'meta_description', 'status',
    ];

    protected function casts(): array
    {
        return ['social_links' => 'array', 'status' => 'boolean'];
    }
}
