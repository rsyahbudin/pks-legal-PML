<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'DIV_ID' => Division::factory(),
            'REF_DEPT_ID' => $this->faker->unique()->bothify('DEPT###'),
            'REF_DEPT_NAME' => $this->faker->jobTitle(),
        ];
    }
}
