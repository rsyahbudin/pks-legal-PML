<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'group' => 'dashboard', 'description' => 'Akses halaman dashboard'],

            // Contracts
            ['name' => 'View Contracts', 'slug' => 'contracts.view', 'group' => 'contracts', 'description' => 'Melihat daftar kontrak'],
            ['name' => 'Create Contracts', 'slug' => 'contracts.create', 'group' => 'contracts', 'description' => 'Membuat kontrak baru'],
            ['name' => 'Edit Contracts', 'slug' => 'contracts.edit', 'group' => 'contracts', 'description' => 'Mengedit kontrak'],
            ['name' => 'Delete Contracts', 'slug' => 'contracts.delete', 'group' => 'contracts', 'description' => 'Menghapus kontrak'],
            ['name' => 'Send Contract Reminder', 'slug' => 'contracts.send_reminder', 'group' => 'contracts', 'description' => 'Mengirim email reminder kontrak secara manual'],

            // Partners
            ['name' => 'View Partners', 'slug' => 'partners.view', 'group' => 'partners', 'description' => 'Melihat daftar partner/vendor'],
            ['name' => 'Create Partners', 'slug' => 'partners.create', 'group' => 'partners', 'description' => 'Menambah partner baru'],
            ['name' => 'Edit Partners', 'slug' => 'partners.edit', 'group' => 'partners', 'description' => 'Mengedit partner'],
            ['name' => 'Delete Partners', 'slug' => 'partners.delete', 'group' => 'partners', 'description' => 'Menghapus partner'],

            // Divisions
            ['name' => 'View Divisions', 'slug' => 'divisions.view', 'group' => 'divisions', 'description' => 'Melihat daftar divisi'],
            ['name' => 'Manage Divisions', 'slug' => 'divisions.manage', 'group' => 'divisions', 'description' => 'Mengelola divisi (tambah/edit/hapus)'],

            // Users
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users', 'description' => 'Melihat daftar pengguna'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'users', 'description' => 'Mengelola pengguna (tambah/edit/hapus)'],

            // Roles
            ['name' => 'View Roles', 'slug' => 'roles.view', 'group' => 'roles', 'description' => 'Melihat daftar role'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'group' => 'roles', 'description' => 'Mengelola role dan permission'],

            // Settings
            ['name' => 'View Settings', 'slug' => 'settings.view', 'group' => 'settings', 'description' => 'Melihat pengaturan sistem'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'group' => 'settings', 'description' => 'Mengubah pengaturan sistem'],
            ['name' => 'Edit Email Templates', 'slug' => 'email_templates.edit', 'group' => 'settings', 'description' => 'Mengedit template email reminder'],

            // Reports
            ['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports', 'description' => 'Melihat laporan dan statistik'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'group' => 'reports', 'description' => 'Export laporan ke Excel/PDF'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}
