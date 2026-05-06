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
                /** Documents libraries — Admin View (approval queues); see docs/documents-approval-spec.md §4 */
                'canViewDocumentAdminViewCompany' => $this->canViewDocumentAdminViewCompany($user),
                'canViewDocumentAdminViewBranch' => $this->canViewDocumentAdminViewBranch($user, $request),
                'canViewDocumentAdminViewTeam' => $this->canViewDocumentAdminViewTeam($user, $request, $branchContext),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'showHrAdminNav' => $this->canShowAdministrationNav($user, $request),
            'canSwitchBranches' => $canSwitchBranches,
            'branchContext' => $branchContext,
            'switchableBranches' => $this->resolveSwitchableBranches($user),
            'documents' => [
                'teamAssignmentUnitIds' => $this->documentsTeamSelectableUnitIdsForUser($user, $request),
                'teamHeadUnitIds' => $this->documentsTeamHeadUnitIdsForUser($user, $request),
            ],
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
     * Company document approvers: {@see Role::CODE_SUPER_ADMIN}, {@see Role::CODE_HR_HEAD} only (documents-approval-spec §4.3).
     */
    private function canViewDocumentAdminViewCompany(?User $user): bool
    {
        return $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;
    }

    /**
     * Branch library approvers: same HR gate as team leave/overtime (§4.2 — no unit head).
     */
    private function canViewDocumentAdminViewBranch(?User $user, Request $request): bool
    {
        return $this->employeeTeamHrPagesAccess->allows($user, $request);
    }

    /**
     * Team library approvers: branch gate OR active unit head under workspace branch (§4.1).
     *
     * @param  array{id: int, code: string, name: string}|null  $branchContext
     */
    private function canViewDocumentAdminViewTeam(?User $user, Request $request, ?array $branchContext): bool
    {
        if ($this->employeeTeamHrPagesAccess->allows($user, $request)) {
            return true;
        }

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

    /**
     * Active {@see EmployeeAssignment} organizational_unit_id values under the workspace branch
     * subtree (team-HR form selectable ids), for the signed-in user's employee.
     *
     * @return list<int>
     */
    private function documentsTeamSelectableUnitIdsForUser(?User $user, Request $request): array
    {
        return $this->documentsTeamAssignmentUnitIdsForUser($user, $request, null);
    }

    /**
     * @return list<int>
     */
    private function documentsTeamHeadUnitIdsForUser(?User $user, Request $request): array
    {
        return $this->documentsTeamAssignmentUnitIdsForUser($user, $request, true);
    }

    /**
     * @return list<int>
     */
    private function documentsTeamAssignmentUnitIdsForUser(?User $user, Request $request, ?bool $headsOnly): array
    {
        if (! $user instanceof User || $user->employee_id === null) {
            return [];
        }

        $organization = $this->branchContextService->defaultOrganization();
        if ($organization === null) {
            return [];
        }

        $branchContext = $this->branchContextService->workspaceBranchContext($request);
        if ($branchContext === null) {
            return [];
        }

        $orgId = (int) $organization->id;
        $branchRootId = (int) $branchContext['id'];
        $selectableIds = $this->scheduleAssignmentAccess->teamHrFormSelectableUnitIds($orgId, $branchRootId);
        if ($selectableIds === []) {
            return [];
        }

        $today = now()->toDateString();

        $query = EmployeeAssignment::query()
            ->where('employee_id', $user->employee_id)
            ->whereNotNull('organizational_unit_id', 'and')
            ->whereIn('organizational_unit_id', $selectableIds, 'and', false)
            ->whereNull('deleted_at', 'and', false)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date', 'and', false)
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('start_date', 'and', false)
                    ->orWhereDate('start_date', '<=', $today);
            });

        if ($headsOnly === true) {
            $query->where('is_head', true);
        }

        /** @var list<int> */
        return $query
            ->pluck('organizational_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
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
