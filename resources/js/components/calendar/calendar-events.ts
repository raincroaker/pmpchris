export type CalendarRecurrenceEnds =
    | { type: 'never' }
    | { type: 'until'; date: string }
    | { type: 'count'; count: number };

export type CalendarEventRecurrence = {
    frequency: 'daily' | 'weekly' | 'monthly' | 'yearly';
    interval: number;
    byWeekday?: number[];
    ends: CalendarRecurrenceEnds;
};

export type CalendarEventRecurrenceException =
    | { date: string; action: 'skip' }
    | {
          date: string;
          action: 'override';
          title?: string;
          startsAt?: string;
          endsAt?: string;
          location?: string;
          details?: string;
          category?: string;
          categoryId?: number | null;
          allDay?: boolean;
      };

export type CalendarEventCategory = {
    id: number;
    name: string;
    colorKey:
        | 'teal'
        | 'blue'
        | 'indigo'
        | 'violet'
        | 'fuchsia'
        | 'rose'
        | 'orange'
        | 'emerald'
        | 'slate';
};

export type CalendarEvent = {
    id: string;
    title: string;
    startsAt: string;
    endsAt: string;
    location: string;
    category: string;
    categoryId?: number | null;
    unitId?: number | null;
    details: string;
    allDay?: boolean;
    recurrence?: CalendarEventRecurrence | null;
    recurrenceExceptions?: CalendarEventRecurrenceException[] | null;
    seriesId?: string;
    setBy?: string | null;
    lastEditedBy?: string | null;
    /** Synthetic calendar rows (e.g. employee birthdays); not persisted calendar events. */
    eventKind?: 'standard' | 'birthday';
    employeeId?: number | null;
    /** Primary job title from HR profile (birthday synthetic rows only). */
    primaryPositionTitle?: string | null;
};

export const calendarEventCategoriesDummy: CalendarEventCategory[] = [
    { id: 1, name: 'Planning', colorKey: 'blue' },
    { id: 2, name: 'Meeting', colorKey: 'indigo' },
    { id: 3, name: 'Review', colorKey: 'violet' },
    { id: 4, name: 'Training', colorKey: 'fuchsia' },
    { id: 5, name: 'Deadline', colorKey: 'rose' },
    { id: 6, name: 'Announcement', colorKey: 'teal' },
    { id: 7, name: 'Operations', colorKey: 'emerald' },
    { id: 8, name: 'Compliance', colorKey: 'slate' },
    { id: 9, name: 'Incident', colorKey: 'orange' },
    { id: 10, name: 'Leave', colorKey: 'emerald' },
    { id: 11, name: 'Team Building', colorKey: 'teal' },
    { id: 12, name: 'Audit', colorKey: 'slate' },
];

function categoryIdByName(name: string): number | null {
    const row = calendarEventCategoriesDummy.find(
        (category) => category.name.toLowerCase() === name.trim().toLowerCase(),
    );

    return row?.id ?? null;
}

export const teamCalendarDummyEvents: CalendarEvent[] = [
    {
        id: 'team-1',
        title: 'Sprint Planning',
        startsAt: '2026-04-20 09:00',
        endsAt: '2026-04-20 10:30',
        location: 'Meeting Room A',
        category: 'Planning',
        categoryId: categoryIdByName('Planning'),
        details: 'Finalize priorities and assign owners for sprint backlog.',
        setBy: 'Team Lead',
        lastEditedBy: 'Scrum Master',
    },
    {
        id: 'team-2',
        title: 'Design Review',
        startsAt: '2026-04-22 14:00',
        endsAt: '2026-04-22 15:00',
        location: 'Zoom',
        category: 'Review',
        categoryId: categoryIdByName('Review'),
        details: 'Walk through wireframes and collect implementation feedback.',
        setBy: 'UX Lead',
        lastEditedBy: 'Product Manager',
    },
    {
        id: 'team-3',
        title: 'Weekly standup (Mon / Wed)',
        startsAt: '2026-04-01 09:00',
        endsAt: '2026-04-01 09:30',
        location: 'Zoom',
        category: 'Meeting',
        categoryId: categoryIdByName('Meeting'),
        details: 'Recurring standup for blockers and priorities.',
        recurrence: {
            frequency: 'weekly',
            interval: 1,
            byWeekday: [1, 3],
            ends: { type: 'until', date: '2026-06-30' },
        },
    },
    {
        id: 'team-4',
        title: 'Daily ops sync',
        startsAt: '2026-04-05 08:30',
        endsAt: '2026-04-05 09:00',
        location: 'Zoom',
        category: 'Operations',
        categoryId: categoryIdByName('Operations'),
        details: 'Daily sync for escalations and handoffs.',
        recurrence: {
            frequency: 'daily',
            interval: 1,
            ends: { type: 'count', count: 20 },
        },
    },
    {
        id: 'team-5',
        title: 'Team offsite (travel day)',
        startsAt: '2026-04-26 00:00',
        endsAt: '2026-04-26 23:59',
        location: 'Offsite venue',
        category: 'Team Building',
        categoryId: categoryIdByName('Team Building'),
        details: 'Travel and arrival; no regular standup.',
        allDay: true,
    },
];

