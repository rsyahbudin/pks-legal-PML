<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        return [
            'REF_DIV_ID' => $this->faker->unique()->bothify('DIV###'),
            'REF_DIV_NAME' => $this->faker->company(),
            'IS_ACTIVE' => 1,
        ];
    }
}
