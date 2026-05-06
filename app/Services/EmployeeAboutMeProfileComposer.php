<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAddress;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeContact;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\WorkScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmployeeAboutMeProfileComposer
{
    /**
     * @var list<string>
     */
    private const SCHEDULE_DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function __construct(
        private BranchContextService $branchContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compose(Request $request, Employee $employee, ?Organization $organization): array
    {
        $today = Carbon::now()->startOfDay();
        $employmentsForEmployee = $employee->employments;
        /** @var EmployeeEmployment|null $currentEmployment */
        $currentEmployment = $employmentsForEmployee->firstWhere('is_current', true)
            ?? $employmentsForEmployee->sortByDesc(fn (EmployeeEmployment $row) => Carbon::parse($row->hire_date))->first();

        $affiliationScope = $this->scopedAffiliations($employee->affiliations, $organization);
        $sortedAffiliationHistory = $affiliationScope
            ->sortByDesc(fn (EmployeeAffiliation $row) => Carbon::parse($row->start_date)->timestamp);

        [$branchLabel, $orgScopeLabel] = $this->resolveHeroAffiliationLabels($request, $today, $affiliationScope);

        $schedule = $this->composeSchedule($employee);

        return [
            'employee_id' => (int) $employee->getKey(),
            'display_name' => $this->formatDisplayNameSansSuffix($employee),
            'first_name' => (string) $employee->first_name,
            'middle_name' => (string) ($employee->middle_name ?? ''),
            'last_name' => (string) $employee->last_name,
            'legal_name_line' => $this->formatLegalNameLine($employee),
            'id_number' => (string) ($employee->id_number ?? ''),
            'avatar_url' => $this->resolveAvatarUrl($employee),
            'attendance_id' => filled($employee->attendance_id) ? (string) $employee->attendance_id : null,
            'status_label' => $currentEmployment !== null
                ? $this->formatEmploymentStatus((string) $currentEmployment->employment_status)
                : '—',
            'branch_label' => $branchLabel,
            'org_scope_label' => $orgScopeLabel,
            'schedule_label' => $schedule['label'],
            'schedule_template_code' => $schedule['code'],
            'schedule_day_tokens' => $schedule['day_tokens'],
            'schedule_working_days' => $schedule['working_days'],
            'stats' => $this->composeStatsTiles($employee, $currentEmployment, $today),
            'demographics' => $this->composeDemographics($employee),
            'personal_contacts' => $this->mapContacts($employee->contacts, 'personal'),
            'emergency_contacts' => $this->mapContacts($employee->contacts, 'emergency'),
            'current_address' => $this->composeAddressBlock($employee->addresses, 'current'),
            'permanent_address' => $this->composeAddressBlock($employee->addresses, 'permanent'),
            'employment_rows' => $this->composeEmploymentRows($currentEmployment),
            'affiliation_history' => $this->mapAffiliationHistory($sortedAffiliationHistory),
            'unit_assignment_history' => $this->mapOrgChartAssignmentHistory($employee->assignments, $currentEmployment),
            'positions' => $this->mapPositions($employee->positions, $currentEmployment),
            'attendance_summary' => $this->composeAttendancePlaceholder(),
        ];
    }

    /**
     * @param  Collection<int, EmployeeAffiliation>  $affiliations
     * @return Collection<int, EmployeeAffiliation>
     */
    private function scopedAffiliations(Collection $affiliations, ?Organization $organization): Collection
    {
        if ($organization === null) {
            return collect();
        }

        return $affiliations
            ->where('organization_id', $organization->id)
            ->values();
    }

    private function formatDisplayNameSansSuffix(Employee $employee): string
    {
        $parts = array_values(array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
        ], fn (?string $part): bool => filled($part)));

        if ($parts === []) {
            return 'Employee #'.$employee->id;
        }

        return implode(' ', $parts);
    }

    private function formatLegalNameLine(Employee $employee): string
    {
        $parts = array_values(array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
            $employee->suffix,
        ], fn (?string $part): bool => filled($part)));

        return $parts === [] ? '' : implode(' ', $parts);
    }

    private function resolveAvatarUrl(Employee $employee): ?string
    {
        $user = $employee->user;

        $avatarPath = $user?->avatar_path ?? null;
        if ($avatarPath === null || $avatarPath === '') {
            return null;
        }

        return asset('storage/'.$avatarPath);
    }

    /**
     * @param  Collection<int, EmployeeAffiliation>  $forOrganization
     * @return array{0: string, 1: string}
     */
    private function resolveHeroAffiliationLabels(Request $request, Carbon $today, Collection $forOrganization): array
    {
        $active = $forOrganization->filter(fn (EmployeeAffiliation $row) => $this->affiliationSpanActiveForDate($row, $today))
            ->values();

        /** @var Collection<int, EmployeeAffiliation> $branchRoots */
        $branchRoots = $active->filter(fn (EmployeeAffiliation $row) => $row->root_unit_id !== null)->values();

        $workspaceBranchId = 0;
        $ctx = $this->branchContext->workspaceBranchContext($request);
        if (is_array($ctx) && isset($ctx['id'])) {
            $workspaceBranchId = (int) $ctx['id'];
        }

        $chosenBranchAffiliation = null;

        if ($branchRoots->count() >= 2 && $workspaceBranchId > 0) {
            $chosenBranchAffiliation = $branchRoots->first(
                fn (EmployeeAffiliation $row) => (int) $row->root_unit_id === $workspaceBranchId,
            );
        }

        if ($chosenBranchAffiliation === null && $branchRoots->count() >= 2) {
            $chosenBranchAffiliation = $this->pickAffiliationByPrimaryStable($branchRoots);
        }

        if ($chosenBranchAffiliation === null && $branchRoots->count() === 1) {
            $chosenBranchAffiliation = $branchRoots->first();
        }

        if ($chosenBranchAffiliation !== null && $chosenBranchAffiliation->rootUnit !== null) {
            return [(string) $chosenBranchAffiliation->rootUnit->name, 'Branch-scoped'];
        }

        $hasOrgWide = $active->contains(fn (EmployeeAffiliation $row) => $row->root_unit_id === null);

        if ($hasOrgWide && $chosenBranchAffiliation === null && $branchRoots->isEmpty()) {
            return ['Org-wide', 'Organization-wide'];
        }

        return ['—', '—'];
    }

    /**
     * Prefer primary, then deterministic id ordering.
     *
     * @param  Collection<int, EmployeeAffiliation>  $rows
     */
    private function pickAffiliationByPrimaryStable(Collection $rows): ?EmployeeAffiliation
    {
        return $rows
            ->sort(function (EmployeeAffiliation $a, EmployeeAffiliation $b): int {
                $primaryCompare = ($b->is_primary ? 1 : 0) <=> ($a->is_primary ? 1 : 0);
                if ($primaryCompare !== 0) {
                    return $primaryCompare;
                }

                return $a->id <=> $b->id;
            })
            ->first();
    }

    private function affiliationSpanActiveForDate(EmployeeAffiliation $affiliation, Carbon $today): bool
    {
        $startRaw = $affiliation->start_date;
        $endRaw = $affiliation->end_date;

        if ($startRaw === null || Carbon::parse($startRaw)->startOfDay()->gt($today)) {
            return false;
        }

        if ($endRaw !== null && Carbon::parse($endRaw)->startOfDay()->lt($today)) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, EmployeeAffiliation>  $sortedDescendingByStartDate
     * @return list<array{id: int, root_unit_id: int|null, unit: string, unit_type: string, code: string|null, start_date: string, end_date: string|null}>
     */
    private function mapAffiliationHistory(Collection $sortedDescendingByStartDate): array
    {
        return $sortedDescendingByStartDate
            ->map(function (EmployeeAffiliation $affiliation): array {
                $rootUnit = $affiliation->rootUnit;

                if ($affiliation->root_unit_id !== null && $rootUnit !== null) {
                    $typeNameRaw = $rootUnit->unitType?->name;

                    return [
                        'id' => (int) $affiliation->getKey(),
                        'root_unit_id' => $affiliation->root_unit_id !== null ? (int) $affiliation->root_unit_id : null,
                        'unit' => (string) $rootUnit->name,
                        'unit_type' => filled($typeNameRaw) ? (string) $typeNameRaw : 'Branch',
                        'code' => filled($rootUnit->code) ? (string) $rootUnit->code : null,
                        'start_date' => Carbon::parse($affiliation->start_date)->format('M j, Y'),
                        'end_date' => $affiliation->end_date !== null ? Carbon::parse($affiliation->end_date)->format('M j, Y') : null,
                    ];
                }

                return [
                    'id' => (int) $affiliation->getKey(),
                    'root_unit_id' => null,
                    'unit' => 'Organization-wide',
                    'unit_type' => 'Organization',
                    'code' => null,
                    'start_date' => Carbon::parse($affiliation->start_date)->format('M j, Y'),
                    'end_date' => $affiliation->end_date !== null ? Carbon::parse($affiliation->end_date)->format('M j, Y') : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Org chart {@see EmployeeAssignment} rows for the current employment (newest first).
     *
     * @param  iterable<int, EmployeeAssignment>  $assignments
     * @return list<array{id: int, unit: string, unit_type: string, code: string|null, start_date: string, end_date: string|null}>
     */
    private function mapOrgChartAssignmentHistory(iterable $assignments, ?EmployeeEmployment $employment): array
    {
        if ($employment === null) {
            return [];
        }

        /** @var list<EmployeeAssignment> $rows */
        $rows = [];

        foreach ($assignments as $assignment) {
            if ((int) $assignment->employee_employment_id !== (int) $employment->getKey()) {
                continue;
            }

            $rows[] = $assignment;
        }

        usort(
            $rows,
            fn (EmployeeAssignment $a, EmployeeAssignment $b): int => Carbon::parse($b->start_date)->timestamp <=> Carbon::parse($a->start_date)->timestamp,
        );

        return array_values(array_map(function (EmployeeAssignment $assignment): array {
            if ($assignment->organizational_unit_id !== null && $assignment->organizationalUnit !== null) {
                $u = $assignment->organizationalUnit;
                $typeNameRaw = $u->unitType?->name;

                return [
                    'id' => (int) $assignment->getKey(),
                    'unit' => (string) $u->name,
                    'unit_type' => filled($typeNameRaw) ? (string) $typeNameRaw : 'Unit',
                    'code' => filled($u->code) ? (string) $u->code : null,
                    'start_date' => Carbon::parse($assignment->start_date)->format('M j, Y'),
                    'end_date' => $assignment->end_date !== null ? Carbon::parse($assignment->end_date)->format('M j, Y') : null,
                ];
            }

            $org = $assignment->organization;

            return [
                'id' => (int) $assignment->getKey(),
                'unit' => $org !== null ? 'Organization: '.(string) $org->name : 'Organization',
                'unit_type' => 'Organization',
                'code' => $org !== null && filled($org->code) ? (string) $org->code : null,
                'start_date' => Carbon::parse($assignment->start_date)->format('M j, Y'),
                'end_date' => $assignment->end_date !== null ? Carbon::parse($assignment->end_date)->format('M j, Y') : null,
            ];
        }, $rows));
    }

    /**
     * Payload for About Me work editor (active current employment only).
     *
     * @return array{
     *     employment_id: int,
     *     employee_id: int,
     *     hire_date: string,
     *     hire_adjustment_max_date: string|null,
     *     positions: list<array{id: int|null, position_id: int, start_date: string, end_date: string|null, is_primary: bool}>,
     *     affiliations: list<array{id: int|null, root_unit_id: int|null, start_date: string, end_date: string|null, is_primary: bool}>
     * }|null
     */
    public function composeAboutMeWorkPayload(Employee $employee, ?EmployeeEmployment $employment): ?array
    {
        if ($employment === null) {
            return null;
        }

        if (! $employment->is_current || $employment->employment_status !== EmployeeEmployment::STATUS_ACTIVE) {
            return null;
        }

        if ($employment->separation_date !== null) {
            return null;
        }

        /** @var list<array<string, mixed>> $positionRows */
        $positionRows = [];

        foreach ($employee->positions as $row) {
            if ((int) $row->employee_employment_id !== (int) $employment->getKey()) {
                continue;
            }

            $positionRows[] = [
                'id' => (int) $row->getKey(),
                'position_id' => (int) $row->position_id,
                'start_date' => Carbon::parse($row->start_date)->toDateString(),
                'end_date' => $row->end_date !== null ? Carbon::parse($row->end_date)->toDateString() : null,
                'is_primary' => (bool) $row->is_primary,
            ];
        }

        usort(
            $positionRows,
            fn (array $a, array $b): int => strcmp((string) $b['start_date'], (string) $a['start_date']),
        );

        /** @var list<array<string, mixed>> $affiliationRows */
        $affiliationRows = [];

        foreach ($employee->affiliations as $row) {
            if ((int) $row->employee_employment_id !== (int) $employment->getKey()) {
                continue;
            }

            $affiliationRows[] = [
                'id' => (int) $row->getKey(),
                'root_unit_id' => $row->root_unit_id !== null ? (int) $row->root_unit_id : null,
                'start_date' => Carbon::parse($row->start_date)->toDateString(),
                'end_date' => $row->end_date !== null ? Carbon::parse($row->end_date)->toDateString() : null,
                'is_primary' => (bool) $row->is_primary,
            ];
        }

        usort(
            $affiliationRows,
            fn (array $a, array $b): int => strcmp((string) $b['start_date'], (string) $a['start_date']),
        );

        return [
            'employment_id' => (int) $employment->getKey(),
            'employee_id' => (int) $employee->getKey(),
            'hire_date' => Carbon::parse($employment->hire_date)->toDateString(),
            'hire_adjustment_max_date' => EmploymentHireAdjustmentBoundary::latestPermittedHireDateIsoForEmployment(
                (int) $employment->getKey(),
            ),
            'positions' => $positionRows,
            'affiliations' => $affiliationRows,
        ];
    }

    /**
     * @return array{label: string, code: string|null, day_tokens: list<string>, working_days: string}
     */
    private function composeSchedule(Employee $employee): array
    {
        $template = $employee->workScheduleTemplate;
        if ($template === null) {
            return [
                'label' => '—',
                'code' => null,
                'day_tokens' => [],
                'working_days' => '',
            ];
        }

        $dayTokens = $this->normalizeScheduleDayTokens(is_array($template->days) ? $template->days : []);

        [$tin, $tout] = $this->resolveScheduleRegularHoursDisplayRange($template);
        if ($tin === '' || $tout === '') {
            $tin = $this->normalizeTimeToFiveChars((string) $template->time_in);
            $tout = $this->normalizeTimeToFiveChars((string) $template->time_out);
        }

        $abbrDays = $this->abbrevDayRangeLabels(is_array($template->days) ? $template->days : []);

        $labelPieces = [(string) $template->name];
        if ($abbrDays !== '') {
            $labelPieces[] = $abbrDays;
        }
        if ($tin !== '' && $tout !== '') {
            $labelPieces[] = $tin.'–'.$tout;
        }

        return [
            'label' => implode(' · ', $labelPieces),
            'code' => null,
            'day_tokens' => $dayTokens,
            'working_days' => $this->formatScheduleWorkingDaysCommaSeparated($dayTokens),
        ];
    }

    /**
     * @param  list<string>  $dayTokens
     */
    private function formatScheduleWorkingDaysCommaSeparated(array $dayTokens): string
    {
        if ($dayTokens === []) {
            return '';
        }

        /** @var array<string, string> */
        static $abbr = [
            'mon' => 'Mon',
            'tue' => 'Tue',
            'wed' => 'Wed',
            'thu' => 'Thu',
            'fri' => 'Fri',
            'sat' => 'Sat',
            'sun' => 'Sun',
        ];

        /** @var array<string, int> */
        $weights = array_flip(self::SCHEDULE_DAY_KEYS);

        $sorted = array_values($dayTokens);
        usort($sorted, function (string $a, string $b) use ($weights): int {
            return ($weights[$a] ?? 99) <=> ($weights[$b] ?? 99);
        });

        $labels = [];

        foreach ($sorted as $token) {
            if (! is_string($token)) {
                continue;
            }

            $key = strtolower(trim($token));

            if (isset($abbr[$key])) {
                $labels[] = $abbr[$key];
            }
        }

        return implode(', ', $labels);
    }

    /**
     * Regular working hours for display: first session start through second session end (excludes a third OT block).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveScheduleRegularHoursDisplayRange(WorkScheduleTemplate $template): array
    {
        $segments = $template->segments;
        if (! is_array($segments) || $segments === []) {
            return ['', ''];
        }

        $first = $segments[0] ?? null;
        if (! is_array($first)) {
            return ['', ''];
        }

        if (count($segments) >= 2 && is_array($segments[1])) {
            $tin = $this->normalizeTimeToFiveChars(isset($first['time_in']) ? (string) $first['time_in'] : '');
            $tout = $this->normalizeTimeToFiveChars(isset($segments[1]['time_out']) ? (string) $segments[1]['time_out'] : '');

            return [$tin, $tout];
        }

        $tin = $this->normalizeTimeToFiveChars(isset($first['time_in']) ? (string) $first['time_in'] : '');
        $tout = $this->normalizeTimeToFiveChars(isset($first['time_out']) ? (string) $first['time_out'] : '');

        return [$tin, $tout];
    }

    private function normalizeTimeToFiveChars(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }

        return strlen($s) >= 5 ? substr($s, 0, 5) : $s;
    }

    /**
     * @param  array<int, mixed>  $days
     * @return list<string>
     */
    private function normalizeScheduleDayTokens(array $days): array
    {
        /** @var array<string, string> */
        static $fullNames = [
            'monday' => 'mon',
            'tuesday' => 'tue',
            'wednesday' => 'wed',
            'thursday' => 'thu',
            'friday' => 'fri',
            'saturday' => 'sat',
            'sunday' => 'sun',
        ];

        $out = [];

        foreach ($days as $day) {
            if (! is_string($day)) {
                continue;
            }

            $key = strtolower(trim($day));
            if ($key === '') {
                continue;
            }

            if (in_array($key, self::SCHEDULE_DAY_KEYS, true)) {
                if (! in_array($key, $out, true)) {
                    $out[] = $key;
                }

                continue;
            }

            if (isset($fullNames[$key])) {
                $mapped = $fullNames[$key];
                if (! in_array($mapped, $out, true)) {
                    $out[] = $mapped;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string|string>  $days
     */
    private function abbrevDayRangeLabels(array $days): string
    {
        if ($days === []) {
            return '';
        }

        /** @var array<string, string> */
        static $abbr = [
            'mon' => 'Mon',
            'tue' => 'Tue',
            'wed' => 'Wed',
            'thu' => 'Thu',
            'fri' => 'Fri',
            'sat' => 'Sat',
            'sun' => 'Sun',
        ];

        /** @var list<string> */
        $orderedKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        $indices = [];

        foreach ($days as $day) {
            if (! is_string($day)) {
                continue;
            }

            $needle = strtolower(trim($day));

            foreach ($orderedKeys as $idx => $key) {
                if ($key === $needle) {
                    $indices[] = $idx;

                    break;
                }
            }
        }

        $indices = array_values(array_unique($indices));
        sort($indices);

        if ($indices === []) {
            return '';
        }

        $labels = array_map(static function (int $index) use ($orderedKeys, $abbr): string {
            $key = $orderedKeys[$index];

            return $abbr[$key];
        }, $indices);

        $firstIdx = reset($indices);
        $lastIdx = end($indices);

        if ($labels !== []
            && is_int($firstIdx)
            && is_int($lastIdx)
            && count($indices) >= 2
            && ($lastIdx - $firstIdx + 1) === count($indices)
        ) {
            return $labels[0].'–'.($labels[count($labels) - 1] ?? $labels[0]);
        }

        return implode(', ', $labels);
    }

    /**
     * @return list<array{label: string, value: string, hint?: string}>
     */
    private function composeStatsTiles(
        Employee $employee,
        ?EmployeeEmployment $currentEmployment,
        Carbon $today,
    ): array {
        if ($currentEmployment === null) {
            return [
                ['label' => 'Hire date', 'value' => '—'],
                ['label' => 'Tenure', 'value' => '—', 'hint' => 'Approximate from hire date'],
                ['label' => 'Primary position', 'value' => '—'],
                ['label' => 'Primary unit', 'value' => '—', 'hint' => 'Current assignment'],
            ];
        }

        $hireCarbon = Carbon::parse($currentEmployment->hire_date)->startOfDay();
        $sepCarbon = $currentEmployment->separation_date !== null
            ? Carbon::parse($currentEmployment->separation_date)->startOfDay()
            : null;
        $endForTenure = $sepCarbon !== null ? $sepCarbon : $today->copy()->startOfDay();

        [$primaryTitle, $primaryCode] = $this->resolvePrimaryPosition($employee->positions, $currentEmployment);
        [$unitLabel] = $this->resolvePrimaryUnit($employee->assignments, $currentEmployment);

        $statsBlock = [
            [
                'label' => 'Hire date',
                'value' => $hireCarbon->format('M j, Y'),
            ],
            [
                'label' => 'Tenure',
                'value' => $this->approximateTenureYm($hireCarbon, $endForTenure),
                'hint' => 'Approximate from hire date',
            ],
            [
                'label' => 'Primary position',
                'value' => filled($primaryTitle) ? $primaryTitle : '—',
            ],
            [
                'label' => 'Primary unit',
                'value' => filled($unitLabel) ? $unitLabel : '—',
                'hint' => 'Current assignment',
            ],
        ];

        if (filled($primaryCode)) {
            $statsBlock[2]['hint'] = $primaryCode;
        }

        return $statsBlock;
    }

    /**
     * @param  iterable<int, EmployeeAssignment>  $assignments
     * @return array{0: string|null, 1: null}
     */
    private function resolvePrimaryUnit(iterable $assignments, EmployeeEmployment $employment): array
    {
        $today = Carbon::now()->startOfDay();

        $rows = [];

        foreach ($assignments as $assignment) {
            if ((int) $assignment->employee_employment_id !== (int) $employment->getKey()) {
                continue;
            }

            $rows[] = $assignment;
        }

        usort($rows, function (EmployeeAssignment $a, EmployeeAssignment $b) use ($today): int {
            $aOpen = $a->end_date === null || Carbon::parse($a->end_date)->startOfDay()->gte($today);
            $bOpen = $b->end_date === null || Carbon::parse($b->end_date)->startOfDay()->gte($today);

            if ($aOpen !== $bOpen) {
                return $aOpen ? -1 : 1;
            }

            $primaryCmp = (($b->is_primary ? 1 : 0) <=> ($a->is_primary ? 1 : 0));

            if ($primaryCmp !== 0) {
                return $primaryCmp;
            }

            return Carbon::parse($b->start_date)->timestamp <=> Carbon::parse($a->start_date)->timestamp;
        });

        foreach ($rows as $assignment) {
            if ($assignment->organizational_unit_id !== null && $assignment->organizationalUnit !== null) {
                return [(string) $assignment->organizationalUnit->name, null];
            }

            if ($assignment->organization_id !== null && $assignment->organization !== null) {
                return ['Organization: '.(string) $assignment->organization->name, null];
            }
        }

        return [null, null];
    }

    /**
     * @param  iterable<int, EmployeePosition>  $positions
     * @return array{string, string|null}
     */
    private function resolvePrimaryPosition(iterable $positions, EmployeeEmployment $employment): array
    {
        $matches = [];

        foreach ($positions as $position) {
            if ((int) $position->employee_employment_id !== (int) $employment->getKey()) {
                continue;
            }

            $matches[] = $position;
        }

        if ($matches === []) {
            return ['', null];
        }

        usort(
            $matches,
            fn (EmployeePosition $a, EmployeePosition $b): int => (($b->is_primary ? 1 : 0) <=> ($a->is_primary ? 1 : 0))
                ?: ($a->id <=> $b->id),
        );

        /** @var EmployeePosition $first */
        $first = reset($matches);
        $linked = $first->position;

        if ($linked === null) {
            return ['—', null];
        }

        $titleRaw = $linked->title ?? '';
        $codeRaw = $linked->code ?? '';

        return [filled($titleRaw) ? (string) $titleRaw : '', filled($codeRaw) ? (string) $codeRaw : null];
    }

    /**
     * @param  iterable<int, EmployeePosition>  $positions
     * @return list<array{title: string, code: string, is_primary: bool, start_date: string, end_date: string|null}>
     */
    private function mapPositions(iterable $positions, ?EmployeeEmployment $employment): array
    {
        if ($employment === null) {
            return [];
        }

        /** @var list<EmployeePosition> $rows */
        $rows = [];

        foreach ($positions as $row) {
            if ((int) $row->employee_employment_id !== (int) $employment->getKey()) {
                continue;
            }

            $rows[] = $row;
        }

        usort(
            $rows,
            fn (EmployeePosition $a, EmployeePosition $b): int => Carbon::parse($b->start_date)->timestamp <=> Carbon::parse($a->start_date)->timestamp,
        );

        return array_values(array_map(function (EmployeePosition $row): array {
            $pos = $row->position;

            return [
                'title' => filled($pos?->title) ? (string) $pos->title : '—',
                'code' => filled($pos?->code) ? (string) $pos->code : '—',
                'is_primary' => (bool) $row->is_primary,
                'start_date' => Carbon::parse($row->start_date)->format('M j, Y'),
                'end_date' => $row->end_date !== null ? Carbon::parse($row->end_date)->format('M j, Y') : null,
            ];
        }, $rows));
    }

    /**
     * @return array<string, mixed>
     */
    private function composeDemographics(Employee $employee): array
    {
        $birth = $employee->birthdate;
        $iso = null;
        if ($birth !== null) {
            $iso = Carbon::parse($birth)->toDateString();
        }

        $religionRaw = (string) ($employee->religion ?? '');
        $religionDisplay = filled($religionRaw) ? $religionRaw : null;

        $religionOther = $employee->religion_other !== null
            ? trim((string) $employee->religion_other)
            : null;
        if ($religionOther === '') {
            $religionOther = null;
        }

        return [
            'birthdate_iso' => $iso,
            'birthdate_display' => $iso !== null ? Carbon::parse($iso)->format('M j, Y') : '',
            'sex' => $this->prettifyToken((string) ($employee->sex ?? '')),
            'civil_status' => $this->prettifyToken(str_replace('_', ' ', (string) ($employee->civil_status ?? ''))),
            'nationality' => filled((string) ($employee->nationality ?? '')) ? (string) $employee->nationality : '—',
            'religion' => $religionDisplay,
            'religion_other' => $religionOther,
        ];
    }

    private function prettifyToken(string $raw): string
    {
        if ($raw === '') {
            return '—';
        }

        return mb_convert_case(mb_strtolower($raw), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param  iterable<int, EmployeeContact>  $contacts
     * @return list<array{
     *     category: 'personal'|'emergency',
     *     channel_label: string,
     *     contact_number: string,
     *     email: string|null,
     *     contact_person: string|null,
     *     relationship: string|null,
     *     is_primary: bool
     * }>
     */
    private function mapContacts(iterable $contacts, string $category): array
    {
        $filtered = [];

        foreach ($contacts as $contact) {
            if ($contact->category !== $category) {
                continue;
            }

            $filtered[] = $contact;
        }

        usort(
            $filtered,
            fn (EmployeeContact $a, EmployeeContact $b): int => (($b->is_primary ? 1 : 0) <=> ($a->is_primary ? 1 : 0))
                ?: ($a->id <=> $b->id),
        );

        return array_values(array_map(function (EmployeeContact $contact): array {
            $typeRaw = (string) ($contact->type ?? '');
            $channelLabel = filled($typeRaw)
                ? mb_convert_case(mb_strtolower($typeRaw), MB_CASE_TITLE, 'UTF-8')
                : ucfirst((string) $contact->category);
            $person = $contact->contact_person;
            $relationship = $contact->relationship;

            return [
                'id' => (int) $contact->getKey(),
                'category' => $contact->category === 'emergency' ? 'emergency' : 'personal',
                'channel_label' => $channelLabel,
                'contact_number' => filled((string) ($contact->contact_number ?? '')) ? (string) $contact->contact_number : '',
                'email' => filled((string) ($contact->email ?? '')) ? (string) $contact->email : null,
                'contact_person' => filled((string) ($person ?? '')) ? (string) $person : null,
                'relationship' => filled((string) ($relationship ?? '')) ? (string) $relationship : null,
                'is_primary' => (bool) $contact->is_primary,
            ];
        }, $filtered));
    }

    /**
     * @param  iterable<int, EmployeeAddress>  $addresses
     * @return array{
     *     type: string,
     *     lines: list<string>,
     *     is_primary: bool,
     *     address_line_1?: string,
     *     address_line_2?: string|null,
     *     barangay?: string,
     *     barangay_code?: string|null,
     *     city?: string,
     *     city_code?: string|null,
     *     province?: string,
     *     province_code?: string|null,
     *     zip_code?: string,
     *     country?: string
     * }|null
     */
    private function composeAddressBlock(iterable $addresses, string $type): ?array
    {
        $allowedType = $type === 'permanent' ? 'permanent' : 'current';
        /** @var list<EmployeeAddress> $rows */
        $rows = [];

        foreach ($addresses as $row) {
            if ($row->type !== $allowedType) {
                continue;
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            return null;
        }

        usort(
            $rows,
            fn (EmployeeAddress $a, EmployeeAddress $b): int => (($b->is_primary ? 1 : 0) <=> ($a->is_primary ? 1 : 0))
                ?: ($a->id <=> $b->id),
        );

        /** @var EmployeeAddress $addr */
        $addr = reset($rows);

        $lines = [];

        $lineOne = trim((string) $addr->address_line_1);
        if ($lineOne !== '') {
            $lines[] = $lineOne;
        }

        $lineTwo = trim((string) ($addr->address_line_2 ?? ''));
        if ($lineTwo !== '') {
            $lines[] = $lineTwo;
        }

        $barangay = trim((string) ($addr->barangay ?? ''));
        if ($barangay !== '') {
            if (stripos($barangay, 'brgy') !== 0 && stripos($barangay, 'barangay') !== 0) {
                $lines[] = 'Brgy. '.$barangay;
            } else {
                $lines[] = $barangay;
            }
        }

        $city = trim((string) ($addr->city ?? ''));
        $zip = trim((string) ($addr->zip_code ?? ''));
        $province = trim((string) ($addr->province ?? ''));
        $cityZip = trim(($city.(($city !== '' && $zip !== '') ? ' ' : '')).$zip);

        $locationLinePieces = [];

        if ($cityZip !== '') {
            $locationLinePieces[] = $cityZip;
        }

        if ($province !== '') {
            $locationLinePieces[] = $province;
        }

        $locationLine = implode(', ', array_filter($locationLinePieces, fn (string $p): bool => $p !== ''));

        if ($locationLine !== '') {
            $lines[] = $locationLine;
        }

        $country = trim((string) ($addr->country ?? ''));
        if ($country !== '') {
            $lines[] = $country;
        }

        $structured = [
            'address_line_1' => (string) $addr->address_line_1,
            'address_line_2' => filled((string) ($addr->address_line_2 ?? '')) ? (string) $addr->address_line_2 : null,
            'barangay' => (string) $addr->barangay,
            'barangay_code' => filled((string) ($addr->barangay_code ?? '')) ? (string) $addr->barangay_code : null,
            'city' => (string) $addr->city,
            'city_code' => filled((string) ($addr->city_code ?? '')) ? (string) $addr->city_code : null,
            'province' => (string) $addr->province,
            'province_code' => filled((string) ($addr->province_code ?? '')) ? (string) $addr->province_code : null,
            'zip_code' => (string) $addr->zip_code,
            'country' => (string) $addr->country,
        ];

        if ($lines === []) {
            return array_merge([
                'type' => $allowedType === 'current' ? 'current' : 'permanent',
                'lines' => ['—'],
                'is_primary' => (bool) $addr->is_primary,
            ], $structured);
        }

        return array_merge([
            'type' => $allowedType === 'current' ? 'current' : 'permanent',
            'lines' => $lines,
            'is_primary' => (bool) $addr->is_primary,
        ], $structured);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function composeEmploymentRows(?EmployeeEmployment $employment): array
    {
        if ($employment === null) {
            return [
                ['label' => 'Start date', 'value' => '—'],
                ['label' => 'Separation date', 'value' => '—'],
                ['label' => 'Status', 'value' => '—'],
            ];
        }

        $hire = Carbon::parse($employment->hire_date)->format('F j, Y');
        $separationRaw = $employment->separation_date;
        $separationFormatted = $separationRaw !== null ? Carbon::parse($separationRaw)->format('F j, Y') : '—';

        return [
            ['label' => 'Start date', 'value' => $hire],
            ['label' => 'Separation date', 'value' => $separationFormatted],
            [
                'label' => 'Status',
                'value' => $this->formatEmploymentStatus((string) $employment->employment_status),
            ],
        ];
    }

    private function approximateTenureYm(Carbon $hire, Carbon $endInclusive): string
    {
        if ($endInclusive->lt($hire)) {
            return '—';
        }

        $totalMonths = (int) $hire->diffInMonths($endInclusive);
        $years = intdiv($totalMonths, 12);
        $monthsRemainder = $totalMonths % 12;

        $segments = [];

        if ($years > 0) {
            $segments[] = $years.'y';
        }

        if ($monthsRemainder > 0) {
            $segments[] = $monthsRemainder.'mo';
        }

        if ($segments === []) {
            return '<1mo';
        }

        return implode(' ', $segments);
    }

    private function formatEmploymentStatus(string $raw): string
    {
        $normalized = str_replace('_', ' ', strtolower($raw));

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @return list<array{label: string, value: string, hint?: string}>
     */
    private function composeAttendancePlaceholder(): array
    {
        return [
            [
                'label' => 'Last integrated sync',
                'value' => '—',
                'hint' => 'Connect attendance feeds to populate.',
            ],
            [
                'label' => 'Exceptions (preview)',
                'value' => 'No rows',
                'hint' => 'Schedule overrides & incidents surface here.',
            ],
        ];
    }
}
