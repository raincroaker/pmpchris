<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Role;
use App\Services\BranchContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeesCreateController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * Add Employee wizard: passes active {@see Position} rows for the default organization (org chart / HR scope).
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user?->hasAnyRole([
                Role::CODE_SUPER_ADMIN,
                Role::CODE_HR_HEAD,
                Role::CODE_HR_MANAGER,
            ]),
            403,
        );

        $organization = $this->branchContextService->defaultOrganization();

        /** @var list<array{id: int, code: string, title: string}> $positions */
        $positions = [];
        if ($organization !== null) {
            $positions = Position::query()
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
        }

        /** @var array{id: int, code: string, name: string}|null $affiliationOrganization */
        $affiliationOrganization = $organization === null ? null : [
            'id' => (int) $organization->id,
            'code' => (string) $organization->code,
            'name' => (string) $organization->name,
        ];

        /** @var list<array{id: int, code: string, name: string, area_name: string|null, group_label: string}> $affiliationRoots */
        $affiliationRoots = [];
        if ($organization !== null) {
            $hasFullAccess = $user?->hasAnyRole([
                Role::CODE_SUPER_ADMIN,
                Role::CODE_HR_HEAD,
            ]) ?? false;
            $isManagerOnly = ($user?->hasRole(Role::CODE_HR_MANAGER) ?? false) && ! $hasFullAccess;

            $affiliationRoots = $isManagerOnly
                ? $this->branchContextService->managedBranchesForPicker($user)->values()->all()
                : $this->branchContextService->branchesForPicker($user)->values()->all();
        }

        $allowOrgWideAffiliation = $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;

        return Inertia::render('Employees/Create', [
            'positions' => $positions,
            'affiliationOrganization' => $affiliationOrganization,
            'affiliationRoots' => $affiliationRoots,
            'allowOrgWideAffiliation' => $allowOrgWideAffiliation,
        ]);
    }
}
