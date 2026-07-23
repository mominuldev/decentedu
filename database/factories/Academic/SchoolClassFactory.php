<?php

namespace Database\Factories\Academic;

use App\Models\Academic\SchoolClass;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        return [
            'branch_id' => function () {
                return Branch::factory()->create()->id;
            },
            'name' => fake()->randomElement(['Six', 'Seven', 'Eight', 'Nine', 'Ten']),
            'name_bn' => fake()->randomElement(['ষষ্ঠ', 'সপ্তম', 'অষ্টম', 'নবম', 'দশম']),
            'serial' => fake()->numberBetween(1, 10),
            'status' => true,
        ];
    }
}