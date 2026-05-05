<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $definitions = [
            [
                'code' => Role::CODE_EMPLOYEE,
                'name' => 'Employee',
                'description' => 'Baseline staff access.',
            ],
            [
                'code' => Role::CODE_HR_HEAD,
                'name' => 'HR Head',
                'description' => 'HR leadership.',
            ],
            [
                'code' => Role::CODE_HR_MANAGER,
                'name' => 'HR Manager',
                'description' => 'HR operations management.',
            ],
            [
                'code' => Role::CODE_SUPER_ADMIN,
                'name' => 'Super Administrator',
                'description' => 'Full application access (reserved for primary dev / bypass paths).',
            ],
        ];

        foreach ($definitions as $row) {
            Role::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                ]
            );
        }
    }
}
