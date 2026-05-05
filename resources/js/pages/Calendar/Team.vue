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
import HrisUnitSelectTriggerLabel from '@/components/hris/HrisUnitSelectTriggerLabel.vue';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCalendarMonthView } from '@/composables/useCalendarMonthView';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { team } from '@/routes/calendar';
import type { BreadcrumbItem } from '@/types';

type TeamCalendarUnitOption = {
    id: number;
    code: string;
    name: string;
    canCreateEvent: boolean;
    isOrganizationScope?: boolean;
};

const props = withDefaults(
    defineProps<{
        calendarCategories?: CalendarEventCategory[];
        teamEvents?: CalendarEvent[];
        teamBirthdays?: CalendarEvent[];
        teamCalendarUnits?: TeamCalendarUnitOption[];
        selectedTeamUnitId?: number | null;
        calendarDisplayMonth?: string | null;
    }>(),
    {
        calendarCategories: () => [],
        teamEvents: () => [],
        teamBirthdays: () => [],
        teamCalendarUnits: () => [],
        selectedTeamUnitId: null,
        calendarDisplayMonth: null,
    },
);

const page = usePage<{
    can?: {
        canManageCalendarCategories?: boolean;
        canManageCalendarEventActions?: boolean;
        canCreateTeamCalendarEvent?: boolean;
    };
}>();

const canManageCalendarCategories = computed(() =>
    Boolean(page.props.can?.canManageCalendarCategories),
);
const canManageCalendarEventActions = computed(() =>
    Boolean(page.props.can?.canManageCalendarEventActions),
);
const calendarCategories = ref<CalendarEventCategory[]>([
    ...props.calendarCategories,
]);
const selectedTeamUnitModel = ref(
    props.selectedTeamUnitId ? String(props.selectedTeamUnitId) : '',
);

const selectedTeamUnit = computed(() => {
    const id = Number(selectedTeamUnitModel.value);
    if (!Number.isFinite(id)) {
        return null;
    }

    return props.teamCalendarUnits.find((unit) => unit.id === id) ?? null;
});

const canCreateTeamCalendarEvent = computed(
    () => selectedTeamUnit.value?.canCreateEvent ?? false,
);
const organizationScopeOptions = computed(() =>
    props.teamCalendarUnits.filter((unit) => unit.isOrganizationScope),
);
const unitScopeOptions = computed(() =>
    props.teamCalendarUnits.filter((unit) => !unit.isOrganizationScope),
);

const teamCalendarUnitSelectOptions = computed(() =>
    props.teamCalendarUnits.map((unit) => ({
        value: String(unit.id),
        label: unit.name,
        code: unit.code,
    })),
);

