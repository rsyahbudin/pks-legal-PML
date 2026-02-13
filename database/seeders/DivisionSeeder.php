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
                'REF_DIV_NAME' => 'Distribution',
                'REF_DIV_ID' => 'DIST',
                'REF_DIV_DESC' => 'Divisi Distribution',
                'departments' => [
                    ['REF_DEPT_NAME' => 'Bancassurance Conventional', 'REF_DEPT_ID' => 'BANC-CONV'],
                    ['REF_DEPT_NAME' => 'Bancassurance Sharia', 'REF_DEPT_ID' => 'BANC-SHAR'],
                    ['REF_DEPT_NAME' => 'Credit Life Employee Benefit', 'REF_DEPT_ID' => 'CLEB'],
                    ['REF_DEPT_NAME' => 'Hybrid Digital Agency', 'REF_DEPT_ID' => 'HDA'],
                    ['REF_DEPT_NAME' => 'Business Development', 'REF_DEPT_ID' => 'BIZDEV'],
                    ['REF_DEPT_NAME' => 'Distribution Support', 'REF_DEPT_ID' => 'DIST-SUP'],
                ],
            ],
            [
                'REF_DIV_NAME' => 'Finance',
                'REF_DIV_ID' => 'FIN',
                'REF_DIV_DESC' => 'Divisi Finance',
                'departments' => [
                    ['REF_DEPT_NAME' => 'Finance and Accounting', 'REF_DEPT_ID' => 'FIN-ACC'],
                    ['REF_DEPT_NAME' => 'Investment', 'REF_DEPT_ID' => 'INV'],
                    ['REF_DEPT_NAME' => 'Financial Planning Analysis and Procurement', 'REF_DEPT_ID' => 'FPAP'],
                ],
            ],
            [
                'REF_DIV_NAME' => 'Compliance',
                'REF_DIV_ID' => 'COMP',
                'REF_DIV_DESC' => 'Divisi Compliance',
                'departments' => [
                    ['REF_DEPT_NAME' => 'Compliance', 'REF_DEPT_ID' => 'COMP'],
                    ['REF_DEPT_NAME' => 'Risk Management', 'REF_DEPT_ID' => 'RISK'],
                ],
            ],
            [
                'REF_DIV_NAME' => 'IT',
                'REF_DIV_ID' => 'IT',
                'REF_DIV_DESC' => 'Divisi IT',
                'departments' => [
                    ['REF_DEPT_NAME' => 'IT Developer', 'REF_DEPT_ID' => 'IT-DEV'],
                    ['REF_DEPT_NAME' => 'IT Security', 'REF_DEPT_ID' => 'IT-SEC'],
                    ['REF_DEPT_NAME' => 'IT Infrastructure', 'REF_DEPT_ID' => 'IT-INFRA'],
                    ['REF_DEPT_NAME' => 'IT Business Analyst', 'REF_DEPT_ID' => 'IT-BA'],
                ],
            ],
            [
                'REF_DIV_NAME' => 'Actuary and Product',
                'REF_DIV_ID' => 'ACT-PRD',
                'REF_DIV_DESC' => 'Divisi Actuary and Product',
                'departments' => [
                    ['REF_DEPT_NAME' => 'Product', 'REF_DEPT_ID' => 'PRD'],
                    ['REF_DEPT_NAME' => 'Actuary', 'REF_DEPT_ID' => 'ACT'],
                ],
            ],
            [
                'REF_DIV_NAME' => 'Operation',
                'REF_DIV_ID' => 'OPS',
                'REF_DIV_DESC' => 'Divisi Operation',
                'departments' => [
                    ['REF_DEPT_NAME' => 'Underwriting', 'REF_DEPT_ID' => 'UW'],
                    ['REF_DEPT_NAME' => 'Claim', 'REF_DEPT_ID' => 'CLM'],
                    ['REF_DEPT_NAME' => 'Policy Owner Service', 'REF_DEPT_ID' => 'POS'],
                    ['REF_DEPT_NAME' => 'Customer Service', 'REF_DEPT_ID' => 'CS'],
                ],
            ],
            [
                'REF_DIV_NAME' => 'Digital',
                'REF_DIV_ID' => 'DIG',
                'REF_DIV_DESC' => 'Divisi Digital',
                'departments' => [
                    ['REF_DEPT_NAME' => 'Digital Marketing', 'REF_DEPT_ID' => 'DIG-MKT'],
                    ['REF_DEPT_NAME' => 'Data Scientist', 'REF_DEPT_ID' => 'DATA-SCI'],
                    ['REF_DEPT_NAME' => 'Marketing Technology', 'REF_DEPT_ID' => 'MARTECH'],
                ],
            ],
            [
                'REF_DIV_NAME' => 'Internal Audit',
                'REF_DIV_ID' => 'AUDIT',
                'REF_DIV_DESC' => 'Divisi Internal Audit',
                'departments' => [
                    ['REF_DEPT_NAME' => 'Audit', 'REF_DEPT_ID' => 'AUDIT'],
                ],
            ],
            [
                'REF_DIV_NAME' => 'Legal',
                'REF_DIV_ID' => 'LEGAL',
                'REF_DIV_DESC' => 'Divisi Legal',
                'departments' => [
                    ['REF_DEPT_NAME' => 'Legal', 'REF_DEPT_ID' => 'LEGAL'],
                ],
            ],
        ];

        foreach ($divisions as $divisionData) {
            $departments = $divisionData['departments'];
            unset($divisionData['departments']);

            // Create or update division
            $division = Division::updateOrCreate(
                ['REF_DIV_ID' => $divisionData['REF_DIV_ID']],
                $divisionData
            );

            // Create departments for this division
            foreach ($departments as $departmentData) {
                Department::updateOrCreate(
                    [
                        'DIV_ID' => $division->LGL_ROW_ID,
                        'REF_DEPT_ID' => $departmentData['REF_DEPT_ID'],
                    ],
                    [
                        'REF_DEPT_NAME' => $departmentData['REF_DEPT_NAME'],
                    ]
                );
            }
        }
    }
}
