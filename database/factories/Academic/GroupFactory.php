<?php

namespace Database\Factories\Academic;

use App\Models\Academic\Group;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'branch_id' => function () {
                return Branch::factory()->create()->id;
            },
            'name' => fake()->randomElement(['Science', 'Arts', 'Commerce']),
            'name_bn' => fake()->randomElement(['বিজ্ঞান', 'কলা', 'বাণিজ্য']),
            'serial' => fake()->numberBetween(1, 3),
            'status' => true,
        ];
    }
}
