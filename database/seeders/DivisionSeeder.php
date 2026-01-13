<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            [
                'name' => 'Distribution',
                'code' => 'DIST',
                'description' => 'Divisi Distribution',
                'departments' => [
                    ['name' => 'Bancassurance Conventional', 'code' => 'BANC-CONV'],
                    ['name' => 'Bancassurance Sharia', 'code' => 'BANC-SHAR'],
                    ['name' => 'Credit Life Employee Benefit', 'code' => 'CLEB'],
                    ['name' => 'Hybrid Digital Agency', 'code' => 'HDA'],
                    ['name' => 'Business Development', 'code' => 'BIZDEV'],
                    ['name' => 'Distribution Support', 'code' => 'DIST-SUP'],
                ],
            ],
            [
                'name' => 'Finance',
                'code' => 'FIN',
                'description' => 'Divisi Finance',
                'departments' => [
                    ['name' => 'Finance and Accounting', 'code' => 'FIN-ACC'],
                    ['name' => 'Investment', 'code' => 'INV'],
                    ['name' => 'Financial Planning Analysis and Procurement', 'code' => 'FPAP'],
                ],
            ],
            [
                'name' => 'Compliance',
                'code' => 'COMP',
                'description' => 'Divisi Compliance',
                'departments' => [
                    ['name' => 'Compliance', 'code' => 'COMP'],
                    ['name' => 'Risk Management', 'code' => 'RISK'],
                ],
            ],
            [
                'name' => 'IT',
                'code' => 'IT',
                'description' => 'Divisi IT',
                'departments' => [
                    ['name' => 'IT Developer', 'code' => 'IT-DEV'],
                    ['name' => 'IT Security', 'code' => 'IT-SEC'],
                    ['name' => 'IT Infrastructure', 'code' => 'IT-INFRA'],
                    ['name' => 'IT Business Analyst', 'code' => 'IT-BA'],
                ],
            ],
            [
                'name' => 'Actuary and Product',
                'code' => 'ACT-PRD',
                'description' => 'Divisi Actuary and Product',
                'departments' => [
                    ['name' => 'Product', 'code' => 'PRD'],
                    ['name' => 'Actuary', 'code' => 'ACT'],
                ],
            ],
            [
                'name' => 'Operation',
                'code' => 'OPS',
                'description' => 'Divisi Operation',
                'departments' => [
                    ['name' => 'Underwriting', 'code' => 'UW'],
                    ['name' => 'Claim', 'code' => 'CLM'],
                    ['name' => 'Policy Owner Service', 'code' => 'POS'],
                    ['name' => 'Customer Service', 'code' => 'CS'],
                ],
            ],
            [
                'name' => 'Digital',
                'code' => 'DIG',
                'description' => 'Divisi Digital',
                'departments' => [
                    ['name' => 'Digital Marketing', 'code' => 'DIG-MKT'],
                    ['name' => 'Data Scientist', 'code' => 'DATA-SCI'],
                    ['name' => 'Marketing Technology', 'code' => 'MARTECH'],
                ],
            ],
            [
                'name' => 'Internal Audit',
                'code' => 'AUDIT',
                'description' => 'Divisi Internal Audit',
                'departments' => [
                    ['name' => 'Audit', 'code' => 'AUDIT'],
                ],
            ],
            [
                'name' => 'Legal',
                'code' => 'LEGAL',
                'description' => 'Divisi Legal',
                'departments' => [
                    ['name' => 'Legal', 'code' => 'LEGAL'],
                ],
            ],
        ];

        foreach ($divisions as $divisionData) {
            $departments = $divisionData['departments'];
            unset($divisionData['departments']);

            // Create or update division
            $division = Division::updateOrCreate(
                ['code' => $divisionData['code']],
                $divisionData
            );

            // Create departments for this division
            foreach ($departments as $departmentData) {
                Department::updateOrCreate(
                    [
                        'division_id' => $division->id,
                        'code' => $departmentData['code'],
                    ],
                    [
                        'name' => $departmentData['name'],
                    ]
                );
            }
        }
    }
}
