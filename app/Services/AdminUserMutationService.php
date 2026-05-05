<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\BranchManager;
use App\Models\Role;
use App\Models\User;
use App\Support\TeamHrEmployeeDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * @param  array{
     *     email: string,
     *     role_id?: int|null,
     *     branch_ids?: list<int>,
     *     password?: string|null,
     * }  $payload
     *
     * @throws ValidationException
     */
    public function updateUser(User $actor, User $target, array $payload): void
    {
        DB::transaction(function () use ($actor, $target, $payload): void {
            $target->email = (string) $payload['email'];
            if ($target->isDirty('email')) {
                $target->email_verified_at = null;
            }

            $password = trim((string) ($payload['password'] ?? ''));
            if ($password !== '') {
                $target->password = $password;
            }

            $target->save();

            $selectedRoleCode = null;
            if (array_key_exists('role_id', $payload) && $payload['role_id'] !== null) {
                $role = Role::query()->findOrFail((int) $payload['role_id']);
                $target->roles()->sync([$role->id]);
                $selectedRoleCode = $role->code;
            }

            $effectiveRoleCode = $selectedRoleCode ?? $this->preferredRoleCode($target);
            if ($effectiveRoleCode === Role::CODE_HR_MANAGER) {
                $this->syncHrManagerBranches(
                    $actor,
                    $target,
                    array_values(array_unique(array_map(
                        static fn ($value): int => (int) $value,
                        $payload['branch_ids'] ?? [],
                    ))),
                );

                return;
            }

            $this->deactivateAllBranchManagerAssignments($target);
        });
    }

    /**
     * @param  array{
     *     email: string,
     *     role_id?: int|null,
     *     branch_ids?: list<int>,
     *     password: string,
     * }  $payload
     *
     * @throws ValidationException
     */
    public function createUserForEmployee(User $actor, Employee $employee, array $payload): User
    {
        return DB::transaction(function () use ($actor, $employee, $payload): User {
            $password = trim((string) ($payload['password'] ?? ''));
            if ($password === '') {
                throw ValidationException::withMessages([
                    'password' => 'Password is required.',
                ]);
            }

            $target = User::query()->create([
                'employee_id' => $employee->id,
                'name' => TeamHrEmployeeDisplay::fullName($employee),
                'email' => (string) $payload['email'],
                'password' => $password,
            ]);

            $selectedRoleCode = null;
            if (array_key_exists('role_id', $payload) && $payload['role_id'] !== null) {
                $role = Role::query()->findOrFail((int) $payload['role_id']);
                $target->roles()->sync([$role->id]);
                $selectedRoleCode = $role->code;
            } else {
                $target->assignRole(Role::CODE_EMPLOYEE);
            }

            $effectiveRoleCode = $selectedRoleCode ?? $this->preferredRoleCode($target);
            if ($effectiveRoleCode === Role::CODE_HR_MANAGER) {
                $this->syncHrManagerBranches(
                    $actor,
                    $target,
                    array_values(array_unique(array_map(
                        static fn ($value): int => (int) $value,
                        $payload['branch_ids'] ?? [],
                    ))),
                );

                return $target;
            }

            $this->deactivateAllBranchManagerAssignments($target);

            return $target;
        });
    }

    private function preferredRoleCode(User $user): ?string
    {
        $preference = [
            Role::CODE_HR_HEAD,
            Role::CODE_HR_MANAGER,
            Role::CODE_SUPER_ADMIN,
            Role::CODE_EMPLOYEE,
        ];

        $codes = $user->roles()
            ->pluck('code')
            ->all();

        foreach ($preference as $code) {
            if (in_array($code, $codes, true)) {
                return $code;
            }
        }

        return $codes[0] ?? null;
    }

    /**
     * @param  list<int>  $branchIds
     *
     * @throws ValidationException
     */
    private function syncHrManagerBranches(User $actor, User $target, array $branchIds): void
    {
        $allowedRootIds = $this->branchContextService
            ->branchesForPicker()
            ->pluck('id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
        $allowedRootIdSet = array_flip($allowedRootIds);

        foreach ($branchIds as $branchId) {
            if (! isset($allowedRootIdSet[$branchId])) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'One or more selected branches are invalid.',
                ]);
            }
        }

        if ($branchIds === []) {
            throw ValidationException::withMessages([
                'branch_ids' => 'At least one branch is required for HR Manager.',
            ]);
        }

        $now = now();

        BranchManager::query()
            ->where('user_id', $target->id)
            ->where('is_active', true)
            ->whereNotIn('root_unit_id', $branchIds, 'and')
            ->update([
                'is_active' => false,
                'ends_at' => $now,
                'updated_at' => $now,
            ]);

        foreach ($branchIds as $branchId) {
            $assignment = BranchManager::query()
                ->firstOrNew([
                    'user_id' => $target->id,
                    'root_unit_id' => $branchId,
                ]);

            if (! $assignment->exists || ! $assignment->is_active || $assignment->starts_at === null) {
                $assignment->starts_at = $now;
            }

            $assignment->assigned_by_user_id = $actor->id;
            $assignment->is_active = true;
            $assignment->ends_at = null;
            $assignment->save();
        }
    }

    private function deactivateAllBranchManagerAssignments(User $target): void
    {
        $now = now();

        BranchManager::query()
            ->where('user_id', $target->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'ends_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
