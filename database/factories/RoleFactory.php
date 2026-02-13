<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->jobTitle();

        return [
            'ROLE_NAME' => $name,
            'ROLE_SLUG' => Str::slug($name),
            'GUARD_NAME' => 'web',
            'ROLE_DESCRIPTION' => $this->faker->sentence(),
            'IS_ACTIVE' => 1,
        ];
    }
}
