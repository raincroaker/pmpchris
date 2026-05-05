<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamAttendanceDayRequest;
use App\Services\BranchContextService;
use App\Services\TeamAttendanceDayMutationService;
use Illuminate\Http\RedirectResponse;

class StoreTeamAttendanceDayController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private TeamAttendanceDayMutationService $teamAttendanceDayMutationService,
    ) {}

    public function __invoke(StoreTeamAttendanceDayRequest $request): RedirectResponse
    {
        $organization = $this->branchContextService->defaultOrganization();
        abort_if($organization === null, 404);

        $validated = $request->validated();
        $validated['organization_id'] = (int) $organization->id;

        $user = $request->user();
        abort_if($user === null, 403);

        $this->teamAttendanceDayMutationService->store($validated, $user);

        $previous = url()->previous();

        if ($previous !== '' && str_contains($previous, '/attendance/team')) {
            return redirect()->to($previous);
        }

        return redirect()->route('attendance.team');
    }
}
