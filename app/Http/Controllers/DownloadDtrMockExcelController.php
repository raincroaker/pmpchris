<?php

namespace App\Http\Controllers;

use App\Exports\DtrMockExport;
use App\Exports\TeamAttendanceDtrWorkbookExport;
use App\Http\Requests\DownloadTeamAttendanceDtrRequest;
use App\Services\BranchContextService;
use App\Services\TeamAttendanceDtrExportService;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadDtrMockExcelController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private TeamAttendanceDtrExportService $teamAttendanceDtrExportService,
    ) {}

    public function __invoke(DownloadTeamAttendanceDtrRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();
        $mode = (string) ($validated['mode'] ?? 'sample');

        if ($mode !== 'individual' && $mode !== 'team') {
            $export = DtrMockExport::sample();

            return Excel::download($export, $export->downloadFileName());
        }

        $organization = $this->branchContextService->defaultOrganization();
        $workspace = $this->branchContextService->workspaceBranchContext($request);
        if ($organization === null || $workspace === null) {
            $fallback = DtrMockExport::sample();

            return Excel::download($fallback, $fallback->downloadFileName());
        }

        $sheets = $this->teamAttendanceDtrExportService->buildExportsForScope(
            (int) $organization->id,
            (int) $workspace['id'],
            $mode,
            isset($validated['employee_id']) ? (int) $validated['employee_id'] : null,
            isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
            (string) $validated['date_from'],
            (string) $validated['date_to'],
        );

        if ($sheets === []) {
            abort(422, 'No employees matched the selected scope for DTR generation.');
        }

        if (count($sheets) === 1) {
            /** @var DtrMockExport $single */
            $single = $sheets[0];

            return Excel::download($single, $single->downloadFileName());
        }

        $unitId = (int) ($validated['unit_id'] ?? 0);
        $unit = \App\Models\OrganizationalUnit::query()->find($unitId);
        $unitCode = $unit?->code !== null && $unit->code !== ''
            ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) $unit->code)
            : sprintf('UNIT%d', $unitId);
        $periodCode = \Carbon\CarbonImmutable::parse((string) $validated['date_from'])->format('M-Y');
        $filename = sprintf('DTR_%s_%s.xlsx', $unitCode, $periodCode);

        return Excel::download(new TeamAttendanceDtrWorkbookExport($sheets), $filename);
    }
}
