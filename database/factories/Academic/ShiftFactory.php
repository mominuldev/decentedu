<?php

namespace Database\Factories\Academic;

use App\Models\Academic\Shift;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'branch_id' => function () {
                return Branch::factory()->create()->id;
            },
            'name' => fake()->randomElement(['Morning', 'Day', 'Evening']),
            'name_bn' => fake()->randomElement(['সকাল', 'দিন', 'সন্ধ্যা']),
            'serial' => fake()->numberBetween(1, 3),
            'status' => true,
        ];
    }
}
