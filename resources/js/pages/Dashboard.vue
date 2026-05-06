<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Hourglass,
    MessageSquareText,
    Timer,
    Users,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';
import HrisKpiCard from '@/components/hris/HrisKpiCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TeamAttendanceRow } from '@/pages/Attendance/teamAttendanceTypes';
import {
    clockInOutDisplay,
    attendanceStatusBadgeClass,
    attendanceStatusLabel,
    punctualityBadgeClass,
    punctualityLabel,
} from '@/pages/Attendance/teamAttendanceUi';
import { chat } from '@/routes';
import { dashboard } from '@/routes';
import { my as attendanceMy } from '@/routes/attendance';
import {
    company as calendarCompany,
    team as calendarTeam,
} from '@/routes/calendar';
import { my as leaveMy } from '@/routes/leave';
import { my as overtimeMy } from '@/routes/overtime';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const page = usePage<{
    auth: { user: { name?: string } | null };
    branchContext: { id: number; code: string; name: string } | null;
}>();

const props = withDefaults(
    defineProps<{
        todayFocus?: {
            attendance_status: string;
            clock_in: string;
            clock_out: string;
            next_event: string;
        };
        kpis?: {
            leave_upcoming_count: number;
            overtime_upcoming_count: number;
            attendance_status_label: string;
            upcoming_events_count: number;
        };
        attendance?: {
            rows: DashboardAttendanceRow[];
            stats: {
                total: number;
                complete: number;
                ongoing: number;
                incomplete: number;
                on_time: number;
                late: number;
                total_net_hours: number;
            };
        };
        upcomingEvents?: Array<{
            id: string;
            title: string;
            subtitle: string;
        }>;
        meta?: {
            updated_at: string;
        };
    }>(),
    {
        todayFocus: () => ({
            attendance_status: 'no_record',
            clock_in: '—',
            clock_out: '—',
            next_event: 'No upcoming event',
        }),
        kpis: () => ({
            leave_upcoming_count: 0,
            overtime_upcoming_count: 0,
            attendance_status_label: 'No record',
            upcoming_events_count: 0,
        }),
        attendance: () => ({
            rows: [],
            stats: {
                total: 0,
                complete: 0,
                ongoing: 0,
                incomplete: 0,
                on_time: 0,
                late: 0,
                total_net_hours: 0,
            },
        }),
        upcomingEvents: () => [],
        meta: () => ({
            updated_at: '',
        }),
    },
);

const displayName = computed(() => page.props.auth?.user?.name ?? '—');
const branchName = computed(() => page.props.branchContext?.name ?? '—');
const branchCode = computed(() => page.props.branchContext?.code ?? '—');

type MockChatRoom = {
    id: string;
    title: string;
    subtitle: string;
    unreadCount?: number;
};

const mockChatRooms: MockChatRoom[] = [
    {
        id: 'c-1',
        title: '—',
        subtitle: 'Loading chats…',
        unreadCount: undefined,
    },
    {
        id: 'c-2',
        title: '—',
        subtitle: 'Loading chats…',
        unreadCount: undefined,
    },
    {
        id: 'c-3',
        title: '—',
        subtitle: 'Loading chats…',
        unreadCount: undefined,
    },
];

type DashboardAttendanceRow = TeamAttendanceRow;

const todayFocus = computed(() => props.todayFocus);
const upcomingEvents = computed(() => props.upcomingEvents);

const attendanceThisMonth = computed(() => props.attendance.rows);
const attendanceMonthStats = computed(() => ({
    ...props.attendance.stats,
    onTime: props.attendance.stats.on_time,
    totalNetHours: props.attendance.stats.total_net_hours,
}));

const todayFocusStatusClass = computed(() => {
    const status = todayFocus.value.attendance_status;
    if (status === 'complete') {
        return 'text-emerald-700 dark:text-emerald-300';
    }
    if (status === 'ongoing') {
        return 'text-amber-800 dark:text-amber-300';
    }
    if (status === 'incomplete') {
        return 'text-rose-700 dark:text-rose-300';
    }

    return 'text-muted-foreground';
});

const kpiCards = computed(
    (): Array<{
        title: string;
        value: string;
        hint: string;
        href: NonNullable<InertiaLinkProps['href']>;
        icon: Component;
        tone: 'amber' | 'violet' | 'emerald' | 'sky';
    }> => [
        {
            title: 'Upcoming leaves',
            value: String(props.kpis.leave_upcoming_count),
            hint: 'From today · filtered',
            href: leaveMy(),
            icon: CalendarDays,
            tone: 'amber',
        },
        {
            title: 'Upcoming overtime',
            value: String(props.kpis.overtime_upcoming_count),
            hint: 'From today · filtered',
            href: overtimeMy(),
            icon: CalendarClock,
            tone: 'violet',
        },
        {
            title: 'Today attendance',
            value: props.kpis.attendance_status_label,
            hint: 'Clock-in / clock-out',
            href: attendanceMy(),
            icon: Timer,
            tone: 'emerald',
        },
        {
            title: 'Upcoming events',
            value: String(props.kpis.upcoming_events_count),
            hint: 'Next 7 days',
            href: calendarCompany(),
            icon: Users,
            tone: 'sky',
        },
    ],
);

