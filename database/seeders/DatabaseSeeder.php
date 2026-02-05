<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        // Create users
        $adminRole = Role::where('slug', 'super-admin')->first();
        $legalRole = Role::where('slug', 'legal')->first();
        $userRole = Role::where('slug', 'user')->first();

        // Get Legal division and department for default assignment
        $legalDivision = Division::where('code', 'LEGAL')->first();
        $legalDepartment = Department::where('code', 'LEGAL')->first();

        // Admin user - assign to Legal division/dept
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'division_id' => $legalDivision->id,
                'department_id' => $legalDepartment->id,
            ]
        );

        // Create test user for legacy compatibility
        User::updateOrCreate(
            ['email' => 'legal@example.com'],
            [
                'name' => 'Legal User',
                'password' => Hash::make('password'),
                'role_id' => $legalRole?->id,
                'division_id' => $legalDivision?->id,
                'department_id' => $legalDepartment?->id,
            ]
        );
    }
}
