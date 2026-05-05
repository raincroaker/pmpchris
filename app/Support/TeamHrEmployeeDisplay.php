<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\User;

final class TeamHrEmployeeDisplay
{
    public static function fullName(Employee $employee): string
    {
        $parts = array_values(array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
            $employee->suffix,
        ], fn (?string $part): bool => filled($part)));

        if ($parts === []) {
            return 'Employee #'.$employee->id;
        }

        return implode(' ', $parts);
    }

    public static function avatarUrl(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $path = $user->avatar_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
