<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'organization_id' => function () {
                return Organization::factory()->create()->id;
            },
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'status' => true,
        ];
    }
}