export const companyCalendarDummyEvents: CalendarEvent[] = [
    {
        id: 'company-1',
        title: 'All Hands',
        startsAt: '2026-04-21 10:00',
        endsAt: '2026-04-21 11:30',
        location: 'Townhall Hall',
        category: 'Announcement',
        categoryId: categoryIdByName('Announcement'),
        details: 'Leadership updates, roadmap highlights, and Q&A.',
        setBy: 'People Operations',
        lastEditedBy: 'Executive Assistant',
    },
    {
        id: 'company-2',
        title: 'Office closed (spring maintenance)',
        startsAt: '2026-04-27 00:00',
        endsAt: '2026-04-28 23:59',
        location: 'All branches',
        category: 'Operations',
        categoryId: categoryIdByName('Operations'),
        details:
            'Scheduled closure; emergency contacts posted on the intranet.',
        allDay: true,
        setBy: 'Facilities',
        lastEditedBy: 'Operations Admin',
    },
    {
        id: 'company-3',
        title: 'Payroll cutoff reminder',
        startsAt: '2026-04-15 16:00',
        endsAt: '2026-04-15 16:30',
        location: 'Finance Desk',
        category: 'Deadline',
        categoryId: categoryIdByName('Deadline'),
        details: 'Monthly reminder for payroll cutoff tasks.',
        recurrence: {
            frequency: 'monthly',
            interval: 1,
            ends: { type: 'until', date: '2026-12-31' },
        },
    },
    {
        id: 'company-4',
        title: 'Compliance training (annual)',
        startsAt: '2026-04-10 08:00',
        endsAt: '2026-04-10 09:00',
        location: 'Training Lab',
        category: 'Compliance',
        categoryId: categoryIdByName('Compliance'),
        details: 'Annual refresher session.',
        recurrence: {
            frequency: 'yearly',
            interval: 1,
            ends: { type: 'never' },
        },
    },
];

export const branchCalendarDummyEvents: CalendarEvent[] = [
    {
        id: 'branch-1',
        title: 'Branch Morning Briefing',
        startsAt: '2026-04-23 08:30',
        endsAt: '2026-04-23 09:00',
        location: 'Branch Hall',
        category: 'Operations',
        categoryId: categoryIdByName('Operations'),
        details:
            'Daily priorities, staffing, and visitor notes for this location.',
        setBy: 'Branch Supervisor',
        lastEditedBy: 'Shift Lead',
    },
    {
        id: 'branch-2',
        title: 'Local Town Hall',
        startsAt: '2026-04-25 11:00',
        endsAt: '2026-04-25 12:00',
        location: 'Training Room',
        category: 'Meeting',
        categoryId: categoryIdByName('Meeting'),
        details: 'Branch-specific announcements and Q&A with site leadership.',
        setBy: 'Branch Manager',
        lastEditedBy: 'Branch Manager',
    },
    {
        id: 'branch-3',
        title: 'Safety Walkthrough',
        startsAt: '2026-04-28 14:00',
        endsAt: '2026-04-28 15:30',
        location: 'Floor 1-2',
        category: 'Incident',
        categoryId: categoryIdByName('Incident'),
        details: 'Quarterly safety inspection and documentation updates.',
    },
    {
        id: 'branch-4',
        title: 'Cross-Branch Planning Sync',
        startsAt: '2026-04-25 13:00',
        endsAt: '2026-04-25 14:00',
        location: 'Zoom',
        category: 'Planning',
        categoryId: categoryIdByName('Planning'),
        details:
            'Coordinate shared inventory and staffing plans across branches.',
    },
];
