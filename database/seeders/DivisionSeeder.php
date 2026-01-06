<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            ['name' => 'IT', 'code' => 'IT', 'description' => 'Divisi Teknologi Informasi'],
            ['name' => 'Legal', 'code' => 'LGL', 'description' => 'Divisi Hukum dan Kepatuhan'],
            ['name' => 'Finance', 'code' => 'FIN', 'description' => 'Divisi Keuangan'],
            ['name' => 'Human Resources', 'code' => 'HR', 'description' => 'Divisi Sumber Daya Manusia'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Divisi Pemasaran'],
            ['name' => 'Operations', 'code' => 'OPS', 'description' => 'Divisi Operasional'],
            ['name' => 'Procurement', 'code' => 'PRC', 'description' => 'Divisi Pengadaan'],
        ];

        foreach ($divisions as $division) {
            Division::updateOrCreate(
                ['code' => $division['code']],
                $division
            );
        }
    }
}
