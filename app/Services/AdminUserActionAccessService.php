<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserActionAccessService
{
    /**
     * @return list<string>
     */
    public static function topAdminRoleCodes(): array
    {
        return [
            Role::CODE_HR_HEAD,
            Role::CODE_SUPER_ADMIN,
        ];
    }

    public function canUseAdminUserEditor(?User $actor): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        return $actor->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]);
    }

    public function canAccessAdministration(?User $actor, Request $request): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if ($this->canUseAdminUserEditor($actor)) {
            return true;
        }

        if (! $actor->hasRole(Role::CODE_HR_MANAGER)) {
            return false;
        }

        /** @var BranchContextService $branchContextService */
        $branchContextService = app(BranchContextService::class);
        $context = $branchContextService->workspaceBranchContext($request);
        if ($context === null) {
            return false;
        }

        $managedRootIds = $branchContextService->managedBranchRootIdsFor($actor);

        return in_array((int) $context['id'], $managedRootIds, true);
    }

    public function canManageAdminUserRoles(?User $actor): bool
    {
        return $this->canUseAdminUserEditor($actor);
    }

    public function canUseEmploymentStateFilter(?User $actor): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        return ! $actor->hasRole(Role::CODE_HR_MANAGER);
    }

    public function hasTopAdminRole(User $user): bool
    {
        return $user->roles()
            ->whereIn('code', self::topAdminRoleCodes())
            ->exists();
    }

    public function isRoleChangeRequested(User $target, ?string $newRoleCode): bool
    {
        if ($newRoleCode === null || $newRoleCode === '') {
            return false;
        }

        $currentCodes = $target->roles()->pluck('code')->all();
        if ($currentCodes === []) {
            return true;
        }

        return $currentCodes !== [$newRoleCode];
    }

    public function adminUserMutationBlockedReason(User $actor, User $target): ?string
    {
        if (! $this->canManageAdminUserRoles($actor) && ! $actor->hasRole(Role::CODE_HR_MANAGER)) {
            return 'You are not allowed to modify users.';
        }

        if ($target->hasRole(Role::CODE_SUPER_ADMIN) && ! $actor->hasRole(Role::CODE_SUPER_ADMIN)) {
            return 'You are not allowed to modify Super Administrator accounts.';
        }

        return null;
    }

    public function roleChangeBlockedReason(User $actor, User $target, ?string $newRoleCode): ?string
    {
        if (! $this->isRoleChangeRequested($target, $newRoleCode)) {
            return null;
        }

        if (! $this->canManageAdminUserRoles($actor)) {
            return 'Only HR Head and Super Administrator may update role assignments.';
        }

        if ($newRoleCode === Role::CODE_SUPER_ADMIN && ! $actor->hasRole(Role::CODE_SUPER_ADMIN)) {
            return 'Only Super Administrators may assign the Super Administrator role.';
        }

        if ((int) $actor->id === (int) $target->id) {
            return 'You cannot change your own role assignment from this screen.';
        }

        if ($this->hasTopAdminRole($target) && ! in_array((string) $newRoleCode, self::topAdminRoleCodes(), true)) {
            if ($this->topAdminUserCount() <= 1) {
                return 'At least one HR Head or Super Administrator account must remain.';
            }
        }

        return null;
    }

    public function passwordChangeBlockedReason(User $actor, User $target, bool $wantsPasswordChange): ?string
    {
        if (! $wantsPasswordChange) {
            return null;
        }

        if (! $this->canManageAdminUserRoles($actor) && ! $actor->hasRole(Role::CODE_HR_MANAGER)) {
            return 'You are not allowed to modify users.';
        }

        return null;
    }

    private function topAdminUserCount(): int
    {
        return User::query()
            ->whereHas('roles', function ($query): void {
                $query->whereIn('code', self::topAdminRoleCodes());
            })
            ->distinct('users.id')
            ->count('users.id');
    }
}
