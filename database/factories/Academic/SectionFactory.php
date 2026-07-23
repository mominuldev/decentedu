<?php

namespace Database\Factories\Academic;

use App\Models\Academic\Section;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'branch_id' => function () {
                return Branch::factory()->create()->id;
            },
            'name' => fake()->randomElement(['A', 'B', 'C']),
            'name_bn' => fake()->randomElement(['ক', 'খ', 'গ']),
            'serial' => fake()->numberBetween(1, 3),
            'status' => true,
        ];
    }
}