<?php

namespace Database\Factories\Academic;

use App\Models\Academic\AcademicYear;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        return [
            'branch_id' => function () {
                return Branch::factory()->create()->id;
            },
            'name' => fake()->randomElement(['2024-2025', '2025-2026', '2026-2027']),
            'name_bn' => fake()->randomElement(['২০২৪-২০২৫', '২০২৫-২০২৬', '২০২৬-২০২৭']),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
            'is_current' => fake()->boolean(),
            'serial' => fake()->numberBetween(1, 3),
            'status' => true,
        ];
    }
}