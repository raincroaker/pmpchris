<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarDays,
    ClipboardList,
    Users,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';
import HrisKpiCard from '@/components/hris/HrisKpiCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { employeeSchedules as attendanceEmployeeSchedules } from '@/routes/attendance';
import { dashboard } from '@/routes';
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
            attendance_status_label: '—',
            upcoming_events_count: 0,
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

const todayFocus = computed(() => props.todayFocus);
const upcomingEvents = computed(() => props.upcomingEvents);

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
            title: 'Schedules',
            value: 'Open',
            hint: 'Employee schedules',
            href: attendanceEmployeeSchedules(),
            icon: ClipboardList,
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
                            <Link :href="attendanceEmployeeSchedules()">
                                <ClipboardList
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <span class="ml-1">Employee Schedules</span>
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
                            Next on your calendar
                        </p>
                    </div>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-1">
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

            <div class="grid min-h-0 gap-4 lg:grid-cols-1">
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
            </div>
        </div>
    </AppLayout>
</template>
