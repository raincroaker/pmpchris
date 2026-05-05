<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarClock, MoreHorizontal } from 'lucide-vue-next';
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
import { company } from '@/routes/calendar';
import type { BreadcrumbItem } from '@/types';

const page = usePage<{
    can?: {
        canManageCalendarCategories?: boolean;
        canManageCalendarEventActions?: boolean;
        canCreateCompanyCalendarEvent?: boolean;
    };
}>();

const props = withDefaults(
    defineProps<{
        calendarCategories?: CalendarEventCategory[];
        companyEvents?: CalendarEvent[];
        calendarDisplayMonth?: string | null;
    }>(),
    {
        calendarCategories: () => [],
        companyEvents: () => [],
        calendarDisplayMonth: null,
    },
);

const canManageCalendarCategories = computed(() =>
    Boolean(page.props.can?.canManageCalendarCategories),
);
const canManageCalendarEventActions = computed(() =>
    Boolean(page.props.can?.canManageCalendarEventActions),
);
const canCreateCompanyCalendarEvent = computed(() =>
    Boolean(page.props.can?.canCreateCompanyCalendarEvent),
);
const calendarCategories = ref<CalendarEventCategory[]>([
    ...props.calendarCategories,
]);

function handleCategoriesUpdated(categories: CalendarEventCategory[]): void {
    calendarCategories.value = [...categories];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Company Calendar',
        href: company(),
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
const optimisticCompanyEvents = ref<CalendarEvent[]>([]);

const maxVisibleEventsPerCell = 2;

const weekStartsOn = 1 as const;
const displayMonthKey = computed(() => monthParamFromDate(displayDate.value));
const monthScopedCompanyEvents = computed<CalendarEvent[]>(() => {
    if (props.calendarDisplayMonth !== displayMonthKey.value) {
        return [];
    }

    return props.companyEvents;
});

const visibleCompanyEvents = computed(() => {
    const { start, end } = getVisibleGridRange(displayDate.value, weekStartsOn);

    return expandEventsForRange(
        [...monthScopedCompanyEvents.value, ...optimisticCompanyEvents.value],
        start,
        end,
        weekStartsOn,
    );
});

const companyEventsByDay = computed(() => {
    const grouped: Record<string, CalendarEvent[]> = {};

    for (const event of visibleCompanyEvents.value) {
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
    return (
        calendarCategories.value.find(
            (category) => category.id === event.categoryId,
        )?.colorKey ?? null
    );
}

function monthParamFromDate(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function reloadCompanyEvents(nextDate: Date): void {
    optimisticCompanyEvents.value = [];
    router.get(
        company.url(),
        {
            month: monthParamFromDate(nextDate),
        },
        {
            only: ['companyEvents', 'calendarDisplayMonth'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onOptimisticCreated(event: CalendarEvent): void {
    optimisticCompanyEvents.value = [...optimisticCompanyEvents.value, event];
}

function onOptimisticReverted(eventId: string): void {
    optimisticCompanyEvents.value = optimisticCompanyEvents.value.filter(
        (event) => event.id !== eventId,
    );
}

watch(displayDate, (nextDate) => {
    reloadCompanyEvents(nextDate);
});
</script>

<template>
    <Head title="Company Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-0 p-0">
            <CalendarMonthToolbar
                title="Company Calendar"
                :display-date="displayDate"
                :picker-year="pickerYear"
                :month-year-label="monthYearLabel"
                :month-options="monthOptions"
                :show-settings="true"
                :show-add="canCreateCompanyCalendarEvent"
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
                            v-if="companyEventsByDay[toDayKey(day)]?.length"
                        >
                            <div class="relative h-full">
                                <div
                                    class="absolute inset-x-0 bottom-0 space-y-1"
                                >
                                    <button
                                        v-for="event in companyEventsByDay[
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
                                        <CalendarClock
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
                                            companyEventsByDay[toDayKey(day)]
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
                                        companyEventsByDay[toDayKey(day)]
                                            .length > maxVisibleEventsPerCell
                                    "
                                    class="absolute top-2 right-2 font-sans text-sm leading-none text-muted-foreground tabular-nums"
                                >
                                    +{{
                                        companyEventsByDay[toDayKey(day)]
                                            .length - maxVisibleEventsPerCell
                                    }}
                                </p>
                            </div>
                        </template>
                    </template>
                </MonthCalendarShell>
            </div>

            <CalendarCategoriesSettingsDialog
                v-model:open="isCategorySettingsOpen"
                :can-manage="canManageCalendarCategories"
                :categories="calendarCategories"
                @categories-updated="handleCategoriesUpdated"
            />

            <CalendarEventsSheet
                v-model:open="isEventsSheetOpen"
                title="Company Events"
                description="Browse company events from this panel."
                :events="visibleCompanyEvents"
                :categories="calendarCategories"
                :can-manage-events="canManageCalendarEventActions"
                :can-create-event="canCreateCompanyCalendarEvent"
                create-endpoint="/calendar/company/events"
                update-endpoint-base="/calendar/company/events"
                delete-endpoint-base="/calendar/company/events"
                list-endpoint="/calendar/company/events"
                :list-query="{ month: monthParamFromDate(displayDate) }"
                :start-view="eventsSheetStartView"
                :initial-day-filter="selectedDayFilter"
                :initial-event-id="selectedEventId"
                @events-mutated="reloadCompanyEvents(displayDate)"
                @optimistic-created="onOptimisticCreated"
                @optimistic-reverted="onOptimisticReverted"
            />
        </div>
    </AppLayout>
</template>
