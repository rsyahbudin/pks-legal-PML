<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard permissions
            ['name' => 'Lihat Dashboard Ticket Statistics', 'slug' => 'dashboard.tickets.view', 'group' => 'dashboard', 'description' => 'Melihat statistik tiket di dashboard'],
            ['name' => 'Lihat Dashboard Contract Statistics', 'slug' => 'dashboard.contracts.view', 'group' => 'dashboard', 'description' => 'Melihat statistik kontrak di dashboard'],
            ['name' => 'Lihat My Tickets', 'slug' => 'dashboard.my-tickets.view', 'group' => 'dashboard', 'description' => 'Melihat tiket milik pengguna di dashboard'],

            // Tickets permissions (replacing old contracts.create which is now ticket creation)
            ['name' => 'Lihat Tickets', 'slug' => 'tickets.view', 'group' => 'tickets', 'description' => 'Melihat daftar tickets'],
            ['name' => 'Buat Ticket', 'slug' => 'tickets.create', 'group' => 'tickets', 'description' => 'Membuat ticket baru'],
            ['name' => 'Edit Ticket', 'slug' => 'tickets.edit', 'group' => 'tickets', 'description' => 'Mengedit ticket (legal only)'],
            ['name' => 'Process Ticket', 'slug' => 'tickets.process', 'group' => 'tickets', 'description' => 'Memproses ticket (legal only)'],
            ['name' => 'Reject Ticket', 'slug' => 'tickets.reject', 'group' => 'tickets', 'description' => 'Menolak ticket (legal only)'],
            ['name' => 'Complete Ticket', 'slug' => 'tickets.complete', 'group' => 'tickets', 'description' => 'Menyelesaikan ticket dan membuat contract (legal only)'],

            // Contracts permissions (actual contract management)
            ['name' => 'Lihat Kontrak', 'slug' => 'contracts.view', 'group' => 'contracts', 'description' => 'Melihat daftar kontrak'],
            ['name' => 'Edit Kontrak', 'slug' => 'contracts.edit', 'group' => 'contracts', 'description' => 'Mengedit kontrak'],
            ['name' => 'Terminate Kontrak', 'slug' => 'contracts.terminate', 'group' => 'contracts', 'description' => 'Mengakhiri kontrak'],
            ['name' => 'Hapus Kontrak', 'slug' => 'contracts.delete', 'group' => 'contracts', 'description' => 'Menghapus kontrak'],
            ['name' => 'Kirim Pengingat Kontrak', 'slug' => 'contracts.send_reminder', 'group' => 'contracts', 'description' => 'Mengirim email pengingat kontrak secara manual'],

            // Users permissions
            ['name' => 'Lihat User', 'slug' => 'users.view', 'group' => 'users', 'description' => 'Melihat daftar pengguna'],
            ['name' => 'Buat User', 'slug' => 'users.create', 'group' => 'users', 'description' => 'Membuat pengguna baru'],
            ['name' => 'Edit User', 'slug' => 'users.edit', 'group' => 'users', 'description' => 'Mengedit pengguna'],
            ['name' => 'Hapus User', 'slug' => 'users.delete', 'group' => 'users', 'description' => 'Menghapus pengguna'],

            // Roles permissions
            ['name' => 'Lihat Role', 'slug' => 'roles.view', 'group' => 'roles', 'description' => 'Melihat daftar role'],
            ['name' => 'Edit Role', 'slug' => 'roles.edit', 'group' => 'roles', 'description' => 'Mengedit role'],
            ['name' => 'Manage Role Permissions', 'slug' => 'roles.manage', 'group' => 'roles', 'description' => 'Mengelola permissions untuk setiap role'],

            // Divisions
            ['name' => 'Lihat Divisi', 'slug' => 'divisions.view', 'group' => 'divisions', 'description' => 'Melihat daftar divisi'],
            ['name' => 'Buat Divisi', 'slug' => 'divisions.create', 'group' => 'divisions', 'description' => 'Membuat divisi baru'],
            ['name' => 'Edit Divisi', 'slug' => 'divisions.edit', 'group' => 'divisions', 'description' => 'Mengedit divisi'],
            ['name' => 'Hapus Divisi', 'slug' => 'divisions.delete', 'group' => 'divisions', 'description' => 'Menghapus divisi'],
            ['name' => 'Manage Divisi', 'slug' => 'divisions.manage', 'group' => 'divisions', 'description' => 'Mengelola divisi (create, edit, delete)'],

            // Departments
            ['name' => 'Lihat Departemen', 'slug' => 'departments.view', 'group' => 'departments', 'description' => 'Melihat daftar departemen'],
            ['name' => 'Buat Departemen', 'slug' => 'departments.create', 'group' => 'departments', 'description' => 'Membuat departemen baru'],
            ['name' => 'Edit Departemen', 'slug' => 'departments.edit', 'group' => 'departments', 'description' => 'Mengedit departemen'],
            ['name' => 'Hapus Departemen', 'slug' => 'departments.delete', 'group' => 'departments', 'description' => 'Menghapus departemen'],
            ['name' => 'Manage Departemen & CC Emails', 'slug' => 'departments.manage', 'group' => 'departments', 'description' => 'Mengelola departemen dan CC email lists'],

            // Settings
            ['name' => 'Lihat Pengaturan', 'slug' => 'settings.view', 'group' => 'settings', 'description' => 'Melihat pengaturan sistem'],
            ['name' => 'Edit Pengaturan', 'slug' => 'settings.edit', 'group' => 'settings', 'description' => 'Mengubah pengaturan sistem'],
            ['name' => 'Edit Template Email', 'slug' => 'email_templates.edit', 'group' => 'settings', 'description' => 'Mengedit template email pengingat'],

            // Reports
            ['name' => 'Lihat Laporan', 'slug' => 'reports.view', 'group' => 'reports', 'description' => 'Melihat laporan dan statistik'],
            ['name' => 'Export Laporan', 'slug' => 'reports.export', 'group' => 'reports', 'description' => 'Export laporan ke Excel/CSV (Tickets, Contracts)'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}
