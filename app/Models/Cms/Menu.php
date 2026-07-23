<?php

namespace App\Models\Cms;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['name', 'location', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('serial');
    }
}
