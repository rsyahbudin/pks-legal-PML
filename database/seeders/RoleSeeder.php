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
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Administrator dengan akses penuh ke seluruh sistem',
            ],
            [
                'name' => 'Legal',
                'slug' => 'legal',
                'description' => 'Tim Legal yang mengelola dan memproses tickets serta contracts',
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'description' => 'User dari departemen lain yang dapat membuat ticket legal request',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }

        // Assign permissions to roles
        $allPermissions = Permission::all();

        // Super Admin - all permissions
        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->sync($allPermissions->pluck('id'));
        }

        // Legal - full ticket & contract management
        $legal = Role::where('slug', 'legal')->first();
        if ($legal) {
            $legalPermissions = Permission::whereIn('slug', [
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
            ])->pluck('id');
            $legal->permissions()->sync($legalPermissions);
        }

        // User - create tickets and view
        $user = Role::where('slug', 'user')->first();
        if ($user) {
            $userPermissions = Permission::whereIn('slug', [
                // Dashboard
                'dashboard.my-tickets.view',
                'dashboard.contracts.view',
                // Tickets - create and view only
                'tickets.view',
                'tickets.create',
                // Contracts - view only
                'contracts.view',
            ])->pluck('id');
            $user->permissions()->sync($userPermissions);
        }
    }
}
