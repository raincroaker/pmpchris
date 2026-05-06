<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAddress;
use App\Models\EmployeeContact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class AboutMeEmployeeDirectoryProfileWriter
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateBasics(Employee $employee, array $validated): void
    {
        DB::transaction(function () use ($employee, $validated): void {
            $employee->first_name = (string) $validated['first_name'];
            $employee->middle_name = isset($validated['middle_name']) && is_string($validated['middle_name']) && $validated['middle_name'] !== ''
                ? (string) $validated['middle_name']
                : null;
            $employee->last_name = (string) $validated['last_name'];
            $employee->id_number = (string) $validated['id_number'];
            $employee->attendance_id = isset($validated['attendance_id']) && is_string($validated['attendance_id']) && trim($validated['attendance_id']) !== ''
                ? trim((string) $validated['attendance_id'])
                : null;
            $employee->save();

            $user = User::query()->where('employee_id', $employee->getKey())->first();
            if ($user instanceof User) {
                $namePieces = [
                    trim((string) $employee->first_name),
                    trim((string) ($employee->middle_name ?? '')),
                    trim((string) $employee->last_name),
                ];
                $name = trim(implode(' ', array_filter($namePieces, fn (string $p): bool => $p !== '')));

                $removeAvatar = (bool) ($validated['remove_avatar'] ?? false);

                /** @var UploadedFile|null $newAvatar */
                $newAvatar = $validated['avatar'] ?? null;

                if ($removeAvatar || $newAvatar !== null) {
                    if ($user->avatar_path !== null && $user->avatar_path !== '') {
                        Storage::disk('public')->delete($user->avatar_path);
                    }
                    $user->avatar_path = null;
                }

                if ($newAvatar !== null) {
                    $user->avatar_path = $newAvatar->store(
                        'avatars/users/'.$user->id,
                        'public',
                    );
                }

                $user->name = $name;
                $user->save();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateDemographics(Employee $employee, array $validated): void
    {
        $religion = isset($validated['religion']) && is_string($validated['religion']) && trim($validated['religion']) !== ''
            ? trim((string) $validated['religion'])
            : null;
        $religionOther = null;
        if ($religion === 'Other') {
            $religionOther = isset($validated['religion_other']) && is_string($validated['religion_other']) && trim($validated['religion_other']) !== ''
                ? trim((string) $validated['religion_other'])
                : null;
        }

        $employee->birthdate = (string) $validated['birthdate'];
        $employee->sex = (string) $validated['sex'];
        $employee->civil_status = isset($validated['civil_status']) && is_string($validated['civil_status']) && trim($validated['civil_status']) !== ''
            ? trim((string) $validated['civil_status'])
            : null;
        $employee->nationality = isset($validated['nationality']) && is_string($validated['nationality']) && trim($validated['nationality']) !== ''
            ? trim((string) $validated['nationality'])
            : null;
        $employee->religion = $religion;
        $employee->religion_other = $religionOther;
        $employee->save();
    }

    /**
     * @param  list<array<string, mixed>>  $personal
     * @param  list<array<string, mixed>>  $emergency
     */
    public function syncContacts(Employee $employee, array $personal, array $emergency): void
    {
        DB::transaction(function () use ($employee, $personal, $emergency): void {
            EmployeeContact::query()
                ->where('employee_id', $employee->getKey())
                ->whereIn('category', ['personal', 'emergency'])
                ->delete();

            foreach ($personal as $row) {
                EmployeeContact::query()->create([
                    'employee_id' => $employee->getKey(),
                    'category' => 'personal',
                    'type' => is_string($row['type']) ? strtolower($row['type']) : null,
                    'contact_person' => null,
                    'relationship' => null,
                    'contact_number' => (string) $row['contact_number'],
                    'email' => isset($row['email']) && is_string($row['email']) && trim($row['email']) !== ''
                        ? trim((string) $row['email'])
                        : null,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                ]);
            }

            foreach ($emergency as $row) {
                EmployeeContact::query()->create([
                    'employee_id' => $employee->getKey(),
                    'category' => 'emergency',
                    'type' => isset($row['type']) && is_string($row['type'])
                        ? strtolower($row['type'])
                        : null,
                    'contact_person' => (string) $row['contact_person'],
                    'relationship' => isset($row['relationship']) && is_string($row['relationship']) && trim($row['relationship']) !== ''
                        ? trim((string) $row['relationship'])
                        : null,
                    'contact_number' => (string) $row['contact_number'],
                    'email' => isset($row['email']) && is_string($row['email']) && trim($row['email']) !== ''
                        ? trim((string) $row['email'])
                        : null,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>|null  $permanent
     */
    public function syncAddresses(Employee $employee, ?array $current, ?array $permanent): void
    {
        DB::transaction(function () use ($employee, $current, $permanent): void {
            EmployeeAddress::query()
                ->where('employee_id', $employee->getKey())
                ->whereIn('type', ['current', 'permanent'])
                ->delete();

            if ($current !== null && $permanent !== null) {
                $curPrimary = (bool) ($current['is_primary'] ?? false);
                $permPrimary = (bool) ($permanent['is_primary'] ?? false);
                if (! $curPrimary && ! $permPrimary) {
                    $current['is_primary'] = true;
                }
            } elseif ($current !== null && $permanent === null) {
                $current['is_primary'] = true;
            } elseif ($permanent !== null && $current === null) {
                $permanent['is_primary'] = true;
            }

            if ($permanent !== null) {
                EmployeeAddress::query()->create([
                    'employee_id' => $employee->getKey(),
                    'type' => 'permanent',
                    'address_line_1' => (string) $permanent['address_line_1'],
                    'address_line_2' => $permanent['address_line_2'] ?? null,
                    'barangay' => (string) $permanent['barangay'],
                    'barangay_code' => $permanent['barangay_code'] ?? null,
                    'city' => (string) $permanent['city'],
                    'city_code' => $permanent['city_code'] ?? null,
                    'province' => (string) $permanent['province'],
                    'province_code' => $permanent['province_code'] ?? null,
                    'zip_code' => (string) $permanent['zip_code'],
                    'country' => (string) ($permanent['country'] ?? 'Philippines'),
                    'is_primary' => (bool) ($permanent['is_primary'] ?? false),
                ]);
            }

            if ($current !== null) {
                EmployeeAddress::query()->create([
                    'employee_id' => $employee->getKey(),
                    'type' => 'current',
                    'address_line_1' => (string) $current['address_line_1'],
                    'address_line_2' => $current['address_line_2'] ?? null,
                    'barangay' => (string) $current['barangay'],
                    'barangay_code' => $current['barangay_code'] ?? null,
                    'city' => (string) $current['city'],
                    'city_code' => $current['city_code'] ?? null,
                    'province' => (string) $current['province'],
                    'province_code' => $current['province_code'] ?? null,
                    'zip_code' => (string) $current['zip_code'],
                    'country' => (string) ($current['country'] ?? 'Philippines'),
                    'is_primary' => (bool) ($current['is_primary'] ?? false),
                ]);
            }
        });
    }
}
