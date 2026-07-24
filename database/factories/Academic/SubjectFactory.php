<?php

namespace Database\Factories\Academic;

use App\Models\Academic\Subject;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'branch_id' => function () {
                return Branch::factory()->create()->id;
            },
            'name' => fake()->randomElement(['Mathematics', 'Physics', 'Chemistry', 'Biology', 'English']),
            'name_bn' => fake()->randomElement(['গণিত', 'পদার্থবিজ্ঞান', 'রসায়ন', 'জীববিজ্ঞান', 'ইংরেজি']),
            'code' => fake()->randomElement(['MATH', 'PHY', 'CHEM', 'BIO', 'ENG']),
            'serial' => fake()->numberBetween(1, 5),
            'status' => true,
        ];
    }
}
