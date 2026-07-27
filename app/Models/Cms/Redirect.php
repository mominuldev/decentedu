<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    use BelongsToBranch;
    use HasFactory;

    protected $fillable = ['from_path', 'to_path', 'status_code', 'is_active'];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    public function recordHit(): void
    {
        $this->forceFill(['last_hit_at' => now()])->increment('hits');
    }

    /**
     * Normalize a path for matching: no leading/trailing slashes, lowercase.
     */
    public static function normalizePath(string $path): string
    {
        return strtolower(trim($path, '/'));
    }
}
