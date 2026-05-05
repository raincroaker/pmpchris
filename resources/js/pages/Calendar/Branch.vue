<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Cake, CalendarClock, MoreHorizontal } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    expandEventsForRange,
    getVisibleGridRange,
} from '@/components/calendar/calendar-event-recurrence';
import type { CalendarEvent } from '@/components/calendar/calendar-events';
import type { CalendarEventCategory } from '@/components/calendar/calendar-events';
import CalendarCategoriesSettingsDialog from '@/components/calendar/CalendarCategoriesSettingsDialog.vue';
import {
    calendarEventCellChipButtonBase,
    getCalendarEventCategoryChipStylesByColorKey,
} from '@/components/calendar/calendarEventCategoryChip';
import CalendarEventsSheet from '@/components/calendar/CalendarEventsSheet.vue';
import CalendarMonthToolbar from '@/components/calendar/CalendarMonthToolbar.vue';
import MonthCalendarShell from '@/components/calendar/MonthCalendarShell.vue';
import { useCalendarMonthView } from '@/composables/useCalendarMonthView';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { branch } from '@/routes/calendar';
import type { BreadcrumbItem } from '@/types';

const page = usePage<{
    can?: {
        canManageCalendarCategories?: boolean;
        canManageCalendarEventActions?: boolean;
        canCreateBranchCalendarEvent?: boolean;
    };
}>();

const props = withDefaults(
    defineProps<{
        calendarCategories?: CalendarEventCategory[];
        branchEvents?: CalendarEvent[];
        branchBirthdays?: CalendarEvent[];
        calendarDisplayMonth?: string | null;
    }>(),
    {
        calendarCategories: () => [],
        branchEvents: () => [],
        branchBirthdays: () => [],
        calendarDisplayMonth: null,
    },
);

const canManageCalendarCategories = computed(() =>
    Boolean(page.props.can?.canManageCalendarCategories),
);
const canManageCalendarEventActions = computed(() =>
    Boolean(page.props.can?.canManageCalendarEventActions),
);
const canCreateBranchCalendarEvent = computed(() =>
    Boolean(page.props.can?.canCreateBranchCalendarEvent),
);
const calendarCategories = ref<CalendarEventCategory[]>([
    ...props.calendarCategories,
]);

function handleCategoriesUpdated(categories: CalendarEventCategory[]): void {
    calendarCategories.value = [...categories];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Branch Calendar',
        href: branch(),
    },
];

function parseDisplayMonth(value: string | null | undefined): Date | undefined {
    if (!value || !/^\d{4}-\d{2}$/.test(value)) {
        return undefined;
    }

    const [year, month] = value
        .split('-')
        .map((part) => Number.parseInt(part, 10));
    if (!Number.isFinite(year) || !Number.isFinite(month)) {
        return undefined;
    }

    return new Date(year, month - 1, 1);
}

const {
    displayDate,
    pickerYear,
    monthOptions,
    monthYearLabel,
    shiftMonth,
    shiftPickerYear,
    selectMonth,
} = useCalendarMonthView(parseDisplayMonth(props.calendarDisplayMonth));

const isEventsSheetOpen = ref(false);
const eventsSheetStartView = ref<'list' | 'new'>('list');
const selectedDayFilter = ref<string | null>(null);
const selectedEventId = ref<string | null>(null);
const isCategorySettingsOpen = ref(false);
const optimisticBranchEvents = ref<CalendarEvent[]>([]);

const maxVisibleEventsPerCell = 2;

const weekStartsOn = 1 as const;
const displayMonthKey = computed(() => monthParamFromDate(displayDate.value));
const monthScopedBranchEvents = computed<CalendarEvent[]>(() => {
    if (props.calendarDisplayMonth !== displayMonthKey.value) {
        return [];
    }

    return [...props.branchEvents, ...props.branchBirthdays];
});

const visibleBranchEvents = computed(() => {
    const { start, end } = getVisibleGridRange(displayDate.value, weekStartsOn);

    return expandEventsForRange(
        [...monthScopedBranchEvents.value, ...optimisticBranchEvents.value],
        start,
        end,
        weekStartsOn,
    );
});

const branchEventsByDay = computed(() => {
    const grouped: Record<string, CalendarEvent[]> = {};

    for (const event of visibleBranchEvents.value) {
        const dayKey = event.startsAt.split(' ')[0];
        if (!grouped[dayKey]) {
            grouped[dayKey] = [];
        }
        grouped[dayKey].push(event);
    }

    return grouped;
});

function toDayKey(day: Date): string {
    return [
        day.getFullYear(),
        String(day.getMonth() + 1).padStart(2, '0'),
        String(day.getDate()).padStart(2, '0'),
    ].join('-');
}

function handleSelectCalendarDay(day: Date): void {
    selectedDayFilter.value = toDayKey(day);
    selectedEventId.value = null;
    eventsSheetStartView.value = 'list';
    isEventsSheetOpen.value = true;
}

function handleSelectCalendarEvent(eventId: string): void {
    selectedDayFilter.value = null;
    selectedEventId.value = eventId;
    eventsSheetStartView.value = 'list';
    isEventsSheetOpen.value = true;
}

function categoryColorKeyByEvent(event: CalendarEvent): string | null {
    if (event.eventKind === 'birthday') {
        return 'fuchsia';
    }

    return (
        calendarCategories.value.find(
            (category) => category.id === event.categoryId,
        )?.colorKey ?? null
    );
}

