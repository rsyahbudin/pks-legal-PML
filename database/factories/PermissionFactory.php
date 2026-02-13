<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'PERMISSION_NAME' => $name,
            'GUARD_NAME' => 'web',
            'PERMISSION_ID' => (string) Str::uuid(),
        ];
    }
}
