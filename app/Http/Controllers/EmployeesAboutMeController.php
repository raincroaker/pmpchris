<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Services\BranchContextService;
use App\Services\EmployeeAboutMeProfileComposer;
use App\Services\EmploymentWorkMutationAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeesAboutMeController extends Controller
{
    public function __invoke(
        Request $request,
        BranchContextService $branchContext,
        EmployeeAboutMeProfileComposer $composer,
        EmploymentWorkMutationAccess $employmentWorkMutationAccess,
    ): Response {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_if($user->employee_id === null, 403);

        $organization = $branchContext->defaultOrganization();

        $employee = Employee::query()
            ->whereKey((int) $user->employee_id)
            ->with([
                'user:id,employee_id,avatar_path',
                'workScheduleTemplate',
                'contacts' => static function ($query): void {
                    $query->orderByDesc('is_primary')->orderBy('id');
                },
                'addresses' => static function ($query): void {
                    $query->orderBy('type')->orderByDesc('is_primary')->orderBy('id');
                },
                'employments' => static function ($query): void {
                    $query->orderByDesc('is_current')->orderByDesc('hire_date');
                },
                'positions.position',
                'assignments' => static function ($query): void {
                    $query->with([
                        'organizationalUnit.unitType',
                        'organization',
                    ]);
                },
                'affiliations' => static function ($query) use ($organization): void {
                    $query->with([
                        'rootUnit.unitType',
                        'employmentPeriod',
                        'organization',
                    ]);

                    if ($organization !== null) {
                        $query->where('organization_id', $organization->id);
                    }
                },
            ])
            ->firstOrFail();

        $profile = $composer->compose($request, $employee, $organization);

        /** @var Employee|null $currentEmploymentModel */
        $currentEmploymentModel = $employee->employments->firstWhere('is_current', true);
        $aboutMeWorkPayload = $composer->composeAboutMeWorkPayload($employee, $currentEmploymentModel);

        /** @var array<string, mixed>|null $aboutMeWork */
        $aboutMeWork = null;

        /** @var array{id: int, code: string, name: string}|null $affiliationOrganization */
        $affiliationOrganization = $organization === null ? null : [
            'id' => (int) $organization->id,
            'code' => (string) $organization->code,
            'name' => (string) $organization->name,
        ];

        $canEditAboutMeHris = $employmentWorkMutationAccess->mayHrManageDirectoryVisibleEmployee($request, $employee);

        if ($canEditAboutMeHris && $aboutMeWorkPayload !== null && $organization !== null) {
            /** @var list<array{id: int, code: string, title: string}> $positionsCatalog */
            $positionsCatalog = Position::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'title'])
                ->map(fn (Position $position): array => [
                    'id' => (int) $position->id,
                    'code' => (string) $position->code,
                    'title' => (string) $position->title,
                ])
                ->all();

            $hasFullAccess = $user->hasAnyRole([
                Role::CODE_SUPER_ADMIN,
                Role::CODE_HR_HEAD,
            ]);
            $isManagerOnly = $user->hasRole(Role::CODE_HR_MANAGER) && ! $hasFullAccess;

            $affiliationRoots = $isManagerOnly
                ? $branchContext->managedBranchesForPicker($user)->values()->all()
                : $branchContext->branchesForPicker($user)->values()->all();

            $allowOrgWideAffiliation = $user->hasAnyRole([
                Role::CODE_SUPER_ADMIN,
                Role::CODE_HR_HEAD,
            ]);

            $aboutMeWork = [
                ...$aboutMeWorkPayload,
                'positions_catalog' => $positionsCatalog,
                'affiliation_roots' => $affiliationRoots,
                'affiliation_organization' => $affiliationOrganization,
                'allow_org_wide_affiliation' => $allowOrgWideAffiliation,
            ];
        }

        return Inertia::render('Employees/AboutMe', [
            'profile' => $profile,
            'aboutMeWork' => $aboutMeWork,
            'canEditAboutMeHris' => $canEditAboutMeHris,
        ]);
    }
}