function calendarCellIconComponent(event: CalendarEvent) {
    return event.eventKind === 'birthday' ? Cake : CalendarClock;
}

function monthParamFromDate(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function reloadBranchEvents(nextDate: Date): void {
    optimisticBranchEvents.value = [];
    router.get(
        branch.url(),
        {
            month: monthParamFromDate(nextDate),
        },
        {
            only: ['branchEvents', 'branchBirthdays', 'calendarDisplayMonth'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onOptimisticCreated(event: CalendarEvent): void {
    optimisticBranchEvents.value = [...optimisticBranchEvents.value, event];
}

function onOptimisticReverted(eventId: string): void {
    optimisticBranchEvents.value = optimisticBranchEvents.value.filter(
        (event) => event.id !== eventId,
    );
}

watch(displayDate, (nextDate) => {
    reloadBranchEvents(nextDate);
});
</script>

<template>
    <Head title="Branch Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-0 p-0">
            <CalendarMonthToolbar
                title="Branch Calendar"
                :display-date="displayDate"
                :picker-year="pickerYear"
                :month-year-label="monthYearLabel"
                :month-options="monthOptions"
                :show-settings="true"
                :show-add="canCreateBranchCalendarEvent"
                @prev-month="shiftMonth(-1)"
                @next-month="shiftMonth(1)"
                @shift-picker-year="shiftPickerYear"
                @select-month="selectMonth"
                @events="
                    eventsSheetStartView = 'list';
                    selectedDayFilter = null;
                    selectedEventId = null;
                    isEventsSheetOpen = true;
                "
                @add="
                    eventsSheetStartView = 'new';
                    selectedDayFilter = null;
                    selectedEventId = null;
                    isEventsSheetOpen = true;
                "
                @settings="isCategorySettingsOpen = true"
            />

            <div class="min-h-0 flex-1">
                <MonthCalendarShell
                    :display-date="displayDate"
                    @select-date="handleSelectCalendarDay"
                >
                    <template #day-cell="{ day }">
                        <template
                            v-if="branchEventsByDay[toDayKey(day)]?.length"
                        >
                            <div class="relative h-full">
                                <div
                                    class="absolute inset-x-0 bottom-0 space-y-1"
                                >
                                    <button
                                        v-for="event in branchEventsByDay[
                                            toDayKey(day)
                                        ].slice(0, maxVisibleEventsPerCell)"
                                        :key="event.id"
                                        type="button"
                                        :class="
                                            cn(
                                                calendarEventCellChipButtonBase,
                                                getCalendarEventCategoryChipStylesByColorKey(
                                                    categoryColorKeyByEvent(
                                                        event,
                                                    ),
                                                ).chip,
                                            )
                                        "
                                        :title="event.title"
                                        :aria-label="event.title"
                                        @click.stop="
                                            handleSelectCalendarEvent(
                                                String(event.id),
                                            )
                                        "
                                    >
                                        <component
                                            :is="
                                                calendarCellIconComponent(event)
                                            "
                                            :class="
                                                cn(
                                                    'size-3 shrink-0',
                                                    getCalendarEventCategoryChipStylesByColorKey(
                                                        categoryColorKeyByEvent(
                                                            event,
                                                        ),
                                                    ).icon,
                                                )
                                            "
                                            aria-hidden="true"
                                        />
                                        <span class="min-w-0 truncate">{{
                                            event.title
                                        }}</span>
                                    </button>
                                    <div
                                        v-if="
                                            branchEventsByDay[toDayKey(day)]
                                                .length >
                                            maxVisibleEventsPerCell
                                        "
                                        class="-mt-1 -mb-1.75 flex justify-center py-0 leading-none"
                                        aria-hidden="true"
                                    >
                                        <MoreHorizontal
                                            class="block size-3.5 text-muted-foreground"
                                        />
                                    </div>
                                </div>
                                <p
                                    v-if="
                                        branchEventsByDay[toDayKey(day)]
                                            .length > maxVisibleEventsPerCell
                                    "
                                    class="absolute top-2 right-2 font-sans text-sm leading-none text-muted-foreground tabular-nums"
                                >
                                    +{{
                                        branchEventsByDay[toDayKey(day)]
                                            .length - maxVisibleEventsPerCell
                                    }}
                                </p>
                            </div>
                        </template>
                    </template>
                </MonthCalendarShell>
            </div>

            <CalendarEventsSheet
                v-model:open="isEventsSheetOpen"
                title="Branch Events"
                description="Browse branch events for this location from this panel."
                :events="visibleBranchEvents"
                :categories="calendarCategories"
                :can-manage-events="canManageCalendarEventActions"
                :can-create-event="canCreateBranchCalendarEvent"
                create-endpoint="/calendar/branch/events"
                update-endpoint-base="/calendar/branch/events"
                delete-endpoint-base="/calendar/branch/events"
                list-endpoint="/calendar/branch/events"
                :list-query="{ month: monthParamFromDate(displayDate) }"
                :start-view="eventsSheetStartView"
                :initial-day-filter="selectedDayFilter"
                :initial-event-id="selectedEventId"
                @events-mutated="reloadBranchEvents(displayDate)"
                @optimistic-created="onOptimisticCreated"
                @optimistic-reverted="onOptimisticReverted"
            />
            <CalendarCategoriesSettingsDialog
                v-model:open="isCategorySettingsOpen"
                :can-manage="canManageCalendarCategories"
                :categories="calendarCategories"
                @categories-updated="handleCategoriesUpdated"
            />
        </div>
    </AppLayout>
</template>
