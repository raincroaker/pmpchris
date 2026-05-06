<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeLeave;
use App\Models\EmployeeOvertime;
use App\Models\User;
use App\Support\TeamAttendanceRecordPresenter;
use App\Support\TeamHrEmployeeDirectoryExtras;

class DashboardOverviewService
{
    public function __construct(
        private BranchContextService $branchContextService,
        private CalendarViewDataService $calendarViewDataService,
    ) {}

    /**
     * @return array{
     *     todayFocus: array{
     *         attendance_status: string,
     *         clock_in: string,
     *         clock_out: string,
     *         next_event: string,
     *     },
     *     kpis: array{
     *         leave_upcoming_count: int,
     *         overtime_upcoming_count: int,
     *         attendance_status_label: string,
     *         upcoming_events_count: int,
     *     },
     *     attendance: array{
     *         rows: list<array<string, mixed>>,
     *         stats: array{
     *             total: int,
     *             complete: int,
     *             ongoing: int,
     *             incomplete: int,
     *             on_time: int,
     *             late: int,
     *             total_net_hours: float,
     *         }
     *     },
     *     upcomingEvents: list<array{
     *         id: string,
     *         title: string,
     *         subtitle: string,
     *     }>,
     *     meta: array{updated_at: string},
     * }
     */
    public function buildForUser(?User $user): array
    {
        $today = now()->toDateString();
        $organization = $this->branchContextService->defaultOrganization();
        $employeeId = $user?->employee_id !== null ? (int) $user->employee_id : null;

        if ($organization === null || $employeeId === null) {
            return $this->emptyPayload();
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $orgId = (int) $organization->id;

        $baseAttendanceQuery = EmployeeAttendanceDay::query()
            ->where('organization_id', '=', $orgId)
            ->where('employee_id', '=', $employeeId)
            ->whereBetween('work_date', [$monthStart, $monthEnd], 'and', false);

        $attendanceRows = (clone $baseAttendanceQuery)
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForAttendancePresenters($today),
                'employee.user:id,employee_id,avatar_path',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'segments',
                'workScheduleTemplate' => static fn ($q) => $q->withTrashed(),
                'createdByUser' => static fn ($q) => $q->with('employee'),
                'updatedByUser' => static fn ($q) => $q->with('employee'),
            ])
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn (EmployeeAttendanceDay $day): array => TeamAttendanceRecordPresenter::toPageRow($day))
            ->values()
            ->all();

        $attendanceStats = [
            'total' => (clone $baseAttendanceQuery)->count('*'),
            'complete' => (clone $baseAttendanceQuery)->where('status', '=', 'complete')->count('*'),
            'ongoing' => (clone $baseAttendanceQuery)->where('status', '=', 'ongoing')->count('*'),
            'incomplete' => (clone $baseAttendanceQuery)->where('status', '=', 'incomplete')->count('*'),
            'on_time' => (clone $baseAttendanceQuery)->where('punctuality', '=', 'on_time')->count('*'),
            'late' => (clone $baseAttendanceQuery)->where('punctuality', '=', 'late')->count('*'),
            'total_net_hours' => (float) ((clone $baseAttendanceQuery)->sum('net_hours') ?? 0),
        ];

        $todayAttendance = EmployeeAttendanceDay::query()
            ->where('organization_id', '=', $orgId)
            ->where('employee_id', '=', $employeeId)
            ->whereDate('work_date', '=', $today, 'and')
            ->with('segments')
            ->first();

        $leaveUpcomingCount = EmployeeLeave::query()
            ->where('organization_id', '=', $orgId)
            ->where('employee_id', '=', $employeeId)
            ->where('status', '=', EmployeeHrRecordStatus::Approved)
            ->whereDate('start_date', '>=', $today, 'and')
            ->count('*');

        $overtimeUpcomingCount = EmployeeOvertime::query()
            ->where('organization_id', '=', $orgId)
            ->where('employee_id', '=', $employeeId)
            ->where('status', '=', EmployeeHrRecordStatus::Approved)
            ->whereDate('ot_date', '>=', $today, 'and')
            ->count('*');

        $eventsPage = $this->calendarViewDataService->companyEventsPageForOrganization(
            $orgId,
            now()->startOfMonth(),
            1,
            5,
            ['range' => 'week'],
        );

        $upcomingEvents = collect($eventsPage['data'] ?? [])
            ->take(3)
            ->map(static function (array $event): array {
                $title = (string) ($event['title'] ?? 'Untitled event');
                $startsAt = (string) ($event['startsAt'] ?? '');
                $category = (string) ($event['category'] ?? '');
                $location = (string) ($event['location'] ?? '');
                $subtitle = trim(implode(' · ', array_filter([$startsAt, $category, $location])));

                return [
                    'id' => (string) ($event['id'] ?? uniqid('event-', true)),
                    'title' => $title,
                    'subtitle' => $subtitle !== '' ? $subtitle : 'Upcoming event',
                ];
            })
            ->values()
            ->all();

        return [
            'todayFocus' => [
                'attendance_status' => $todayAttendance?->status?->value ?? 'no_record',
                'clock_in' => $this->firstClockIn($todayAttendance),
                'clock_out' => $this->lastClockOut($todayAttendance),
                'next_event' => $upcomingEvents[0]['title'] ?? 'No upcoming event',
            ],
            'kpis' => [
                'leave_upcoming_count' => $leaveUpcomingCount,
                'overtime_upcoming_count' => $overtimeUpcomingCount,
                'attendance_status_label' => $this->todayAttendanceLabel($todayAttendance),
                'upcoming_events_count' => (int) ($eventsPage['data'] ? count($eventsPage['data']) : 0),
            ],
            'attendance' => [
                'rows' => $attendanceRows,
                'stats' => $attendanceStats,
            ],
            'upcomingEvents' => $upcomingEvents,
            'meta' => [
                'updated_at' => now()->format('M j, Y g:i A'),
            ],
        ];
    }

    private function todayAttendanceLabel(?EmployeeAttendanceDay $day): string
    {
        if ($day === null) {
            return 'No record';
        }

        return match ($day->status->value) {
            'complete' => 'Complete',
            'ongoing' => 'Ongoing',
            'incomplete' => 'Incomplete',
            default => 'No record',
        };
    }

    private function firstClockIn(?EmployeeAttendanceDay $day): string
    {
        if (! $day instanceof EmployeeAttendanceDay) {
            return '—';
        }

        $first = $day->segments->sortBy('segment_index')->first();

        return $first?->actual_in !== null && $first->actual_in !== ''
            ? (string) $first->actual_in
            : '—';
    }

    private function lastClockOut(?EmployeeAttendanceDay $day): string
    {
        if (! $day instanceof EmployeeAttendanceDay) {
            return '—';
        }

        $last = $day->segments->sortBy('segment_index')->last();

        return $last?->actual_out !== null && $last->actual_out !== ''
            ? (string) $last->actual_out
            : '—';
    }

    /**
     * @return array{
     *     todayFocus: array{
     *         attendance_status: string,
     *         clock_in: string,
     *         clock_out: string,
     *         next_event: string,
     *     },
     *     kpis: array{
     *         leave_upcoming_count: int,
     *         overtime_upcoming_count: int,
     *         attendance_status_label: string,
     *         upcoming_events_count: int,
     *     },
     *     attendance: array{
     *         rows: list<array<string, mixed>>,
     *         stats: array{
     *             total: int,
     *             complete: int,
     *             ongoing: int,
     *             incomplete: int,
     *             on_time: int,
     *             late: int,
     *             total_net_hours: float,
     *         }
     *     },
     *     upcomingEvents: list<array{
     *         id: string,
     *         title: string,
     *         subtitle: string,
     *     }>,
     *     meta: array{updated_at: string},
     * }
     */
    private function emptyPayload(): array
    {
        return [
            'todayFocus' => [
                'attendance_status' => 'no_record',
                'clock_in' => '—',
                'clock_out' => '—',
                'next_event' => 'No upcoming event',
            ],
            'kpis' => [
                'leave_upcoming_count' => 0,
                'overtime_upcoming_count' => 0,
                'attendance_status_label' => 'No record',
                'upcoming_events_count' => 0,
            ],
            'attendance' => [
                'rows' => [],
                'stats' => [
                    'total' => 0,
                    'complete' => 0,
                    'ongoing' => 0,
                    'incomplete' => 0,
                    'on_time' => 0,
                    'late' => 0,
                    'total_net_hours' => 0.0,
                ],
            ],
            'upcomingEvents' => [],
            'meta' => [
                'updated_at' => now()->format('M j, Y g:i A'),
            ],
        ];
    }
}
