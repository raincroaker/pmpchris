<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarClock, MoreHorizontal } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { getVisibleGridRange } from '@/components/calendar/calendar-event-recurrence';
import CalendarHolidaysSheet from '@/components/calendar/CalendarHolidaysSheet.vue';
import CalendarMonthToolbar from '@/components/calendar/CalendarMonthToolbar.vue';
import { expandHolidayOccurrencesInRange } from '@/components/calendar/holiday-rule-expansion';
import type { HolidayOccurrence } from '@/components/calendar/holiday-rule-expansion';
import type { HolidayTypeDefinition } from '@/components/calendar/holiday-types-seed';
import {
    getHolidayTypeChipStyles,
    holidayCellChipButtonBase,
    resolveHolidayType,
} from '@/components/calendar/holiday-types-seed';
import HolidayTypesSettingsDialog from '@/components/calendar/HolidayTypesSettingsDialog.vue';
import MonthCalendarShell from '@/components/calendar/MonthCalendarShell.vue';
import { useCalendarMonthView } from '@/composables/useCalendarMonthView';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { HolidayRule } from '@/pages/Attendance/attendanceRulesTypes';
import calendar from '@/routes/calendar';
import type { BreadcrumbItem } from '@/types';

const page = usePage<{
    can?: {
        canManageHolidayTypes?: boolean;
        canManageOrganizationHolidays?: boolean;
    };
}>();

const props = withDefaults(
    defineProps<{
        holidayTypes?: HolidayTypeDefinition[];
        organizationHolidays?: HolidayRule[];
        calendarDisplayMonth?: string | null;
    }>(),
    {
        holidayTypes: () => [],
        organizationHolidays: () => [],
        calendarDisplayMonth: null,
    },
);

const canManageHolidayTypes = computed(() =>
    Boolean(page.props.can?.canManageHolidayTypes),
);
const canManageOrganizationHolidays = computed(() =>
    Boolean(page.props.can?.canManageOrganizationHolidays),
);

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

const holidayTypes = ref<HolidayTypeDefinition[]>([...props.holidayTypes]);
const holidayRules = ref<HolidayRule[]>([...props.organizationHolidays]);

watch(
    () => props.holidayTypes,
    (next) => {
        holidayTypes.value = [...next];
    },
    { deep: true },
);

watch(
    () => props.organizationHolidays,
    (next) => {
        holidayRules.value = [...next];
    },
    { deep: true },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Holiday Calendar', href: calendar.holidays.url() },
];

const isHolidaySheetOpen = ref(false);
const holidaysSheetStartView = ref<'list' | 'new'>('list');
const holidaysSheetInitialDayFilter = ref<string | null>(null);
const holidaysSheetInitialHolidayId = ref<number | null>(null);
const selectedCalendarDayKey = ref<string | null>(null);

const isHolidayTypesSettingsOpen = ref(false);

const weekStartsOn = 1 as const;
const maxVisibleHolidaysPerCell = 2;

function toDayKey(day: Date): string {
    return [
        day.getFullYear(),
        String(day.getMonth() + 1).padStart(2, '0'),
        String(day.getDate()).padStart(2, '0'),
    ].join('-');
}

