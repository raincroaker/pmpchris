<?php

namespace App\Http\Middleware;

use App\Models\EmployeeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminUserActionAccessService;
use App\Services\BranchContextService;
use App\Services\EmployeeTeamHrPagesAccess;
use App\Services\LeaveOvertimePolicyManagementAccess;
use App\Services\ScheduleAssignmentAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private AdminUserActionAccessService $adminUserActionAccessService,
        private BranchContextService $branchContextService,
        private EmployeeTeamHrPagesAccess $employeeTeamHrPagesAccess,
        private LeaveOvertimePolicyManagementAccess $leaveOvertimePolicyManagementAccess,
        private ScheduleAssignmentAccessService $scheduleAssignmentAccess,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $canSwitchBranches = $this->branchContextService->canSwitchBranchContext($user);
        $branchContext = $this->branchContextService->workspaceBranchContext($request);
        $canManageCalendarData = $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;

        $canManageWorkspaceAttendancePolicies = $this->scheduleAssignmentAccess->allows($user, $request);
        $canViewWorkSchedules = $user !== null;
        $canMutateWorkSchedules = $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;

        $canViewLeaveOvertimePolicies = $this->leaveOvertimePolicyManagementAccess->allows($user);
        $canMutateLeaveOvertimePolicies = $this->leaveOvertimePolicyManagementAccess->allowsMutating($user);

        return [
            ...parent::share($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->serializeAuthUser($user),
            ],
            'can' => [
                'canAddEmployee' => $user?->hasAnyRole([
                    Role::CODE_SUPER_ADMIN,
                    Role::CODE_HR_HEAD,
                    Role::CODE_HR_MANAGER,
                ]) ?? false,
                'canRecordEmploymentSeparation' => $user?->hasAnyRole([
                    Role::CODE_SUPER_ADMIN,
                    Role::CODE_HR_HEAD,
                ]) ?? false,
                'canViewWorkSchedules' => $canViewWorkSchedules,
                'canMutateWorkSchedules' => $canMutateWorkSchedules,
                'canManageScheduleAssignments' => $canManageWorkspaceAttendancePolicies,
                'canEditOrganizationStructure' => $user?->canEditOrganizationStructure() ?? false,
                'canManageCalendarCategories' => $canManageCalendarData,
                'canManageCalendarEventActions' => $canManageCalendarData,
                'canCreateCompanyCalendarEvent' => $user?->hasAnyRole([
                    Role::CODE_SUPER_ADMIN,
                    Role::CODE_HR_HEAD,
                ]) ?? false,
                'canCreateBranchCalendarEvent' => $this->canCreateBranchCalendarEvent($user, $branchContext),
                'canCreateTeamCalendarEvent' => $this->canCreateTeamCalendarEvent($user, $branchContext),
                'canManageHolidayTypes' => $user?->hasAnyRole([
                    Role::CODE_SUPER_ADMIN,
                    Role::CODE_HR_HEAD,
                ]) ?? false,
                'canManageOrganizationHolidays' => $user?->hasAnyRole([
                    Role::CODE_SUPER_ADMIN,
                    Role::CODE_HR_HEAD,
                ]) ?? false,
                'canViewEmployeeTeamLeaveOvertime' => $this->employeeTeamHrPagesAccess->allows($user, $request),
                'canAddEmployeeTeamLeaveOvertimeEntry' => $this->employeeTeamHrPagesAccess->allowsAddingTeamLeaveOvertimeEntries($user, $request),
                'canViewLeaveOvertimePolicies' => $canViewLeaveOvertimePolicies,
                'canMutateLeaveOvertimePolicies' => $canMutateLeaveOvertimePolicies,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'showHrAdminNav' => $this->canShowAdministrationNav($user, $request),
            'canSwitchBranches' => $canSwitchBranches,
            'branchContext' => $branchContext,
            'switchableBranches' => $this->resolveSwitchableBranches($user),
        ];
    }

    /**
     * @param  array{id: int, code: string, name: string}|null  $branchContext
     */
    private function canCreateBranchCalendarEvent(?User $user, ?array $branchContext): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasAnyRole([Role::CODE_SUPER_ADMIN, Role::CODE_HR_HEAD])) {
            return true;
        }

        if (! $user->hasRole(Role::CODE_HR_MANAGER) || $branchContext === null) {
            return false;
        }

        $managedRootIds = $this->branchContextService->managedBranchRootIdsFor($user);

        return in_array((int) $branchContext['id'], $managedRootIds, true);
    }

    /**
     * @param  array{id: int, code: string, name: string}|null  $branchContext
     */
    private function canCreateTeamCalendarEvent(?User $user, ?array $branchContext): bool
    {
        return $this->userIsActiveUnitHeadForWorkspaceBranch($user, $branchContext);
    }

    /**
     * Active assignment as unit head for a unit under the workspace branch root.
     *
     * @param  array{id: int, code: string, name: string}|null  $branchContext
     */
    private function userIsActiveUnitHeadForWorkspaceBranch(?User $user, ?array $branchContext): bool
    {
        if (! $user instanceof User || $user->employee_id === null || $branchContext === null) {
            return false;
        }

        $today = now()->toDateString();
        $branchRootId = (int) $branchContext['id'];

        $activeHeadAssignments = EmployeeAssignment::query()
            ->where('employee_id', $user->employee_id)
            ->where('is_head', true)
            ->whereNotNull('organizational_unit_id', 'and')
            ->whereNull('deleted_at', 'and', false)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date', 'and', false)
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->pluck('organizational_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($activeHeadAssignments as $unitId) {
            if ($this->isUnitWithinRoot($unitId, $branchRootId)) {
                return true;
            }
        }

        return false;
    }

    private function isUnitWithinRoot(int $unitId, int $rootUnitId): bool
    {
        if ($unitId <= 0 || $rootUnitId <= 0) {
            return false;
        }

        if ($unitId === $rootUnitId) {
            return true;
        }

        $parentId = OrganizationalUnit::query()
            ->whereKey($unitId)
            ->where('is_active', true)
            ->value('parent_id');

        while ($parentId !== null) {
            $current = (int) $parentId;
            if ($current === $rootUnitId) {
                return true;
            }

            $parentId = OrganizationalUnit::query()
                ->whereKey($current)
                ->where('is_active', true)
                ->value('parent_id');
        }

        return false;
    }

    private function canShowAdministrationNav(?User $user, Request $request): bool
    {
        if (! filter_var(env('SHOW_HR_ADMIN_NAV', 'true'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return $this->adminUserActionAccessService->canAccessAdministration($user, $request);
    }

    /**
     * @return list<array{id: int, code: string, name: string, area_name?: string|null, group_label?: string}>
     */
    private function resolveSwitchableBranches(?User $user): array
    {
        if (! $this->branchContextService->canSwitchBranchContext($user)) {
            return [];
        }

        return $this->branchContextService
            ->branchesForPicker($user)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeAuthUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $data = $user->toArray();
        $avatarPath = $user->avatar_path;
        $data['avatar'] = filled($avatarPath)
            ? asset('storage/'.ltrim((string) $avatarPath, '/'))
            : null;

        return $data;
    }
}
