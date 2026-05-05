<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminNoAccountUserRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminUserActionAccessService;
use App\Services\AdminUserMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class UpdateAdminNoAccountUserController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function __invoke(
        UpdateAdminNoAccountUserRequest $request,
        Employee $employee,
        AdminUserActionAccessService $accessService,
        AdminUserMutationService $mutationService,
    ): RedirectResponse {
        /** @var User|null $actor */
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($accessService->canAccessAdministration($actor, $request), 403);

        if ($employee->user()->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Selected employee already has an account.',
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

        if ($newRoleCode !== null && ! $accessService->canManageAdminUserRoles($actor)) {
            throw ValidationException::withMessages([
                'role_id' => 'Only HR Head and Super Administrator may update role assignments.',
            ]);
        }

        if (
            $newRoleCode === Role::CODE_SUPER_ADMIN
            && ! $actor->hasRole(Role::CODE_SUPER_ADMIN)
        ) {
            throw ValidationException::withMessages([
                'role_id' => 'Only Super Administrators may assign the Super Administrator role.',
            ]);
        }

        $mutationService->createUserForEmployee($actor, $employee, [
            'email' => (string) $validated['email'],
            'role_id' => $canManageRoles && isset($validated['role_id']) ? (int) $validated['role_id'] : null,
            'branch_ids' => $canManageRoles
                ? array_values(array_unique(array_map(
                    static fn ($value): int => (int) $value,
                    $validated['branch_ids'] ?? [],
                )))
                : [],
            'password' => (string) $validated['password'],
        ]);

        return back(303);
    }
}
