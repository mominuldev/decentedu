<?php

namespace Database\Factories\Academic;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Shift;
use App\Models\Academic\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassConfigFactory extends Factory
{
    protected $model = ClassConfig::class;

    public function definition(): array
    {
        return [
            'branch_id' => function () {
                return \App\Models\Branch::factory()->create()->id;
            },
            'class_id' => function () {
                return SchoolClass::factory()->create()->id;
            },
            'shift_id' => function () {
                return Shift::factory()->create()->id;
            },
            'section_id' => function () {
                return Section::factory()->create()->id;
            },
            'serial' => fake()->numberBetween(1, 10),
            'status' => true,
        ];
    }
}