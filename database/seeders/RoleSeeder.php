<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'ROLE_NAME' => 'Super Admin',
                'ROLE_SLUG' => 'super-admin',
                'ROLE_DESCRIPTION' => 'Administrator dengan akses penuh ke seluruh sistem',
            ],
            [
                'ROLE_NAME' => 'Legal',
                'ROLE_SLUG' => 'legal',
                'ROLE_DESCRIPTION' => 'Tim Legal yang mengelola dan memproses tickets serta contracts',
            ],
            [
                'ROLE_NAME' => 'User',
                'ROLE_SLUG' => 'user',
                'ROLE_DESCRIPTION' => 'User dari departemen lain yang dapat membuat ticket legal request',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['ROLE_SLUG' => $role['ROLE_SLUG']],
                $role
            );
        }

        // Assign permissions to roles
        $allPermissions = Permission::all();

        // Super Admin - all permissions
        $superAdmin = Role::where('ROLE_SLUG', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->sync($allPermissions->pluck('LGL_ROW_ID'));
        }

        // Legal - full ticket & contract management
        $legal = Role::where('ROLE_SLUG', 'legal')->first();
        if ($legal) {
            $legalPermissions = Permission::whereIn('PERMISSION_CODE', [
                // Dashboard
                'dashboard.tickets.view',
                'dashboard.aging.view',
                'dashboard.contracts.view',
                // Tickets - full access
                'tickets.view',
                'tickets.create',
                'tickets.edit',
                'tickets.process',
                'tickets.reject',
                'tickets.complete',
                // Contracts
                'contracts.view',
                'contracts.edit',
                'contracts.terminate',
                'contracts.send_reminder',
                // Reference data
                'divisions.view',
                'departments.view',
            ])->pluck('LGL_ROW_ID');
            $legal->permissions()->sync($legalPermissions);
        }

        // User - create tickets and view
        $user = Role::where('ROLE_SLUG', 'user')->first();
        if ($user) {
            $userPermissions = Permission::whereIn('PERMISSION_CODE', [
                // Dashboard
                'dashboard.my-tickets.view',
                'dashboard.contracts.view',
                // Tickets - create and view only
                'tickets.view',
                'tickets.create',
                // Contracts - view only
                'contracts.view',
            ])->pluck('LGL_ROW_ID');
            $user->permissions()->sync($userPermissions);
        }
    }
}
