<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin role with all permissions
        $superAdmin = Role::updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Akses penuh ke semua fitur sistem',
                'is_system' => true,
            ]
        );
        $superAdmin->syncPermissions(Permission::pluck('id')->toArray());

        // Create Legal role
        $legal = Role::updateOrCreate(
            ['slug' => 'legal'],
            [
                'name' => 'Legal',
                'description' => 'Tim legal dengan akses penuh ke kontrak',
                'is_system' => true,
            ]
        );
        $legalPermissions = Permission::whereIn('slug', [
            'dashboard.view',
            'contracts.view',
            'contracts.create',
            'contracts.edit',
            'contracts.delete',
            'contracts.send_reminder',
            'partners.view',
            'partners.create',
            'partners.edit',
            'partners.delete',
            'divisions.view',
            'users.view',
            'reports.view',
            'reports.export',
            'settings.view',
        ])->pluck('id')->toArray();
        $legal->syncPermissions($legalPermissions);

        // Create PIC role
        $pic = Role::updateOrCreate(
            ['slug' => 'pic'],
            [
                'name' => 'PIC',
                'description' => 'Person In Charge - akses kontrak yang ditugaskan',
                'is_system' => true,
            ]
        );
        $picPermissions = Permission::whereIn('slug', [
            'dashboard.view',
            'contracts.view',
            'partners.view',
            'divisions.view',
        ])->pluck('id')->toArray();
        $pic->syncPermissions($picPermissions);

        // Create Management role
        $management = Role::updateOrCreate(
            ['slug' => 'management'],
            [
                'name' => 'Management',
                'description' => 'Akses read-only untuk monitoring',
                'is_system' => true,
            ]
        );
        $managementPermissions = Permission::whereIn('slug', [
            'dashboard.view',
            'contracts.view',
            'partners.view',
            'divisions.view',
            'reports.view',
        ])->pluck('id')->toArray();
        $management->syncPermissions($managementPermissions);
    }
}
