<?php

namespace App\Services;

use App\Enums\AttendanceEntrySource;
use App\Enums\AttendanceRecordStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceSegment;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use App\Support\AttendancePunctualityResolver;
use App\Support\AttendanceRecordStatusResolver;
use App\Support\AttendanceScheduleHm;
use App\Support\AttendanceTemplateScheduledNetHours;
use App\Support\EmployeeBranchDirectoryFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class TeamAttendanceDayMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function isAttendanceDayMutableInWorkspace(
        EmployeeAttendanceDay $day,
        Request $request,
    ): bool {
        $organization = $this->branchContextService->defaultOrganization();
        $workspace = $this->branchContextService->workspaceBranchContext($request);
        if ($organization === null || $workspace === null) {
            return false;
        }

        if ((int) $day->organization_id !== (int) $organization->id) {
            return false;
        }

        if ($day->deleted_at !== null) {
            return false;
        }

        $today = now()->toDateString();
        $branchRootId = (int) $workspace['id'];
        $orgId = (int) $organization->id;

        return Employee::query()
            ->whereKey($day->employee_id)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($orgId, $branchRootId, $today): void {
                EmployeeBranchDirectoryFilter::apply($query, $orgId, $branchRootId, $today);
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated, User $user): EmployeeAttendanceDay
    {
        /** @var WorkScheduleTemplate $template */
        $template = WorkScheduleTemplate::query()
            ->whereKey((int) $validated['work_schedule_template_id'])
            ->firstOrFail();

        $segments = $validated['segments'];
        if (! is_array($segments)) {
            $segments = [];
        }

        return DB::transaction(function () use ($validated, $template, $segments, $user): EmployeeAttendanceDay {
            $day = EmployeeAttendanceDay::query()->create([
                'organization_id' => (int) $validated['organization_id'],
                'employee_id' => (int) $validated['employee_id'],
                'organizational_unit_id' => $validated['organizational_unit_id'] !== null
                    ? (int) $validated['organizational_unit_id']
                    : null,
                'work_date' => $validated['work_date'],
                'work_schedule_template_id' => (int) $template->id,
                'clock_pattern' => $template->clock_pattern,
                'is_overnight_schedule' => (bool) $template->is_overnight,
                'ingest_key' => null,
                'original_entry_source' => AttendanceEntrySource::Manual,
                'last_modified_source' => AttendanceEntrySource::Manual,
                'status' => AttendanceRecordStatus::Incomplete,
                'punctuality' => null,
                'net_hours' => null,
                'variance_label' => isset($validated['variance_label'])
                    && is_string($validated['variance_label'])
                    && trim($validated['variance_label']) !== ''
                    ? trim($validated['variance_label'])
                    : null,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);

            $this->replaceSegmentsFromValidated($day, $segments);
            $this->applyDerivations($day, $template, $segments);
            $day->save();

            return $day->fresh(['segments']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(EmployeeAttendanceDay $day, array $validated, User $user): void
    {
        $template = $day->workScheduleTemplate ?? WorkScheduleTemplate::query()
            ->whereKey((int) $day->work_schedule_template_id)
            ->withTrashed()
            ->firstOrFail();

        $segments = $validated['segments'];
        if (! is_array($segments)) {
            $segments = [];
        }

        DB::transaction(function () use ($day, $validated, $segments, $user, $template): void {
            $day->fill([
                'employee_id' => (int) $validated['employee_id'],
                'organizational_unit_id' => $validated['organizational_unit_id'] !== null
                    ? (int) $validated['organizational_unit_id']
                    : null,
                'work_date' => $validated['work_date'],
                'original_entry_source' => $day->original_entry_source,
                'last_modified_source' => AttendanceEntrySource::Manual,
                'variance_label' => isset($validated['variance_label'])
                    && is_string($validated['variance_label'])
                    && trim($validated['variance_label']) !== ''
                    ? trim($validated['variance_label'])
                    : null,
                'updated_by_user_id' => $user->id,
            ]);

            $this->replaceSegmentsFromValidated($day, $segments);
            $this->applyDerivations($day, $template, $segments);

            $day->save();
        });
    }

    public function destroy(EmployeeAttendanceDay $day): void
    {
        $day->delete();
    }

    /**
     * @param  list<mixed>|array<mixed>  $segmentsPayload
     */
    private function applyDerivations(
        EmployeeAttendanceDay $day,
        WorkScheduleTemplate $template,
        array $segmentsPayload,
    ): void {
        $resolverPayload = [];
        foreach ($segmentsPayload as $seg) {
            if (! is_array($seg)) {
                continue;
            }

            $resolverPayload[] = [
                'actual_in' => $this->normalizedHmOrNull($seg['actual_in'] ?? null),
                'actual_out' => $this->normalizedHmOrNull($seg['actual_out'] ?? null),
            ];
        }

        $status = AttendanceRecordStatusResolver::fromPunchSegments($resolverPayload);
        $day->status = $status;

        $first = isset($resolverPayload[0]) && is_array($resolverPayload[0]) ? $resolverPayload[0] : null;
        if ($first !== null) {
            $firstForPunctuality = [
                'scheduled_in' => is_array($segmentsPayload[0] ?? null) && isset($segmentsPayload[0]['scheduled_in'])
                    ? AttendanceScheduleHm::normalize((string) $segmentsPayload[0]['scheduled_in'])
                    : null,
                'actual_in' => $first['actual_in'] ?? null,
            ];

            $grace = max(0, (int) $template->grace_late_arrival_minutes);
            $day->punctuality = AttendancePunctualityResolver::fromFirstSegmentClockIn(
                $firstForPunctuality,
                $grace,
            );
        } else {
            $day->punctuality = null;
        }

        if ($status === AttendanceRecordStatus::Complete) {
            $day->net_hours = AttendanceTemplateScheduledNetHours::fromTemplate($template);
        } else {
            $day->net_hours = null;
        }
    }

    /**
     * @param  list<mixed>|array<mixed>  $segmentsPayload
     */
    private function replaceSegmentsFromValidated(EmployeeAttendanceDay $day, array $segmentsPayload): void
    {
        $day->segments()->delete();

        foreach (array_values($segmentsPayload) as $index => $seg) {
            if (! is_array($seg)) {
                continue;
            }

            EmployeeAttendanceSegment::query()->create([
                'employee_attendance_day_id' => $day->id,
                'segment_index' => $index,
                'label' => isset($seg['label']) && is_string($seg['label']) ? substr($seg['label'], 0, 80) : null,
                'scheduled_in' => AttendanceScheduleHm::normalize((string) ($seg['scheduled_in'] ?? '09:00')),
                'scheduled_out' => AttendanceScheduleHm::normalize((string) ($seg['scheduled_out'] ?? '17:00')),
                'actual_in' => $this->normalizedHmOrNull($seg['actual_in'] ?? null),
                'actual_out' => $this->normalizedHmOrNull($seg['actual_out'] ?? null),
            ]);
        }

        $day->load('segments');
    }

    private function normalizedHmOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $t = trim($value);

        return $t === '' ? null : AttendanceScheduleHm::normalize($t);
    }
}