function monthParamFromDate(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

const displayMonthKey = computed(() => monthParamFromDate(displayDate.value));

const monthScopedOrganizationHolidays = computed<HolidayRule[]>(() => {
    if (props.calendarDisplayMonth !== displayMonthKey.value) {
        return [];
    }

    return props.organizationHolidays;
});

const visibleHolidayOccurrences = computed((): HolidayOccurrence[] => {
    const { start, end } = getVisibleGridRange(displayDate.value, weekStartsOn);
    const expanded = expandHolidayOccurrencesInRange(
        monthScopedOrganizationHolidays.value,
        start,
        end,
    );

    return expanded.filter(
        (occ) =>
            resolveHolidayType(holidayTypes.value, occ.rule.type_id) !==
            undefined,
    );
});

const holidaysByDay = computed(() => {
    const grouped: Record<string, HolidayOccurrence[]> = {};
    for (const occ of visibleHolidayOccurrences.value) {
        if (!grouped[occ.occurrenceDate]) {
            grouped[occ.occurrenceDate] = [];
        }
        grouped[occ.occurrenceDate].push(occ);
    }

    return grouped;
});

function holidayChipClasses(holiday: HolidayRule): string {
    const def = holidayTypes.value.find((t) => t.id === holiday.type_id);

    const styles = getHolidayTypeChipStyles(def?.colorKey ?? 'sky');

    return cn(holidayCellChipButtonBase, styles.chip);
}

function holidayChipIconClasses(holiday: HolidayRule): string {
    const def = holidayTypes.value.find((t) => t.id === holiday.type_id);

    const styles = getHolidayTypeChipStyles(def?.colorKey ?? 'sky');

    return styles.icon;
}

function openHolidaySheetList(dayKey: string | null): void {
    holidaysSheetStartView.value = 'list';
    holidaysSheetInitialHolidayId.value = null;
    holidaysSheetInitialDayFilter.value = dayKey;
    isHolidaySheetOpen.value = true;
}

function openHolidayToolbarList(): void {
    holidaysSheetInitialDayFilter.value = null;
    holidaysSheetInitialHolidayId.value = null;
    holidaysSheetStartView.value = 'list';
    isHolidaySheetOpen.value = true;
}

function openHolidayToolbarAdd(): void {
    holidaysSheetInitialDayFilter.value =
        selectedCalendarDayKey.value ?? toDayKey(new Date());
    holidaysSheetInitialHolidayId.value = null;
    holidaysSheetStartView.value = 'new';
    isHolidaySheetOpen.value = true;
}

function handleSelectCalendarDay(day: Date): void {
    const key = toDayKey(day);
    selectedCalendarDayKey.value = key;
    openHolidaySheetList(key);
}

function handleSelectHolidayChip(dayKey: string, holidayId: number): void {
    holidaysSheetStartView.value = 'list';
    holidaysSheetInitialHolidayId.value = holidayId;
    holidaysSheetInitialDayFilter.value = dayKey;
    isHolidaySheetOpen.value = true;
}

function reloadHolidayCalendar(nextDate: Date): void {
    router.get(
        calendar.holidays.url({
            query: { month: monthParamFromDate(nextDate) },
        }),
        {},
        {
            only: [
                'holidayTypes',
                'organizationHolidays',
                'calendarDisplayMonth',
            ],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(displayDate, (nextDate) => {
    reloadHolidayCalendar(nextDate);
});
</script>

<template>
    <Head title="Holiday Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-0 p-0">
            <CalendarMonthToolbar
                title="Holiday Calendar"
                list-button-label="Holidays"
                new-button-label="New"
                new-aria-label="New holiday"
                :display-date="displayDate"
                :picker-year="pickerYear"
                :month-year-label="monthYearLabel"
                :month-options="monthOptions"
                :show-settings="canManageHolidayTypes"
                :show-add="canManageOrganizationHolidays"
                @prev-month="shiftMonth(-1)"
                @next-month="shiftMonth(1)"
                @shift-picker-year="shiftPickerYear"
                @select-month="selectMonth"
                @events="openHolidayToolbarList"
                @add="openHolidayToolbarAdd"
                @settings="isHolidayTypesSettingsOpen = true"
            />

            <div class="min-h-0 flex-1">
                <MonthCalendarShell
                    :display-date="displayDate"
                    @select-date="handleSelectCalendarDay"
                >
                    <template #day-cell="{ day }">
                        <template v-if="holidaysByDay[toDayKey(day)]?.length">
                            <div class="relative h-full">
                                <div
                                    class="absolute inset-x-0 bottom-0 space-y-1"
                                >
                                    <button
                                        v-for="occ in holidaysByDay[
                                            toDayKey(day)
                                        ].slice(0, maxVisibleHolidaysPerCell)"
                                        :key="occ.occurrenceId"
                                        type="button"
                                        :class="holidayChipClasses(occ.rule)"
                                        :title="occ.rule.name"
                                        :aria-label="occ.rule.name"
                                        @click.stop="
                                            handleSelectHolidayChip(
                                                toDayKey(day),
                                                occ.rule.id,
                                            )
                                        "
                                    >
                                        <CalendarClock
                                            :class="
                                                cn(
                                                    'size-3 shrink-0',
                                                    holidayChipIconClasses(
                                                        occ.rule,
                                                    ),
                                                )
                                            "
                                        />
                                        <span class="min-w-0 truncate">{{
                                            occ.rule.name
                                        }}</span>
                                    </button>
                                    <div
                                        v-if="
                                            holidaysByDay[toDayKey(day)]
                                                .length >
                                            maxVisibleHolidaysPerCell
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
                                        holidaysByDay[toDayKey(day)].length >
                                        maxVisibleHolidaysPerCell
                                    "
                                    class="absolute top-2 right-2 font-sans text-sm leading-none text-muted-foreground tabular-nums"
                                >
                                    +{{
                                        holidaysByDay[toDayKey(day)].length -
                                        maxVisibleHolidaysPerCell
                                    }}
                                </p>
                            </div>
                        </template>
                    </template>
                </MonthCalendarShell>
            </div>

            <HolidayTypesSettingsDialog
                v-model:open="isHolidayTypesSettingsOpen"
                v-model:types="holidayTypes"
                :can-manage="canManageHolidayTypes"
                :calendar-display-month="displayMonthKey"
            />

            <CalendarHolidaysSheet
                v-model:open="isHolidaySheetOpen"
                v-model:holidays="holidayRules"
                title="Holiday Calendar"
                :types="holidayTypes"
                :can-manage="canManageOrganizationHolidays"
                :calendar-display-month="displayMonthKey"
                list-endpoint="/calendar/organization-holidays"
                :start-view="holidaysSheetStartView"
                :initial-day-filter="holidaysSheetInitialDayFilter"
                :initial-holiday-id="holidaysSheetInitialHolidayId"
                default-range-filter="month"
            />
        </div>
    </AppLayout>
</template>
