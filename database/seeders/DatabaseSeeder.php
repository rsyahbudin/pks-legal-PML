<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed permissions first, then roles (which need permissions)
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingSeeder::class,
            DivisionSeeder::class,
        ]);

        // Create admin user
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'email_verified_at' => now(),
                'role_id' => $superAdminRole?->id,
            ]
        );

        // Create test user for legacy compatibility
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
    }
}
