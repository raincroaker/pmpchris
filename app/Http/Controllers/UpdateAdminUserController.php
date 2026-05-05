<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminUserActionAccessService;
use App\Services\AdminUserMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class UpdateAdminUserController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function __invoke(
        UpdateAdminUserRequest $request,
        User $user,
        AdminUserActionAccessService $accessService,
        AdminUserMutationService $mutationService,
    ): RedirectResponse {
        /** @var User|null $actor */
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($accessService->canAccessAdministration($actor, $request), 403);

        $mutationBlockedReason = $accessService->adminUserMutationBlockedReason($actor, $user);
        if ($mutationBlockedReason !== null) {
            throw ValidationException::withMessages([
                'email' => $mutationBlockedReason,
            ]);
        }

        $validated = $request->validated();
        $canManageRoles = $accessService->canManageAdminUserRoles($actor);

        $newRoleCode = null;
        if (
            $canManageRoles
            && isset($validated['role_id'])
            && $validated['role_id'] !== null
        ) {
            $newRoleCode = Role::query()
                ->whereKey((int) $validated['role_id'])
                ->value('code');
        }

        $roleBlockedReason = $accessService->roleChangeBlockedReason($actor, $user, $newRoleCode);
        if ($roleBlockedReason !== null) {
            throw ValidationException::withMessages([
                'role_id' => $roleBlockedReason,
            ]);
        }

        $password = trim((string) ($validated['password'] ?? ''));
        $passwordBlockedReason = $accessService->passwordChangeBlockedReason(
            $actor,
            $user,
            $password !== '',
        );
        if ($passwordBlockedReason !== null) {
            throw ValidationException::withMessages([
                'password' => $passwordBlockedReason,
            ]);
        }

        $mutationService->updateUser($actor, $user, [
            'email' => (string) $validated['email'],
            'role_id' => $canManageRoles && isset($validated['role_id']) ? (int) $validated['role_id'] : null,
            'branch_ids' => $canManageRoles
                ? array_values(array_unique(array_map(
                    static fn ($value): int => (int) $value,
                    $validated['branch_ids'] ?? [],
                )))
                : [],
            'password' => $password !== '' ? $password : null,
        ]);

        return back(303);
    }
}
