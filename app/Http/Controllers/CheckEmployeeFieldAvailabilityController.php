<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckEmployeeFieldAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
            Role::CODE_HR_MANAGER,
        ]) ?? false, 403);

        $payload = $request->validate([
            'id_number' => ['nullable', 'string', 'max:50'],
            'attendance_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:255'],
        ]);

        $idNumber = trim((string) ($payload['id_number'] ?? ''));
        $attendanceId = trim((string) ($payload['attendance_id'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));

        return response()->json([
            'id_number' => $this->fieldAvailability(
                $idNumber,
                fn (string $value): bool => Employee::query()
                    ->where('id_number', $value)
                    ->whereNull('deleted_at')
                    ->exists(),
                'Employee ID is already in use.',
            ),
            'attendance_id' => $this->fieldAvailability(
                $attendanceId,
                fn (string $value): bool => Employee::query()
                    ->where('attendance_id', $value)
                    ->whereNull('deleted_at')
                    ->exists(),
                'Attendance ID is already in use.',
            ),
            'email' => $this->emailAvailability($email),
        ]);
    }

    /**
     * @param  callable(string):bool  $exists
     * @return array{status: 'idle'|'available'|'taken'|'invalid', message: string}
     */
    private function fieldAvailability(string $value, callable $exists, string $takenMessage): array
    {
        if ($value === '') {
            return [
                'status' => 'idle',
                'message' => '',
            ];
        }

        if ($exists($value)) {
            return [
                'status' => 'taken',
                'message' => $takenMessage,
            ];
        }

        return [
            'status' => 'available',
            'message' => 'Looks good.',
        ];
    }

    /**
     * @return array{status: 'idle'|'available'|'taken'|'invalid', message: string}
     */
    private function emailAvailability(string $email): array
    {
        if ($email === '') {
            return [
                'status' => 'idle',
                'message' => '',
            ];
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'invalid',
                'message' => 'Enter a valid email address.',
            ];
        }

        if (User::query()->where('email', $email)->exists()) {
            return [
                'status' => 'taken',
                'message' => 'Email is already in use.',
            ];
        }

        return [
            'status' => 'available',
            'message' => 'Email is available.',
        ];
    }
}