function handleCategoriesUpdated(categories: CalendarEventCategory[]): void {
    calendarCategories.value = [...categories];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Team Calendar',
        href: team(),
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
const optimisticTeamEvents = ref<CalendarEvent[]>([]);

const maxVisibleEventsPerCell = 2;

const weekStartsOn = 1 as const;
const displayMonthKey = computed(() => monthParamFromDate(displayDate.value));
const scopeMonthScopedTeamEvents = computed<CalendarEvent[]>(() => {
    const serverMonthMatches =
        props.calendarDisplayMonth === displayMonthKey.value;
    const serverUnitId =
        props.selectedTeamUnitId != null
            ? String(props.selectedTeamUnitId)
            : '';
    const selectedUnitMatches = serverUnitId === selectedTeamUnitModel.value;
    if (!serverMonthMatches || !selectedUnitMatches) {
        return [];
    }

    return [...props.teamEvents, ...props.teamBirthdays];
});

const visibleTeamEvents = computed(() => {
    const { start, end } = getVisibleGridRange(displayDate.value, weekStartsOn);

    const expanded = expandEventsForRange(
        [...scopeMonthScopedTeamEvents.value, ...optimisticTeamEvents.value],
        start,
        end,
        weekStartsOn,
    );

    const selectedUnitId = Number(selectedTeamUnitModel.value);
    if (!Number.isFinite(selectedUnitId)) {
        return expanded;
    }

    return expanded.filter((event) => {
        if (event.eventKind === 'birthday') {
            return true;
        }

        const eventUnitId = (
            event as CalendarEvent & { unitId?: number | null }
        ).unitId;
        if (eventUnitId == null) {
            return selectedTeamUnit.value?.isOrganizationScope === true;
        }

        return eventUnitId === selectedUnitId;
    });
});

const teamEventsByDay = computed(() => {
    const grouped: Record<string, CalendarEvent[]> = {};

    for (const event of visibleTeamEvents.value) {
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

function reloadTeamEventsWindow(): void {
    if (selectedTeamUnitModel.value === '') {
        return;
    }

    optimisticTeamEvents.value = [];
    router.get(
        team.url(),
        {
            month: monthParamFromDate(displayDate.value),
            unit_id: selectedTeamUnitModel.value,
        },
        {
            only: [
                'teamEvents',
                'teamBirthdays',
                'selectedTeamUnitId',
                'calendarDisplayMonth',
            ],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onOptimisticCreated(event: CalendarEvent): void {
    optimisticTeamEvents.value = [...optimisticTeamEvents.value, event];
}

function onOptimisticReverted(eventId: string): void {
    optimisticTeamEvents.value = optimisticTeamEvents.value.filter(
        (event) => event.id !== eventId,
    );
}

watch(
    () => props.selectedTeamUnitId,
    (nextSelectedTeamUnitId) => {
        selectedTeamUnitModel.value =
            nextSelectedTeamUnitId != null
                ? String(nextSelectedTeamUnitId)
                : '';
    },
);

watch(displayDate, () => {
    reloadTeamEventsWindow();
});

watch(selectedTeamUnitModel, () => {
    reloadTeamEventsWindow();
});
</script>

<template>
    <Head title="Team Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-0 p-0">
            <CalendarMonthToolbar
                title="Team Calendar"
                :display-date="displayDate"
                :picker-year="pickerYear"
                :month-year-label="monthYearLabel"
                :month-options="monthOptions"
                :show-settings="true"
                :show-add="canCreateTeamCalendarEvent"
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
            >
                <template #pre-actions>
                    <Select
                        v-model="selectedTeamUnitModel"
                        :disabled="props.teamCalendarUnits.length === 0"
                    >
                        <SelectTrigger
                            class="w-[240px] cursor-pointer rounded-md"
                            aria-label="Team unit calendar"
                        >
                            <SelectValue placeholder="Select team unit">
                                <template #default="{ modelValue }">
                                    <HrisUnitSelectTriggerLabel
                                        :select-model-value="modelValue"
                                        :options="teamCalendarUnitSelectOptions"
                                        fallback-label="Select team unit"
                                    />
                                </template>
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent class="max-h-80 w-[320px]">
                            <SelectGroup
                                v-if="organizationScopeOptions.length > 0"
                            >
                                <SelectLabel>Organization</SelectLabel>
                                <SelectItem
                                    v-for="unit in organizationScopeOptions"
                                    :key="unit.id"
                                    :value="String(unit.id)"
                                    class="cursor-pointer"
                                >
                                    <div
                                        class="flex min-w-0 items-baseline gap-1"
                                    >
                                        <span class="truncate text-sm">{{
                                            unit.name
                                        }}</span>
                                        <span
                                            v-if="unit.code"
                                            class="shrink-0 font-mono text-xs text-muted-foreground"
                                            >{{ unit.code }}</span
                                        >
                                    </div>
                                </SelectItem>
                            </SelectGroup>
                            <SelectGroup v-if="unitScopeOptions.length > 0">
                                <SelectLabel>Units</SelectLabel>
                                <SelectItem
                                    v-for="unit in unitScopeOptions"
                                    :key="unit.id"
                                    :value="String(unit.id)"
                                    class="cursor-pointer"
                                >
                                    <div
                                        class="flex min-w-0 items-baseline gap-1"
                                    >
                                        <span class="truncate text-sm">{{
                                            unit.name
                                        }}</span>
                                        <span
                                            v-if="unit.code"
                                            class="shrink-0 font-mono text-xs text-muted-foreground"
                                            >{{ unit.code }}</span
                                        >
                                    </div>
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </template>
            </CalendarMonthToolbar>

            <div class="min-h-0 flex-1">
                <MonthCalendarShell
                    :display-date="displayDate"
                    @select-date="handleSelectCalendarDay"
                >
                    <template #day-cell="{ day }">
                        <template v-if="teamEventsByDay[toDayKey(day)]?.length">
                            <div class="relative h-full">
                                <div
                                    class="absolute inset-x-0 bottom-0 space-y-1"
                                >
                                    <button
                                        v-for="event in teamEventsByDay[
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
                                            teamEventsByDay[toDayKey(day)]
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
                                        teamEventsByDay[toDayKey(day)].length >
                                        maxVisibleEventsPerCell
                                    "
                                    class="absolute top-2 right-2 font-sans text-sm leading-none text-muted-foreground tabular-nums"
                                >
                                    +{{
                                        teamEventsByDay[toDayKey(day)].length -
                                        maxVisibleEventsPerCell
                                    }}
                                </p>
                            </div>
                        </template>
                    </template>
                </MonthCalendarShell>
            </div>

            <CalendarEventsSheet
                v-model:open="isEventsSheetOpen"
                title="Team Events"
                description="Browse team events from this panel."
                :events="visibleTeamEvents"
                :categories="calendarCategories"
                :can-manage-events="canManageCalendarEventActions"
                :can-create-event="canCreateTeamCalendarEvent"
                create-endpoint="/calendar/team/events"
                update-endpoint-base="/calendar/team/events"
                delete-endpoint-base="/calendar/team/events"
                list-endpoint="/calendar/team/events"
                :list-query="{
                    month: monthParamFromDate(displayDate),
                    unit_id: selectedTeamUnitModel,
                }"
                :team-unit-id="
                    selectedTeamUnit?.isOrganizationScope
                        ? null
                        : (selectedTeamUnit?.id ?? null)
                "
                :start-view="eventsSheetStartView"
                :initial-day-filter="selectedDayFilter"
                :initial-event-id="selectedEventId"
                @events-mutated="reloadTeamEventsWindow"
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
