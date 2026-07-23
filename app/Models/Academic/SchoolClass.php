<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends SetupModel
{
    protected $table = 'classes';

    public function classConfigs(): HasMany
    {
        return $this->hasMany(ClassConfig::class, 'class_id');
    }
}
