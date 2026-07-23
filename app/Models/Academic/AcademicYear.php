<?php

namespace App\Models\Academic;

class AcademicYear extends SetupModel
{
    protected $fillable = ['name', 'name_bn', 'start_date', 'end_date', 'is_current', 'serial', 'status'];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'is_current' => 'boolean',
            'serial' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
