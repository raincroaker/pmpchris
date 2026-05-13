<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\EmployeeLeave;
use App\Models\EmployeeOvertime;
use App\Models\User;

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

        $orgId = (int) $organization->id;

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
                'attendance_status' => 'no_record',
                'clock_in' => '—',
                'clock_out' => '—',
                'next_event' => $upcomingEvents[0]['title'] ?? 'No upcoming event',
            ],
            'kpis' => [
                'leave_upcoming_count' => $leaveUpcomingCount,
                'overtime_upcoming_count' => $overtimeUpcomingCount,
                'attendance_status_label' => '—',
                'upcoming_events_count' => (int) ($eventsPage['data'] ? count($eventsPage['data']) : 0),
            ],
            'upcomingEvents' => $upcomingEvents,
            'meta' => [
                'updated_at' => now()->format('M j, Y g:i A'),
            ],
        ];
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
                'attendance_status_label' => '—',
                'upcoming_events_count' => 0,
            ],
            'upcomingEvents' => [],
            'meta' => [
                'updated_at' => now()->format('M j, Y g:i A'),
            ],
        ];
    }
}