const attendanceStatCards = computed(
    (): Array<{
        key: string;
        label: string;
        value: string;
        icon: Component;
        tone: 'neutral' | 'emerald' | 'amber' | 'rose' | 'violet' | 'sky';
    }> => [
        {
            key: 'rows',
            label: 'Rows',
            value: String(attendanceMonthStats.value.total),
            icon: Users,
            tone: 'neutral',
        },
        {
            key: 'complete',
            label: 'Complete',
            value: String(attendanceMonthStats.value.complete),
            icon: CheckCircle2,
            tone: 'emerald',
        },
        {
            key: 'ongoing',
            label: 'Ongoing',
            value: String(attendanceMonthStats.value.ongoing),
            icon: Clock3,
            tone: 'amber',
        },
        {
            key: 'incomplete',
            label: 'Incomplete',
            value: String(attendanceMonthStats.value.incomplete),
            icon: AlertTriangle,
            tone: 'rose',
        },
        {
            key: 'punctuality',
            label: 'On-time / Late',
            value: `${attendanceMonthStats.value.onTime} / ${attendanceMonthStats.value.late}`,
            icon: Timer,
            tone: 'violet',
        },
        {
            key: 'hours',
            label: 'Net hours',
            value: attendanceMonthStats.value.totalNetHours.toFixed(2),
            icon: Hourglass,
            tone: 'sky',
        },
    ],
);

