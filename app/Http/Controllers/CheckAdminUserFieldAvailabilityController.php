<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckAdminUserFieldAvailabilityRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CheckAdminUserFieldAvailabilityController extends Controller
{
    public function __invoke(CheckAdminUserFieldAvailabilityRequest $request): JsonResponse
    {
        $email = $request->email();

        return response()->json([
            'email' => $this->emailAvailability($email, $request->ignoreUserId()),
        ]);
    }

    /**
     * @return array{status: 'idle'|'available'|'taken'|'invalid', message: string}
     */
    private function emailAvailability(string $email, ?int $ignoreUserId): array
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

        $query = User::query()->where('email', $email);
        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
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
