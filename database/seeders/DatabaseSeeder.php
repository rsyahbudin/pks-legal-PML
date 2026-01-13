<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\Division;
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
        $legalRole = Role::where('slug', 'legal')->first();
        $legalDivision = Division::where('code', 'LEGAL')->first();
        $legalDepartment = Department::where('code', 'LEGAL')->first();

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'email_verified_at' => now(),
                'role_id' => $superAdminRole?->id,
            ]
        );

        // Create test user for legacy compatibility
        User::firstOrCreate(
            ['email' => 'legal@example.com'],
            [
                'name' => 'Legal User',
                'password' => 'password',
                'email_verified_at' => now(),
                'role_id' => $legalRole?->id,
                'division_id' => $legalDivision?->id,
                'department_id' => $legalDepartment?->id,
            ]
        );
    }
}
