<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeAddress;
use App\Models\EmployeeContact;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentEmployeeProfileSeeder extends Seeder
{
    /**
     * Attach sample contacts and addresses to local dev employees (EMP-SEED-*).
     * Safe to re-run: uses firstOrCreate on stable keys.
     */
    public function run(): void
    {
        $employees = Employee::query()
            ->where('id_number', 'like', 'EMP-SEED-%')
            ->orderBy('id_number')
            ->get();

        foreach ($employees as $employee) {
            $seq = $this->sequenceFromIdNumber($employee->id_number, $employee->id);
            $this->seedContacts($employee, $seq);
            $this->seedAddresses($employee, $seq);
        }
    }

    private function sequenceFromIdNumber(string $idNumber, int $fallbackId): int
    {
        if (preg_match('/EMP-SEED-(\d{3})$/', $idNumber, $matches) === 1) {
            return (int) $matches[1];
        }

        return $fallbackId;
    }

    private function seedContacts(Employee $employee, int $seq): void
    {
        $mobile = sprintf('+63917%06d', min($seq, 999_999));

        $loginEmail = User::query()->where('employee_id', $employee->id)->value('email');

        EmployeeContact::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'category' => 'personal',
                'type' => 'mobile',
            ],
            [
                'contact_number' => $mobile,
                'email' => $loginEmail,
                'is_primary' => true,
            ]
        );

        EmployeeContact::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'category' => 'personal',
                'type' => 'work',
            ],
            [
                'contact_number' => sprintf('+6328%07d', 1000000 + $seq),
                'email' => 'work.contact+'.$employee->id_number.'@example.com',
                'is_primary' => false,
            ]
        );

        if ($seq % 2 === 1) {
            EmployeeContact::query()->firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'category' => 'emergency',
                    'type' => 'mobile',
                ],
                [
                    'contact_person' => 'Emergency contact '.$seq,
                    'relationship' => $seq % 3 === 0 ? 'Parent' : 'Spouse',
                    'contact_number' => sprintf('+63918%06d', min($seq + 50, 999_999)),
                    'email' => null,
                    'is_primary' => false,
                ]
            );
        } else {
            EmployeeContact::query()->firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'category' => 'emergency',
                    'type' => null,
                ],
                [
                    'contact_person' => 'Emergency contact '.$seq,
                    'relationship' => 'Sibling',
                    'contact_number' => sprintf('+63919%06d', min($seq + 100, 999_999)),
                    'email' => null,
                    'is_primary' => false,
                ]
            );
        }
    }

    private function seedAddresses(Employee $employee, int $seq): void
    {
        $current = $this->addressVariant($seq);
        $permanent = $this->addressVariant($seq + 3);

        EmployeeAddress::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'type' => 'current',
            ],
            array_merge($current, [
                'is_primary' => true,
            ])
        );

        EmployeeAddress::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'type' => 'permanent',
            ],
            array_merge($permanent, [
                'is_primary' => false,
            ])
        );
    }

    /**
     * @return array{address_line_1: string, address_line_2: string|null, barangay: string, city: string, province: string, zip_code: string, country: string}
     */
    private function addressVariant(int $seed): array
    {
        $presets = [
            ['Poblacion', 'Tagum City', 'Davao del Norte', '8100', 'Blk 3 Lot 12, Apokon Road', 'Green Meadows Subd.'],
            ['San Antonio', 'Davao City', 'Davao del Sur', '8000', '45 Mabini Street', null],
            ['Central', 'Panabo City', 'Davao del Norte', '8105', 'Purok 7, National Highway', 'Unit 2'],
            ['Matina', 'Davao City', 'Davao del Sur', '8000', 'Door 12-B, Eco West Drive', null],
            ['Poblacion', 'Digos City', 'Davao del Sur', '8002', 'Rizal Avenue corner Quezon', null],
            ['Upper Carmen', 'Cagayan de Oro', 'Misamis Oriental', '9000', 'Phase 3, Hillside Village', 'Blk 8'],
        ];

        $i = $seed % count($presets);
        $p = $presets[$i];

        return [
            'address_line_1' => $p[4],
            'address_line_2' => $p[5],
            'barangay' => $p[0],
            'city' => $p[1],
            'province' => $p[2],
            'zip_code' => $p[3],
            'country' => 'Philippines',
        ];
    }
}