function openDashboardTarget(
    href: NonNullable<InertiaLinkProps['href']>,
): void {
    router.visit(href);
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4 lg:px-16">
            <div class="rounded-xl border border-border/70 bg-card p-4">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0">
                        <h1 class="text-xl font-semibold text-foreground">
                            Welcome,
                            <span class="tabular-nums">{{ displayName }}</span>
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Workspace branch:
                            <span class="font-medium text-foreground">{{
                                branchName
                            }}</span>
                            <span
                                class="font-mono text-xs text-muted-foreground"
                            >
                                ({{ branchCode }})
                            </span>
                        </p>
                        <p
                            class="mt-1 max-w-3xl text-sm leading-relaxed text-muted-foreground"
                        >
                            Overview of your month-to-date activity and quick
                            actions.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline" class="h-9">
                            <Link :href="attendanceMy()">
                                <Timer class="size-4" aria-hidden="true" />
                                <span class="ml-1">My Attendance</span>
                            </Link>
                        </Button>
                        <Button as-child variant="outline" class="h-9">
                            <Link :href="calendarTeam()">
                                <CalendarDays
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <span class="ml-1">Team Calendar</span>
                            </Link>
                        </Button>
                        <Button as-child variant="outline" class="h-9">
                            <Link :href="chat()">
                                <MessageSquareText
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <span class="ml-1">Chats</span>
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <HrisKpiCard
                    v-for="card in kpiCards"
                    :key="card.title"
                    :title="card.title"
                    :value="card.value"
                    :hint="card.hint"
                    :tone="card.tone"
                    :icon="card.icon"
                    clickable
                    @select="openDashboardTarget(card.href)"
                />
            </div>

            <section
                class="min-h-64 overflow-hidden rounded-xl border border-border/70 bg-card lg:min-h-50"
            >
                <div
                    class="flex flex-col gap-2 border-b border-border/60 bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-foreground">
                            Today focus
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Quick snapshot for today
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Attendance:
                        <span
                            class="font-medium"
                            :class="todayFocusStatusClass"
                        >
                            {{ todayFocus.attendance_status }}
                        </span>
                    </p>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-3">
                    <div
                        class="rounded-lg border border-border/70 bg-muted/20 p-3"
                    >
                        <p class="text-xs text-muted-foreground">Clock in</p>
                        <p
                            class="mt-1 font-mono text-base text-foreground tabular-nums"
                        >
                            {{ todayFocus.clock_in }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-border/70 bg-muted/20 p-3"
                    >
                        <p class="text-xs text-muted-foreground">Clock out</p>
                        <p
                            class="mt-1 font-mono text-base text-foreground tabular-nums"
                        >
                            {{ todayFocus.clock_out }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-border/70 bg-muted/20 p-3"
                    >
                        <p class="text-xs text-muted-foreground">Next event</p>
                        <p
                            class="mt-1 truncate text-sm font-medium text-foreground"
                        >
                            {{ todayFocus.next_event }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="min-h-120 overflow-hidden rounded-xl border border-border/70 bg-card lg:min-h-144"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-border/60 bg-muted/30 p-4"
                >
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-foreground">
                            Attendance Overview
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            This month clock-ins and status mix
                        </p>
                    </div>
                    <Button as-child variant="outline" size="sm" class="h-8">
                        <Link :href="attendanceMy()">Open attendance</Link>
                    </Button>
                </div>

                <div
                    class="grid gap-3 border-b border-border/60 p-4 sm:grid-cols-2 lg:grid-cols-6"
                >
                    <HrisKpiCard
                        v-for="stat in attendanceStatCards"
                        :key="stat.key"
                        :title="stat.label"
                        :value="stat.value"
                        hint="This month"
                        :tone="stat.tone"
                        :icon="stat.icon"
                    />
                </div>

                <div class="overflow-x-auto p-4">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-border/60 text-left text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                <th class="py-2 pr-4">Work date</th>
                                <th class="py-2 pr-4">Attendance ID</th>
                                <th class="py-2 pr-4">Time (clock in / out)</th>
                                <th class="py-2 pr-4">Net hours</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-0">Punctuality</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in attendanceThisMonth"
                                :key="row.id"
                                class="border-b border-border/40 last:border-0"
                            >
                                <td
                                    class="py-2 pr-4 font-mono text-foreground tabular-nums"
                                >
                                    {{ row.work_date }}
                                </td>
                                <td
                                    class="py-2 pr-4 font-mono text-xs text-muted-foreground"
                                >
                                    {{ row.attendance_id ?? '—' }}
                                </td>
                                <td
                                    class="py-2 pr-4 font-mono whitespace-pre-line text-foreground tabular-nums"
                                >
                                    {{
                                        clockInOutDisplay(
                                            row.clock_pattern,
                                            row.segments,
                                        )
                                    }}
                                </td>
                                <td
                                    class="py-2 pr-4 text-foreground tabular-nums"
                                >
                                    {{ row.net_hours.toFixed(2) }}
                                </td>
                                <td class="py-2 pr-4">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            attendanceStatusBadgeClass(
                                                row.status,
                                            )
                                        "
                                    >
                                        {{ attendanceStatusLabel(row.status) }}
                                    </span>
                                </td>
                                <td class="py-2 pr-0">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            punctualityBadgeClass(
                                                row.punctuality,
                                            )
                                        "
                                    >
                                        {{ punctualityLabel(row.punctuality) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid min-h-0 gap-4 lg:grid-cols-2">
                <section
                    class="min-h-88 overflow-hidden rounded-xl border border-border/70 bg-card lg:min-h-104"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-border/60 bg-muted/30 p-4"
                    >
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-foreground">
                                Upcoming events
                            </h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Next 7 days
                            </p>
                        </div>
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="h-8"
                        >
                            <Link :href="calendarCompany()">View calendar</Link>
                        </Button>
                    </div>

                    <ul
                        v-if="upcomingEvents.length > 0"
                        class="max-h-64 divide-y divide-border/60 overflow-y-auto lg:max-h-80"
                    >
                        <li
                            v-for="event in upcomingEvents"
                            :key="event.id"
                            class="flex items-start gap-3 p-4 transition-colors hover:bg-muted/40"
                        >
                            <div
                                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground"
                            >
                                <CalendarDays
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-foreground"
                                >
                                    {{ event.title }}
                                </p>
                                <p
                                    class="mt-1 truncate text-xs text-muted-foreground"
                                >
                                    {{ event.subtitle }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="p-4 text-sm text-muted-foreground">
                        No upcoming events in this window.
                    </div>
                </section>

                <section
                    class="min-h-88 overflow-hidden rounded-xl border border-border/70 bg-card lg:min-h-104"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-border/60 bg-muted/30 p-4"
                    >
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-foreground">
                                Chats
                            </h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Pinned / recent rooms · placeholder list
                            </p>
                        </div>
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="h-8"
                        >
                            <Link :href="chat()">Open chats</Link>
                        </Button>
                    </div>

                    <ul
                        class="max-h-64 divide-y divide-border/60 overflow-y-auto lg:max-h-80"
                    >
                        <li
                            v-for="room in mockChatRooms"
                            :key="room.id"
                            class="flex items-center gap-3 p-4 transition-colors hover:bg-muted/40"
                        >
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-foreground"
                            >
                                <MessageSquareText
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span
                                        class="h-4 w-32 animate-pulse rounded bg-muted/60"
                                    ></span>
                                    <span
                                        class="h-5 w-8 animate-pulse rounded-full bg-muted/60"
                                    ></span>
                                </div>
                                <p
                                    class="mt-2 h-4 w-4/5 animate-pulse rounded bg-muted/60"
                                ></p>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
