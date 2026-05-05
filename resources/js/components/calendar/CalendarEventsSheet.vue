<script setup lang="ts">
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import {
    Briefcase,
    Cake,
    Calendar,
    CalendarPlus,
    CircleHelp,
    Check,
    ChevronDownIcon,
    Clock3,
    Filter,
    MapPin,
    Pencil,
    Repeat,
    Save,
    Search,
    Tag,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
    formatRecurrenceSummary,
    parseEventDateString,
} from '@/components/calendar/calendar-event-recurrence';
import type {
    CalendarEventCategory,
    CalendarEvent,
    CalendarEventRecurrence,
    CalendarRecurrenceEnds,
} from '@/components/calendar/calendar-events';
import { getCalendarEventCategoryChipStylesByColorKey } from '@/components/calendar/calendarEventCategoryChip';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar as DatePickerCalendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { appToast } from '@/lib/app-toast-client';
import { cn } from '@/lib/utils';

type CalendarSheetView = 'list' | 'detail' | 'new' | 'edit';
type EventRangeFilter = 'today' | 'week' | 'month' | 'year' | 'custom';
type RecurrenceApplyScope = 'entire_series' | 'single_occurrence';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        events: CalendarEvent[];
        categories: CalendarEventCategory[];
        canManageEvents?: boolean;
        canCreateEvent?: boolean;
        createEndpoint?: string;
        updateEndpointBase?: string;
        deleteEndpointBase?: string;
        listEndpoint?: string;
        listQuery?: Record<
            string,
            string | number | boolean | null | undefined
        >;
        teamUnitId?: number | null;
        defaultRangeFilter?: EventRangeFilter;
        startView?: 'list' | 'new';
        initialDayFilter?: string | null;
        initialEventId?: string | null;
    }>(),
    {
        startView: 'list',
        canManageEvents: false,
        canCreateEvent: false,
        createEndpoint: '',
        updateEndpointBase: '',
        deleteEndpointBase: '',
        listEndpoint: '',
        listQuery: () => ({}),
        teamUnitId: null,
        defaultRangeFilter: 'month',
        initialDayFilter: null,
        initialEventId: null,
    },
);

const emit = defineEmits<{
    (event: 'events-mutated'): void;
    (event: 'optimistic-created', value: CalendarEvent): void;
    (event: 'optimistic-reverted', value: string): void;
}>();

const open = defineModel<boolean>('open', { default: false });
const view = ref<CalendarSheetView>('list');
const activeEvent = ref<CalendarEvent | null>(null);
const isDeleteConfirmOpen = ref(false);
const deletedEventRootIds = ref<Set<string>>(new Set());
const searchQuery = ref('');
const rangeFilter = ref<EventRangeFilter>(normalizedDefaultRangeFilter());
const isCustomRangeOpen = ref(false);
const isCategoryFilterOpen = ref(false);
const customFrom = ref<DateValue | undefined>(undefined);
const customTo = ref<DateValue | undefined>(undefined);
const selectedCategoryIds = ref<number[]>([]);
const formStartDate = ref<DateValue | undefined>(undefined);
const formEndDate = ref<DateValue | undefined>(undefined);
const isStartTimeOpen = ref(false);
const isEndTimeOpen = ref(false);
const isSubmitting = ref(false);
const isDeleting = ref(false);
const deleteApplyScope = ref<RecurrenceApplyScope>('entire_series');
const restoredOccurrenceDates = ref<Set<string>>(new Set());
const listEvents = ref<CalendarEvent[]>([]);
const listPage = ref(1);
const listHasMore = ref(false);
const isListLoading = ref(false);
const listBottomSentinel = ref<HTMLElement | null>(null);
const listIntersectionObserver = ref<IntersectionObserver | null>(null);

const optionalLabelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';
const clearFieldButtonClass =
    'absolute right-0 top-1/2 z-[1] size-6 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground';

const form = ref({
    title: '',
    startTime: '',
    endTime: '',
    location: '',
    categoryId: null as number | null,
    notes: '',
    isAllDay: false,
});

type FormRecurrenceFrequency =
    | 'none'
    | 'daily'
    | 'weekly'
    | 'monthly'
    | 'yearly';

const formRecurrence = ref({
    frequency: 'none' as FormRecurrenceFrequency,
    interval: 1,
    byWeekday: [] as number[],
    endsType: 'never' as 'never' | 'until' | 'count',
    untilDate: undefined as DateValue | undefined,
    occurrenceCount: 10,
});

const recurrenceWeekdayOptions: { label: string; value: number }[] = [
    { label: 'Mo', value: 1 },
    { label: 'Tu', value: 2 },
    { label: 'We', value: 3 },
    { label: 'Th', value: 4 },
    { label: 'Fr', value: 5 },
    { label: 'Sa', value: 6 },
    { label: 'Su', value: 0 },
];

const showRecurrenceSection = computed(() => {
    if (
        form.value.isAllDay &&
        formEndDate.value &&
        formStartDate.value &&
        formEndDate.value.compare(formStartDate.value as DateValue) !== 0
    ) {
        return false;
    }

    return true;
});

function getEventRootId(event: CalendarEvent): string {
    const eventId = String(event.id);

    if (event.seriesId) {
        return String(event.seriesId);
    }

    const sep = eventId.lastIndexOf('::');
    if (sep !== -1) {
        return eventId.slice(0, sep);
    }

    return eventId;
}

const sourceEvents = computed(() =>
    props.events.filter(
        (event) => !deletedEventRootIds.value.has(getEventRootId(event)),
    ),
);
/** Server list endpoints omit synthetic rows (e.g. birthdays); merge them from {@link sourceEvents}. */
const syntheticEventsFromProps = computed(() =>
    sourceEvents.value.filter((event) => event.eventKind === 'birthday'),
);
const listSourceEvents = computed(() => {
    const deleted = deletedEventRootIds.value;
    const fromApi = listEvents.value.filter(
        (event) => !deleted.has(getEventRootId(event)),
    );
    const synthetics = syntheticEventsFromProps.value.filter(
        (event) => !deleted.has(getEventRootId(event)),
    );
    const byId = new Map<string, CalendarEvent>();
    for (const event of fromApi) {
        byId.set(event.id, event);
    }
    for (const event of synthetics) {
        if (!byId.has(event.id)) {
            byId.set(event.id, event);
        }
    }

    return Array.from(byId.values()).sort((a, b) => {
        const ta = parseEventDateString(a.startsAt)?.getTime() ?? 0;
        const tb = parseEventDateString(b.startsAt)?.getTime() ?? 0;
        if (ta !== tb) {
            return ta - tb;
        }

        return String(a.id).localeCompare(String(b.id));
    });
});
const usePaginatedListEvents = computed(
    () => view.value === 'list' && props.listEndpoint.trim() !== '',
);
const eventsForListFiltering = computed(() =>
    usePaginatedListEvents.value ? listSourceEvents.value : sourceEvents.value,
);
const listQueryKey = computed(() => JSON.stringify(props.listQuery ?? {}));
const listLocalFilterKey = computed(() =>
    JSON.stringify({
        search: searchQuery.value.trim(),
        categories: [...selectedCategoryIds.value].sort((a, b) => a - b),
        range: rangeFilter.value,
        customFrom: parsedCustomFrom.value
            ? toDateValue(parsedCustomFrom.value)
            : null,
        customTo: parsedCustomTo.value
            ? toDateValue(parsedCustomTo.value)
            : null,
    }),
);
const activeEventOccurrenceDate = computed(() => {
    if (!activeEvent.value) {
        return null;
    }

    const id = String(activeEvent.value.id);
    if (id.includes('::')) {
        const suffix = id.split('::')[1] ?? '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(suffix)) {
            return suffix;
        }
    }

    const startsAt = activeEvent.value.startsAt;
    const day = startsAt.split(' ')[0] ?? '';
    return /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : null;
});
const isActiveRecurringOccurrence = computed(() =>
    Boolean(activeEvent.value?.seriesId && activeEventOccurrenceDate.value),
);
const skippedOccurrenceDates = computed(() => {
    const exceptions = activeEvent.value?.recurrenceExceptions;
    if (!Array.isArray(exceptions)) {
        return [] as string[];
    }

    return exceptions
        .filter(
            (exception) =>
                exception.action === 'skip' &&
                /^\d{4}-\d{2}-\d{2}$/.test(exception.date),
        )
        .map((exception) => exception.date)
        .sort((a, b) => a.localeCompare(b));
});
const skippedOccurrencesPendingRestore = computed(() =>
    skippedOccurrenceDates.value.filter(
        (day) => !restoredOccurrenceDates.value.has(day),
    ),
);

const deleteDialogDescription = computed(() => {
    if (!activeEvent.value) {
        return 'This action cannot be undone.';
    }

    if (
        isActiveRecurringOccurrence.value &&
        deleteApplyScope.value === 'single_occurrence'
    ) {
        return 'This will delete only this occurrence. Other occurrences in the series will remain.';
    }

    if (activeEvent.value.recurrence || activeEvent.value.seriesId) {
        return 'This will delete the entire series and all of its occurrences. This action cannot be undone.';
    }

    return 'This will delete this event. This action cannot be undone.';
});

function resetFormRecurrence(): void {
    formRecurrence.value = {
        frequency: 'none',
        interval: 1,
        byWeekday: [],
        endsType: 'never',
        untilDate: undefined,
        occurrenceCount: 10,
    };
}

function toggleRestoredOccurrenceDate(dayKey: string): void {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dayKey)) {
        return;
    }

    const next = new Set(restoredOccurrenceDates.value);
    if (next.has(dayKey)) {
        next.delete(dayKey);
    } else {
        next.add(dayKey);
    }
    restoredOccurrenceDates.value = next;
}

function loadRecurrenceIntoForm(
    r: CalendarEventRecurrence | null | undefined,
): void {
    if (!r) {
        resetFormRecurrence();
        return;
    }

    formRecurrence.value.frequency = r.frequency;
    formRecurrence.value.interval = Math.max(1, r.interval || 1);
    formRecurrence.value.byWeekday = r.byWeekday ? [...r.byWeekday] : [];

    if (r.ends.type === 'never') {
        formRecurrence.value.endsType = 'never';
        formRecurrence.value.untilDate = undefined;
    } else if (r.ends.type === 'until') {
        formRecurrence.value.endsType = 'until';
        formRecurrence.value.untilDate = parseDayKey(r.ends.date);
    } else {
        formRecurrence.value.endsType = 'count';
        formRecurrence.value.occurrenceCount = Math.max(1, r.ends.count);
    }
}

function toggleWeekday(day: number): void {
    const set = new Set(formRecurrence.value.byWeekday);
    if (set.has(day)) {
        set.delete(day);
    } else {
        set.add(day);
    }

    formRecurrence.value.byWeekday = [...set].sort((a, b) => a - b);
}

function buildRecurrenceFromForm(): CalendarEventRecurrence | null {
    if (formRecurrence.value.frequency === 'none') {
        return null;
    }

    const interval = Math.max(1, formRecurrence.value.interval || 1);
    let ends: CalendarRecurrenceEnds;

    if (formRecurrence.value.endsType === 'never') {
        ends = { type: 'never' };
    } else if (formRecurrence.value.endsType === 'until') {
        const until = dateValueToDate(formRecurrence.value.untilDate);
        if (!until) {
            ends = { type: 'never' };
        } else {
            const y = until.getFullYear();
            const m = String(until.getMonth() + 1).padStart(2, '0');
            const d = String(until.getDate()).padStart(2, '0');
            ends = { type: 'until', date: `${y}-${m}-${d}` };
        }
    } else {
        ends = {
            type: 'count',
            count: Math.max(1, formRecurrence.value.occurrenceCount),
        };
    }

    const freq = formRecurrence.value.frequency;
    const base: CalendarEventRecurrence = {
        frequency: freq,
        interval,
        ends,
    };

    if (freq === 'weekly') {
        base.byWeekday = [...new Set(formRecurrence.value.byWeekday)]
            .filter((day) => Number.isInteger(day) && day >= 0 && day <= 6)
            .sort((a, b) => a - b);
    }

    return base;
}

function getFormRecurrenceValidationErrors(): string[] {
    const errors: string[] = [];

    if (!showRecurrenceSection.value) {
        return errors;
    }

    if (formRecurrence.value.frequency === 'none') {
        return errors;
    }

    if (
        !Number.isFinite(formRecurrence.value.interval) ||
        formRecurrence.value.interval < 1
    ) {
        errors.push('Interval must be 1 or greater.');
    }

    if (formRecurrence.value.frequency === 'weekly') {
        const days = formRecurrence.value.byWeekday;
        if (days.length === 0) {
            errors.push('Weekly recurrence requires at least one weekday.');
        }

        const invalidDay = days.find(
            (day) => !Number.isInteger(day) || day < 0 || day > 6,
        );
        if (invalidDay !== undefined) {
            errors.push('Weekly recurrence has an invalid weekday selection.');
        }

        if (new Set(days).size !== days.length) {
            errors.push('Weekly recurrence contains duplicate weekdays.');
        }
    } else if (formRecurrence.value.byWeekday.length > 0) {
        errors.push('Weekday selections apply only to weekly recurrence.');
    }

    if (formRecurrence.value.endsType === 'until') {
        if (!formRecurrence.value.untilDate) {
            errors.push('End date is required when recurrence ends on a date.');
            return errors;
        }

        const until = dateValueToDate(formRecurrence.value.untilDate);
        const start = dateValueToDate(formStartDate.value);
        if (!until || !start) {
            errors.push(
                'End date must be valid and not before the start date.',
            );
            return errors;
        }

        if (startOfDay(until).getTime() < startOfDay(start).getTime()) {
            errors.push('End date cannot be before the event start date.');
        }
    }

    if (formRecurrence.value.endsType === 'count') {
        if (
            !Number.isFinite(formRecurrence.value.occurrenceCount) ||
            formRecurrence.value.occurrenceCount < 1
        ) {
            errors.push('Occurrence count must be 1 or greater.');
        }
    }

    return errors;
}

const recurrenceValidationErrors = computed(() =>
    getFormRecurrenceValidationErrors(),
);

const recurrenceValidationHints = computed(() => {
    const hints: string[] = [];
    if (
        !showRecurrenceSection.value ||
        formRecurrence.value.frequency === 'none'
    ) {
        return hints;
    }

    if (formRecurrence.value.frequency === 'monthly') {
        const start = dateValueToDate(formStartDate.value);
        if (start && start.getDate() >= 29) {
            hints.push(
                'Monthly repeats on the 29th, 30th, or 31st may skip shorter months.',
            );
        }
    }

    return hints;
});

function isFormRecurrenceInputValid(): boolean {
    return recurrenceValidationErrors.value.length === 0;
}

function resolveEventFromProps(eventId: string): CalendarEvent | null {
    const direct =
        sourceEvents.value.find((event) => event.id === eventId) ?? null;
    if (direct) {
        return direct;
    }

    const lastSep = eventId.lastIndexOf('::');
    if (lastSep === -1) {
        return null;
    }

    const dayKey = eventId.slice(lastSep + 2);
    const baseId = eventId.slice(0, lastSep);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(dayKey)) {
        return null;
    }

    return (
        sourceEvents.value.find(
            (e) =>
                e.id === eventId ||
                (e.seriesId === baseId && e.startsAt.startsWith(dayKey)),
        ) ?? null
    );
}

function listRequestQuery(page: number): URLSearchParams {
    const params = new URLSearchParams();
    const source = props.listQuery ?? {};
    for (const [key, value] of Object.entries(source)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }

        params.set(key, String(value));
    }

    params.set('page', String(page));
    params.set('per_page', '40');
    params.set('search', searchQuery.value.trim());
    if (selectedCategoryIds.value.length > 0) {
        params.set('category_ids', selectedCategoryIds.value.join(','));
    }
    params.set('range', rangeFilter.value);
    if (rangeFilter.value === 'custom') {
        if (parsedCustomFrom.value) {
            params.set('custom_from', toDateValue(parsedCustomFrom.value));
        }

        if (parsedCustomTo.value) {
            params.set('custom_to', toDateValue(parsedCustomTo.value));
        }
    }

    return params;
}

async function fetchListPage(page: number): Promise<void> {
    if (props.listEndpoint.trim() === '' || isListLoading.value) {
        return;
    }

    isListLoading.value = true;
    try {
        const url = `${props.listEndpoint}?${listRequestQuery(page).toString()}`;
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });
        if (!response.ok) {
            return;
        }

        const payload = await response.json().catch(() => ({}));
        const rows = Array.isArray((payload as { data?: unknown }).data)
            ? (payload as { data: CalendarEvent[] }).data
            : [];
        const hasMore = Boolean(
            (payload as { meta?: { hasMore?: boolean } }).meta?.hasMore,
        );
        const nextPage = (payload as { meta?: { nextPage?: number | null } })
            .meta?.nextPage;

        if (page === 1) {
            listEvents.value = [...rows];
        } else {
            const merged = [...listEvents.value, ...rows];
            const byId = new Map<string, CalendarEvent>();
            for (const event of merged) {
                byId.set(event.id, event);
            }

            listEvents.value = Array.from(byId.values());
        }

        listHasMore.value = hasMore;
        listPage.value =
            nextPage && Number.isFinite(nextPage) ? Number(nextPage) : page + 1;
    } finally {
        isListLoading.value = false;
    }
}

async function resetAndFetchFirstListPage(): Promise<void> {
    if (props.listEndpoint.trim() === '' || view.value !== 'list') {
        return;
    }

    listEvents.value = [];
    listPage.value = 1;
    listHasMore.value = false;
    await fetchListPage(1);
}

async function loadNextListPageIfNeeded(): Promise<void> {
    if (
        !usePaginatedListEvents.value ||
        isListLoading.value ||
        !listHasMore.value ||
        view.value !== 'list'
    ) {
        return;
    }

    await fetchListPage(listPage.value);
}

function disconnectListObserver(): void {
    listIntersectionObserver.value?.disconnect();
    listIntersectionObserver.value = null;
}

function setupListObserver(): void {
    disconnectListObserver();
    if (!listBottomSentinel.value || !usePaginatedListEvents.value) {
        return;
    }

    listIntersectionObserver.value = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                void loadNextListPageIfNeeded();
            }
        },
        {
            root: null,
            rootMargin: '0px 0px 160px 0px',
            threshold: 0,
        },
    );
    listIntersectionObserver.value.observe(listBottomSentinel.value);
}

watch(
    () => form.value.isAllDay,
    (isAllDay) => {
        isStartTimeOpen.value = false;
        isEndTimeOpen.value = false;

        if (isAllDay && formStartDate.value && !formEndDate.value) {
            formEndDate.value = formStartDate.value;
        }
    },
);

watch(showRecurrenceSection, (visible) => {
    if (!visible) {
        resetFormRecurrence();
    }
});

watch(
    () => [formRecurrence.value.frequency, formStartDate.value] as const,
    () => {
        if (formRecurrence.value.frequency !== 'weekly') {
            return;
        }

        if (formRecurrence.value.byWeekday.length > 0) {
            return;
        }

        const d = dateValueToDate(formStartDate.value);
        if (!d) {
            return;
        }

        formRecurrence.value.byWeekday = [d.getDay()];
    },
);

watch(
    () => formRecurrence.value.byWeekday,
    (days) => {
        const normalized = [...new Set(days)]
            .filter((day) => Number.isInteger(day) && day >= 0 && day <= 6)
            .sort((a, b) => a - b);

        if (
            normalized.length !== days.length ||
            normalized.some((day, i) => day !== days[i])
        ) {
            formRecurrence.value.byWeekday = normalized;
        }
    },
    { deep: true },
);

watch(
    () => form.value.startTime,
    (t) => {
        if (!t?.trim()) {
            isEndTimeOpen.value = false;
        }
    },
);

const headerTitle = computed(() => {
    if (view.value === 'detail') {
        return 'Event details';
    }
    if (view.value === 'new') {
        return `New ${props.title.replace('Events', 'Event')}`;
    }
    if (view.value === 'edit') {
        return `Edit ${activeEvent.value?.title ?? 'Event'}`;
    }

    return props.title;
});

const headerDescription = computed(() => {
    if (view.value === 'detail' && activeEvent.value) {
        return 'Review the schedule and event information.';
    }
    if (view.value === 'new') {
        return 'Create a new event using this starter form.';
    }
    if (view.value === 'edit') {
        return 'Update event details.';
    }

    return props.description ?? 'Browse and open event details.';
});

const parsedCustomFrom = computed(() => dateValueToDate(customFrom.value));
const parsedCustomTo = computed(() => dateValueToDate(customTo.value));

const fromDisplayLabel = computed(() => {
    if (!parsedCustomFrom.value) {
        return 'Select start date';
    }

    return formatFullDate(parsedCustomFrom.value);
});

const toDisplayLabel = computed(() => {
    if (!parsedCustomTo.value) {
        return 'Select end date';
    }

    return formatFullDate(parsedCustomTo.value);
});

const dateBounds = computed(() => {
    const today = new Date();
    const startOfToday = startOfDay(today);
    const endOfToday = new Date(
        startOfToday.getFullYear(),
        startOfToday.getMonth(),
        startOfToday.getDate() + 1,
    );
    const mondayOffset = (startOfToday.getDay() + 6) % 7;
    const startOfWeek = new Date(
        startOfToday.getFullYear(),
        startOfToday.getMonth(),
        startOfToday.getDate() - mondayOffset,
    );
    const endOfWeekExclusive = new Date(
        startOfWeek.getFullYear(),
        startOfWeek.getMonth(),
        startOfWeek.getDate() + 7,
    );
    const startOfMonth = new Date(
        startOfToday.getFullYear(),
        startOfToday.getMonth(),
        1,
    );
    const endOfMonthExclusive = new Date(
        startOfToday.getFullYear(),
        startOfToday.getMonth() + 1,
        1,
    );
    const startOfYear = new Date(startOfToday.getFullYear(), 0, 1);
    const endOfYearExclusive = new Date(startOfToday.getFullYear() + 1, 0, 1);

    return {
        startOfToday,
        endOfToday,
        startOfWeek,
        endOfWeekExclusive,
        startOfMonth,
        endOfMonthExclusive,
        startOfYear,
        endOfYearExclusive,
    };
});

const isCustomRangeValid = computed(() => {
    if (parsedCustomFrom.value === null) {
        return false;
    }

    if (parsedCustomTo.value === null) {
        return true;
    }

    return parsedCustomFrom.value < parsedCustomTo.value;
});

const dateAnchorLabel = computed(() => {
    if (rangeFilter.value === 'today') {
        return formatFullDate(dateBounds.value.startOfToday);
    }
    if (rangeFilter.value === 'week') {
        return formatRange(
            dateBounds.value.startOfWeek,
            dateBounds.value.endOfWeekExclusive,
        );
    }
    if (rangeFilter.value === 'month') {
        return formatRange(
            dateBounds.value.startOfMonth,
            dateBounds.value.endOfMonthExclusive,
        );
    }
    if (rangeFilter.value === 'year') {
        return formatRange(
            dateBounds.value.startOfYear,
            dateBounds.value.endOfYearExclusive,
        );
    }

    if (parsedCustomFrom.value && parsedCustomTo.value) {
        return `${formatShortDate(parsedCustomFrom.value)} - ${formatShortDate(parsedCustomTo.value)}`;
    }

    if (parsedCustomFrom.value) {
        return formatFullDate(parsedCustomFrom.value);
    }

    return 'Custom Range';
});

const rangeSelectValue = computed(() =>
    rangeFilter.value === 'custom' ? '' : rangeFilter.value,
);

const rangeSelectDisplayLabel = computed(() => {
    if (rangeFilter.value === 'custom') {
        return 'Custom';
    }
    if (rangeFilter.value === 'today') {
        return 'Today';
    }
    if (rangeFilter.value === 'week') {
        return 'This Week';
    }
    if (rangeFilter.value === 'month') {
        return 'This Month';
    }

    return 'This Year';
});

const availableCategories = computed(() =>
    [...props.categories].sort((a, b) => a.name.localeCompare(b.name)),
);

const selectedCategorySummary = computed(() => {
    if (selectedCategoryIds.value.length === 0) {
        return 'All categories';
    }

    if (selectedCategoryIds.value.length <= 2) {
        return selectedCategoryIds.value
            .map((id) => categoryNameById(id))
            .join(', ');
    }

    return `${selectedCategoryIds.value.length} categories selected`;
});

function categoryById(
    categoryId: number | null | undefined,
): CalendarEventCategory | null {
    if (categoryId == null) {
        return null;
    }

    return (
        props.categories.find((category) => category.id === categoryId) ?? null
    );
}

function categoryNameById(categoryId: number | null | undefined): string {
    return categoryById(categoryId)?.name ?? '';
}

function categoryColorKeyById(
    categoryId: number | null | undefined,
): string | null {
    return categoryById(categoryId)?.colorKey ?? null;
}

function eventCategoryName(event: CalendarEvent): string {
    if (event.category.trim() !== '') {
        return event.category;
    }

    return categoryNameById(event.categoryId);
}

function eventCategoryColorKey(event: CalendarEvent): string | null {
    if (event.eventKind === 'birthday') {
        return 'fuchsia';
    }

    return categoryColorKeyById(event.categoryId);
}

const toMinValue = computed<DateValue | undefined>(() => {
    if (
        !customFrom.value ||
        typeof (customFrom.value as { add?: unknown }).add !== 'function'
    ) {
        return undefined;
    }

    return (
        customFrom.value as { add: (payload: { days: number }) => DateValue }
    ).add({ days: 1 });
});

const calendarFromValue = computed<DateValue | undefined>(
    () => customFrom.value as DateValue | undefined,
);

const calendarToValue = computed<DateValue | undefined>(
    () => customTo.value as DateValue | undefined,
);

const formCalendarStartValue = computed<DateValue | undefined>(
    () => formStartDate.value as DateValue | undefined,
);

const formCalendarEndValue = computed<DateValue | undefined>(
    () => formEndDate.value as DateValue | undefined,
);

const formStartDateLabel = computed(() => {
    const parsed = dateValueToDate(formStartDate.value);
    if (!parsed) {
        return 'Select start date';
    }

    return formatFullDate(parsed);
});

const formEndDateLabel = computed(() => {
    const parsed = dateValueToDate(formEndDate.value);
    if (!parsed) {
        return 'Select end date';
    }

    return formatFullDate(parsed);
});

/** Inclusive minimum end date (same pattern as custom filter `min-value` on the “To” calendar). */
const formEndDateMinValue = computed<DateValue | undefined>(
    () => formStartDate.value as DateValue | undefined,
);

const formRecurrenceUntilCalendarValue = computed<DateValue | undefined>(
    () => formRecurrence.value.untilDate as DateValue | undefined,
);

const formRecurrenceUntilLabel = computed(() => {
    const parsed = dateValueToDate(formRecurrence.value.untilDate);
    if (!parsed) {
        return 'Select end date';
    }

    return formatFullDate(parsed);
});

function combineDateValueAndHhmm(dateVal: unknown, hhmm: string): Date | null {
    if (!dateVal || !/^\d{2}:\d{2}$/.test(hhmm)) {
        return null;
    }

    const base = dateValueToDate(dateVal);
    if (!base) {
        return null;
    }

    const [h, m] = hhmm.split(':').map(Number);

    return new Date(
        base.getFullYear(),
        base.getMonth(),
        base.getDate(),
        h,
        m,
        0,
        0,
    );
}

/** Same calendar day only: end time must be strictly after start (no equal slot). */
function filterTimeOptionsStrictlyAfter(
    all: string[],
    startHhmm: string,
): string[] {
    const idx = all.indexOf(startHhmm);
    if (idx === -1) {
        return all;
    }

    return all.slice(idx + 1);
}

function isFormTimedScheduleValid(): boolean {
    const start = combineDateValueAndHhmm(
        formStartDate.value,
        form.value.startTime.trim(),
    );
    if (!start) {
        return false;
    }

    const hasEndDate = !!formEndDate.value;
    const hasEndTime = form.value.endTime.trim() !== '';

    if (!hasEndDate && !hasEndTime) {
        return true;
    }

    if (hasEndDate && !hasEndTime) {
        const s = dateValueToDate(formStartDate.value!);
        const e = dateValueToDate(formEndDate.value!);
        if (!s || !e) {
            return false;
        }

        return startOfDay(e).getTime() >= startOfDay(s).getTime();
    }

    if (!hasEndDate && hasEndTime) {
        const end = combineDateValueAndHhmm(
            formStartDate.value,
            form.value.endTime.trim(),
        );

        return end !== null && end.getTime() > start.getTime();
    }

    const end = combineDateValueAndHhmm(
        formEndDate.value!,
        form.value.endTime.trim(),
    );

    return end !== null && end.getTime() > start.getTime();
}

const canSaveForm = computed(() => {
    if (form.value.title.trim() === '' || form.value.categoryId === null) {
        return false;
    }

    if (!formStartDate.value) {
        return false;
    }

    if (form.value.isAllDay) {
        if (!formEndDate.value) {
            return false;
        }

        const startDate = dateValueToDate(formStartDate.value);
        const endDate = dateValueToDate(formEndDate.value);

        if (!startDate || !endDate) {
            return false;
        }

        if (startOfDay(endDate).getTime() < startOfDay(startDate).getTime()) {
            return false;
        }

        return isFormRecurrenceInputValid();
    }

    if (form.value.startTime.trim() === '') {
        return false;
    }

    if (!isFormTimedScheduleValid()) {
        return false;
    }

    return isFormRecurrenceInputValid();
});

const timeOptions = computed(() => {
    const options: string[] = [];
    const startHour = 6;
    const endHour = 21;

    for (let hour = startHour; hour <= endHour; hour += 1) {
        for (let minute = 0; minute < 60; minute += 30) {
            if (hour === endHour && minute > 0) {
                continue;
            }

            options.push(
                `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`,
            );
        }
    }

    return options;
});

/** When start and end fall on the same day, end time must be strictly after start time. */
const filteredEndTimeOptions = computed(() => {
    const all = timeOptions.value;
    if (!form.value.startTime.trim()) {
        return [];
    }

    if (!formStartDate.value) {
        return filterTimeOptionsStrictlyAfter(all, form.value.startTime);
    }

    const endD = formEndDate.value;
    const startD = formStartDate.value;

    if (!endD) {
        return filterTimeOptionsStrictlyAfter(all, form.value.startTime);
    }

    const dayCmp = (endD as DateValue).compare(startD as DateValue);
    if (dayCmp > 0) {
        return all;
    }

    return filterTimeOptionsStrictlyAfter(all, form.value.startTime);
});

function eventMatchesSheetFilters(event: CalendarEvent): boolean {
    const query = searchQuery.value.trim().toLowerCase();
    const categoryName = eventCategoryName(event);
    const matchesQuery =
        query === '' ||
        event.title.toLowerCase().includes(query) ||
        event.location.toLowerCase().includes(query) ||
        event.details.toLowerCase().includes(query) ||
        categoryName.toLowerCase().includes(query);

    if (!matchesQuery) {
        return false;
    }

    if (
        selectedCategoryIds.value.length > 0 &&
        event.eventKind !== 'birthday' &&
        (event.categoryId == null ||
            !selectedCategoryIds.value.includes(event.categoryId))
    ) {
        return false;
    }

    const startsAt = parseEventDateString(event.startsAt);
    if (startsAt === null) {
        return false;
    }

    if (rangeFilter.value === 'today') {
        return (
            startsAt >= dateBounds.value.startOfToday &&
            startsAt < dateBounds.value.endOfToday
        );
    }

    if (rangeFilter.value === 'week') {
        return (
            startsAt >= dateBounds.value.startOfWeek &&
            startsAt < dateBounds.value.endOfWeekExclusive
        );
    }

    if (rangeFilter.value === 'custom') {
        if (!parsedCustomFrom.value) {
            return false;
        }

        const customStart = new Date(
            parsedCustomFrom.value.getFullYear(),
            parsedCustomFrom.value.getMonth(),
            parsedCustomFrom.value.getDate(),
        );
        const customEndBase = parsedCustomTo.value ?? parsedCustomFrom.value;
        const customEndExclusive = new Date(
            customEndBase.getFullYear(),
            customEndBase.getMonth(),
            customEndBase.getDate() + 1,
        );

        return startsAt >= customStart && startsAt < customEndExclusive;
    }

    if (rangeFilter.value === 'year') {
        return (
            startsAt >= dateBounds.value.startOfYear &&
            startsAt < dateBounds.value.endOfYearExclusive
        );
    }

    return (
        startsAt >= dateBounds.value.startOfMonth &&
        startsAt < dateBounds.value.endOfMonthExclusive
    );
}

const filteredEvents = computed(() =>
    eventsForListFiltering.value.filter((event) =>
        eventMatchesSheetFilters(event),
    ),
);

watch(
    () => open.value,
    (isOpen) => {
        if (isOpen) {
            deletedEventRootIds.value = new Set();
            isDeleteConfirmOpen.value = false;
            view.value = props.startView;
            searchQuery.value = '';
            selectedCategoryIds.value = [];
            rangeFilter.value = normalizedDefaultRangeFilter();
            isCustomRangeOpen.value = false;
            isCategoryFilterOpen.value = false;
            customFrom.value = undefined;
            customTo.value = undefined;

            if (props.initialEventId) {
                applyInitialEventSelection(props.initialEventId);
                return;
            }

            if (props.startView === 'new' && props.canCreateEvent) {
                prepareNewForm();
            } else {
                if (props.startView === 'new') {
                    view.value = 'list';
                }
                activeEvent.value = null;
            }

            if (props.initialDayFilter && props.startView === 'list') {
                applyInitialDayFilter(props.initialDayFilter);
            }

            if (props.startView === 'list') {
                void resetAndFetchFirstListPage().then(() => {
                    void nextTick().then(() => setupListObserver());
                });
            }
        } else {
            disconnectListObserver();
        }
    },
);

watch(
    () => props.startView,
    (nextView) => {
        if (!open.value) {
            return;
        }

        view.value = nextView;
        if (nextView === 'new') {
            prepareNewForm();
            disconnectListObserver();
            return;
        }

        if (nextView === 'list') {
            void resetAndFetchFirstListPage().then(() => {
                void nextTick().then(() => setupListObserver());
            });
        }
    },
);

watch(
    () => props.initialEventId,
    (nextEventId) => {
        if (!open.value || !nextEventId) {
            return;
        }

        applyInitialEventSelection(nextEventId);
    },
);

watch(
    () => props.initialDayFilter,
    (nextDay) => {
        if (!open.value || !nextDay || view.value !== 'list') {
            return;
        }

        applyInitialDayFilter(nextDay);
    },
);

watch(listQueryKey, () => {
    if (!open.value || view.value !== 'list') {
        return;
    }

    void resetAndFetchFirstListPage();
});

watch(listLocalFilterKey, () => {
    if (!open.value || view.value !== 'list' || !usePaginatedListEvents.value) {
        return;
    }

    void resetAndFetchFirstListPage();
});

watch(
    () => [usePaginatedListEvents.value, listBottomSentinel.value] as const,
    () => {
        if (!open.value || view.value !== 'list') {
            return;
        }

        void nextTick().then(() => setupListObserver());
    },
);

onBeforeUnmount(() => {
    disconnectListObserver();
});

function openEventDetails(event: CalendarEvent): void {
    activeEvent.value = event;
    view.value = 'detail';
}

function openNewEvent(): void {
    if (!props.canCreateEvent) {
        return;
    }

    prepareNewForm();
    view.value = 'new';
}

function applyInitialEventSelection(eventId: string): void {
    const selected = resolveEventFromProps(eventId);

    if (selected) {
        openEventDetails(selected);
        return;
    }

    view.value = 'list';
    activeEvent.value = null;
}

function applyInitialDayFilter(dayKey: string): void {
    const parsed = parseDayKey(dayKey);
    if (parsed === undefined) {
        return;
    }

    customFrom.value = parsed;
    customTo.value = undefined;
    rangeFilter.value = 'custom';
}

function normalizedDefaultRangeFilter(): EventRangeFilter {
    const allowed: EventRangeFilter[] = [
        'today',
        'week',
        'month',
        'year',
        'custom',
    ];

    return allowed.includes(props.defaultRangeFilter)
        ? props.defaultRangeFilter
        : 'month';
}

function setQuickRange(filter: Exclude<EventRangeFilter, 'custom'>): void {
    customFrom.value = undefined;
    customTo.value = undefined;
    rangeFilter.value = filter;
    isCustomRangeOpen.value = false;
}

function setRangeFilter(value: unknown): void {
    if (typeof value !== 'string' || value === '') {
        return;
    }

    setQuickRange(value as Exclude<EventRangeFilter, 'custom'>);
}

function applyCustomRangeFilter(): void {
    if (!isCustomRangeValid.value) {
        return;
    }

    rangeFilter.value = 'custom';
    isCustomRangeOpen.value = false;
}

function clearCustomRangeFilter(): void {
    customFrom.value = undefined;
    customTo.value = undefined;
    rangeFilter.value = normalizedDefaultRangeFilter();
    isCustomRangeOpen.value = false;
}

function clearCustomFrom(): void {
    customFrom.value = undefined;
}

function clearCustomTo(): void {
    customTo.value = undefined;
}

function isCategorySelected(categoryId: number): boolean {
    return selectedCategoryIds.value.includes(categoryId);
}

function toggleCategorySelection(categoryId: number): void {
    if (selectedCategoryIds.value.includes(categoryId)) {
        selectedCategoryIds.value = selectedCategoryIds.value.filter(
            (value) => value !== categoryId,
        );
        return;
    }

    selectedCategoryIds.value = [...selectedCategoryIds.value, categoryId];
}

function clearCategorySelection(): void {
    selectedCategoryIds.value = [];
}

function maybeClearEndTimeIfStartAfterEnd(): void {
    if (!form.value.endTime.trim() || !formStartDate.value) {
        return;
    }

    const endD = formEndDate.value ?? formStartDate.value;
    if (endD.compare(formStartDate.value as DateValue) !== 0) {
        return;
    }

    if (form.value.endTime <= form.value.startTime) {
        form.value.endTime = '';
    }
}

function selectStartTime(time: string): void {
    form.value.startTime = time;
    isStartTimeOpen.value = false;
    maybeClearEndTimeIfStartAfterEnd();
}

function selectEndTime(time: string): void {
    form.value.endTime = time;
    isEndTimeOpen.value = false;
}

function clearFormStartDate(): void {
    formStartDate.value = undefined;
    formEndDate.value = undefined;
}

function onFormStartDateSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);
    if (!normalized) {
        formStartDate.value = undefined;
        return;
    }

    formStartDate.value = normalized;

    if (
        formEndDate.value &&
        formEndDate.value.compare(normalized as DateValue) < 0
    ) {
        formEndDate.value = normalized;
    }

    if (form.value.isAllDay && !formEndDate.value) {
        formEndDate.value = normalized;
    }

    maybeClearEndTimeIfStartAfterEnd();

    close();
}

function onFormEndDateSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);
    if (!normalized) {
        formEndDate.value = undefined;
        return;
    }

    if (
        formStartDate.value &&
        normalized.compare(formStartDate.value as DateValue) < 0
    ) {
        formEndDate.value = formStartDate.value;
        close();
        return;
    }

    formEndDate.value = normalized;
    maybeClearEndTimeIfStartAfterEnd();
    close();
}

function onFormCategoryChange(value: unknown): void {
    if (typeof value !== 'string' && typeof value !== 'number') {
        return;
    }

    const parsed =
        typeof value === 'number' ? value : Number.parseInt(value, 10);
    form.value.categoryId = Number.isNaN(parsed) ? null : parsed;
}

function onRecurrenceFrequencyChange(value: unknown): void {
    if (typeof value !== 'string' || value === '') {
        return;
    }

    formRecurrence.value.frequency = value as FormRecurrenceFrequency;
    if (formRecurrence.value.frequency !== 'weekly') {
        formRecurrence.value.byWeekday = [];
    }
}

function onRecurrenceEndsChange(value: unknown): void {
    if (typeof value !== 'string' || value === '') {
        return;
    }

    const v = value as 'never' | 'until' | 'count';
    formRecurrence.value.endsType = v;

    if (v !== 'until') {
        formRecurrence.value.untilDate = undefined;
    }
}

function onFormRecurrenceUntilSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);

    if (!normalized) {
        formRecurrence.value.untilDate = undefined;
        close();
        return;
    }

    formRecurrence.value.untilDate = normalized;
    close();
}

function onCustomFromSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);

    if (normalized === undefined) {
        customFrom.value = undefined;
        return;
    }

    customFrom.value = normalized;
    close();
}

function onCustomToSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);

    if (normalized === undefined) {
        customTo.value = undefined;
        return;
    }

    customTo.value = normalized;
    close();
}

function openEditEvent(): void {
    if (!props.canManageEvents) {
        return;
    }

    if (!activeEvent.value) {
        return;
    }

    if (activeEvent.value.eventKind === 'birthday') {
        return;
    }
    restoredOccurrenceDates.value = new Set();

    const parsedStart = parseEventDate(activeEvent.value.startsAt);
    const parsedEnd = parseEventDate(activeEvent.value.endsAt);

    form.value = {
        title: activeEvent.value.title,
        startTime:
            activeEvent.value.allDay || !parsedStart
                ? ''
                : toTimeValue(parsedStart),
        endTime:
            activeEvent.value.allDay || !parsedEnd
                ? ''
                : toTimeValue(parsedEnd),
        location: activeEvent.value.location,
        categoryId:
            activeEvent.value.categoryId ??
            props.categories.find(
                (category) => category.name === activeEvent.value?.category,
            )?.id ??
            null,
        notes: activeEvent.value.details,
        isAllDay: activeEvent.value.allDay === true,
    };
    formStartDate.value = parsedStart
        ? parseDate(toDateValue(parsedStart))
        : undefined;
    formEndDate.value = parsedEnd
        ? parseDate(toDateValue(parsedEnd))
        : undefined;
    loadRecurrenceIntoForm(activeEvent.value.recurrence);
    view.value = 'edit';
}

function openDeleteConfirmDialog(): void {
    if (!props.canManageEvents) {
        return;
    }

    if (!activeEvent.value) {
        return;
    }

    if (activeEvent.value.eventKind === 'birthday') {
        return;
    }
    deleteApplyScope.value = isActiveRecurringOccurrence.value
        ? 'single_occurrence'
        : 'entire_series';

    isDeleteConfirmOpen.value = true;
}

function confirmDeleteActiveEvent(): void {
    if (!props.canManageEvents) {
        return;
    }

    if (!activeEvent.value) {
        return;
    }

    void deleteActiveEvent();
}

function goBack(): void {
    if (
        view.value === 'detail' ||
        view.value === 'new' ||
        view.value === 'edit'
    ) {
        view.value = 'list';
    }
}

function prepareNewForm(): void {
    form.value = {
        title: '',
        startTime: '',
        endTime: '',
        location: '',
        categoryId: null,
        notes: '',
        isAllDay: false,
    };
    formStartDate.value = undefined;
    formEndDate.value = undefined;
    resetFormRecurrence();
    restoredOccurrenceDates.value = new Set();
}

function parseEventDate(value: string): Date | null {
    const normalized = value.replace(' ', 'T');
    const parsed = new Date(normalized);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed;
}

function toDateValue(value: Date): string {
    return [
        value.getFullYear(),
        String(value.getMonth() + 1).padStart(2, '0'),
        String(value.getDate()).padStart(2, '0'),
    ].join('-');
}

function toTimeValue(value: Date): string {
    return [
        String(value.getHours()).padStart(2, '0'),
        String(value.getMinutes()).padStart(2, '0'),
    ].join(':');
}

function dateValueToDate(value: unknown): Date | null {
    if (
        !value ||
        typeof value !== 'object' ||
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        return null;
    }

    return (value as { toDate: (timezone: string) => Date }).toDate(
        getLocalTimeZone(),
    );
}

function formatShortDate(value: Date): string {
    return value.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatFullDate(value: Date): string {
    return value.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function startOfDay(value: Date): Date {
    return new Date(value.getFullYear(), value.getMonth(), value.getDate());
}

function formatDetailDateLabel(ev: CalendarEvent): string {
    const start = parseEventDate(ev.startsAt);
    const end = parseEventDate(ev.endsAt);
    if (!start || !end) {
        return ev.startsAt.split(' ')[0] ?? ev.startsAt;
    }

    if (startOfDay(start).getTime() === startOfDay(end).getTime()) {
        return formatFullDate(start);
    }

    return `${formatFullDate(start)} – ${formatFullDate(end)}`;
}

function formatDetailTimeLabel(ev: CalendarEvent): string {
    if (ev.allDay) {
        return 'All day';
    }

    const start = parseEventDate(ev.startsAt);
    const end = parseEventDate(ev.endsAt);
    if (!start || !end) {
        return `${ev.startsAt.split(' ')[1] ?? ''} – ${ev.endsAt.split(' ')[1] ?? ''}`.trim();
    }

    const startTime = start.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
    const endTime = end.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });

    const sameDay = startOfDay(start).getTime() === startOfDay(end).getTime();

    return sameDay
        ? `${startTime} – ${endTime}`
        : `${startTime} – ${endTime} (ends ${formatShortDate(end)})`;
}

function formatOccurrenceDateLabel(ev: CalendarEvent): string | null {
    if (!ev.seriesId) {
        return null;
    }

    const eventId = String(ev.id);
    const sep = eventId.lastIndexOf('::');
    if (sep === -1) {
        return null;
    }

    const dayKey = eventId.slice(sep + 2);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dayKey)) {
        return null;
    }

    const parsed = parseEventDate(`${dayKey} 00:00`);
    if (!parsed) {
        return dayKey;
    }

    return formatFullDate(parsed);
}

function formatOccurrenceDayKey(dayKey: string): string {
    const parsed = parseEventDateString(`${dayKey} 00:00`);
    if (!parsed) {
        return dayKey;
    }

    return formatFullDate(parsed);
}

function buildEventFromForm(): CalendarEvent {
    const startDv = formStartDate.value!;
    const startD = dateValueToDate(startDv)!;
    const endDv = formEndDate.value ?? formStartDate.value!;
    const endD = dateValueToDate(endDv)!;

    let startsAt: string;
    let endsAt: string;

    if (form.value.isAllDay) {
        const sy = startD.getFullYear();
        const sm = String(startD.getMonth() + 1).padStart(2, '0');
        const sd = String(startD.getDate()).padStart(2, '0');
        const ey = endD.getFullYear();
        const em = String(endD.getMonth() + 1).padStart(2, '0');
        const ed = String(endD.getDate()).padStart(2, '0');
        startsAt = `${sy}-${sm}-${sd} 00:00`;
        endsAt = `${ey}-${em}-${ed} 23:59`;
    } else {
        const st = form.value.startTime.trim();
        startsAt = `${toDateValue(startD)} ${st}`;

        const et = form.value.endTime.trim();
        if (et) {
            const endDateForTime = formEndDate.value ?? formStartDate.value!;
            const endBase = dateValueToDate(endDateForTime)!;
            endsAt = `${toDateValue(endBase)} ${et}`;
        } else {
            endsAt = startsAt;
        }
    }

    const recurrence = buildRecurrenceFromForm();
    const previous = activeEvent.value;
    const id =
        view.value === 'edit' && previous
            ? (previous.seriesId ?? previous.id)
            : `local-${Date.now()}`;

    return {
        id,
        title: form.value.title.trim(),
        startsAt,
        endsAt,
        location: form.value.location.trim(),
        category: categoryNameById(form.value.categoryId),
        categoryId: form.value.categoryId,
        details: form.value.notes.trim(),
        allDay: form.value.isAllDay,
        recurrence: recurrence ?? null,
        setBy:
            view.value === 'edit' && previous
                ? (previous.setBy ?? 'You')
                : 'You',
        lastEditedBy: view.value === 'edit' ? 'You' : null,
    };
}

function firstErrorMessage(payload: unknown): string | null {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const maybeMessage = (payload as { message?: unknown }).message;
    if (typeof maybeMessage === 'string' && maybeMessage.trim() !== '') {
        return maybeMessage;
    }

    const maybeErrors = (payload as { errors?: unknown }).errors;
    if (!maybeErrors || typeof maybeErrors !== 'object') {
        return null;
    }

    const firstEntry = Object.values(maybeErrors).find(
        (value) => Array.isArray(value) && value.length > 0,
    );
    if (!firstEntry || !Array.isArray(firstEntry)) {
        return null;
    }

    const firstMessage = firstEntry[0];
    return typeof firstMessage === 'string' && firstMessage.trim() !== ''
        ? firstMessage
        : null;
}

function resolveCsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    return token ? token.trim() : '';
}

function persistedEventId(event: CalendarEvent): number | null {
    if (event.eventKind === 'birthday') {
        return null;
    }

    const raw = String(event.id).trim();
    if (raw === '') {
        return null;
    }

    const withoutOccurrence = raw.includes('::') ? raw.split('::')[0] : raw;
    const parts = withoutOccurrence.split('-');
    const numeric = parts[parts.length - 1];
    const id = Number.parseInt(numeric ?? '', 10);

    return Number.isFinite(id) ? id : null;
}

function buildMutationPayload(event: CalendarEvent): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        title: event.title,
        starts_at: event.startsAt,
        ends_at: event.endsAt,
        is_all_day: event.allDay === true,
        location: event.location,
        details: event.details,
        category_id: event.categoryId,
        recurrence: event.recurrence ?? null,
        unit_id: props.teamUnitId ?? null,
    };

    if (isActiveRecurringOccurrence.value) {
        payload.apply_to = 'entire_series';
    }

    if (restoredOccurrenceDates.value.size > 0 && event.recurrence) {
        payload.restore_occurrence_dates = Array.from(
            restoredOccurrenceDates.value,
        ).sort((a, b) => a.localeCompare(b));
    }

    return payload;
}

async function deleteActiveEvent(): Promise<void> {
    if (
        !activeEvent.value ||
        props.deleteEndpointBase.trim() === '' ||
        isDeleting.value
    ) {
        return;
    }

    const id = persistedEventId(activeEvent.value);
    if (id === null) {
        appToast.error('Unable to delete this event.');
        return;
    }
    if (
        isActiveRecurringOccurrence.value &&
        deleteApplyScope.value === 'single_occurrence' &&
        !activeEventOccurrenceDate.value
    ) {
        appToast.error('Occurrence date is missing.');
        return;
    }

    isDeleting.value = true;
    const operation = (async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const deletePayload: Record<string, unknown> = {};
        if (isActiveRecurringOccurrence.value) {
            deletePayload.apply_to = deleteApplyScope.value;
            if (deleteApplyScope.value === 'single_occurrence') {
                deletePayload.occurrence_date = activeEventOccurrenceDate.value;
            }
        }

        const response = await fetch(`${props.deleteEndpointBase}/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify(deletePayload),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstErrorMessage(payload) ?? 'Unable to delete event.',
            );
        }
    })();

    try {
        await appToast.promise(operation, {
            loading:
                isActiveRecurringOccurrence.value &&
                deleteApplyScope.value === 'single_occurrence'
                    ? 'Deleting occurrence...'
                    : 'Deleting event...',
            success:
                isActiveRecurringOccurrence.value &&
                deleteApplyScope.value === 'single_occurrence'
                    ? 'Occurrence deleted.'
                    : 'Event deleted.',
            error: (error: unknown) =>
                error instanceof Error && error.message !== ''
                    ? error.message
                    : 'Unable to delete event.',
        });
        isDeleteConfirmOpen.value = false;
        activeEvent.value = null;
        view.value = 'list';
        emit('events-mutated');
    } finally {
        isDeleting.value = false;
    }
}

function submitCalendarForm(): void {
    const canSubmit =
        (view.value === 'new' && props.canCreateEvent) ||
        (view.value === 'edit' && props.canManageEvents);

    if (!canSubmit) {
        return;
    }

    if (!canSaveForm.value) {
        return;
    }

    void persistEventMutation();
}

async function persistEventMutation(): Promise<void> {
    if (isSubmitting.value) {
        return;
    }

    if (view.value === 'edit' && activeEvent.value?.eventKind === 'birthday') {
        return;
    }

    const isEdit = view.value === 'edit';
    if (!isEdit && props.createEndpoint.trim() === '') {
        appToast.error('Event create endpoint is not configured.');
        return;
    }

    const built = buildEventFromForm();
    if (built.categoryId == null) {
        appToast.error('Event category is required.');
        return;
    }
    const optimisticEventId = built.id;
    let optimisticApplied = false;

    let endpoint = props.createEndpoint.trim();
    let method = 'POST';
    if (isEdit) {
        const id = activeEvent.value
            ? persistedEventId(activeEvent.value)
            : null;
        if (id === null || props.updateEndpointBase.trim() === '') {
            appToast.error('Unable to update this event.');
            return;
        }

        endpoint = `${props.updateEndpointBase}/${id}`;
        method = 'PATCH';
    }

    isSubmitting.value = true;
    if (!isEdit) {
        emit('optimistic-created', built);
        optimisticApplied = true;
    }
    const operation = (async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(endpoint, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify(buildMutationPayload(built)),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstErrorMessage(payload) ?? 'Unable to save event.',
            );
        }
    })();

    try {
        await appToast.promise(operation, {
            loading: isEdit ? 'Saving event...' : 'Creating event...',
            success: isEdit ? 'Event updated.' : 'Event created.',
            error: (error: unknown) =>
                error instanceof Error && error.message !== ''
                    ? error.message
                    : 'Unable to save event.',
        });
        emit('events-mutated');
        open.value = false;
    } catch {
        if (optimisticApplied) {
            emit('optimistic-reverted', optimisticEventId);
        }
    } finally {
        isSubmitting.value = false;
    }
}

function formatTime12h(hhmm: string): string {
    if (!hhmm || !/^\d{2}:\d{2}$/.test(hhmm)) {
        return hhmm;
    }

    const [hours, minutes] = hhmm.split(':').map(Number);
    const d = new Date(2000, 0, 1, hours, minutes, 0, 0);

    return d.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function normalizeDateValue(value: unknown): DateValue | undefined {
    if (!value || Array.isArray(value)) {
        return undefined;
    }
    if (typeof value !== 'object' || value === null) {
        return undefined;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        return undefined;
    }

    return value as DateValue;
}

function formatRange(start: Date, endExclusive: Date): string {
    const end = new Date(
        endExclusive.getFullYear(),
        endExclusive.getMonth(),
        endExclusive.getDate() - 1,
    );

    const startLabel = formatFullDate(start);
    const endLabel = formatFullDate(end);

    return `${startLabel} to ${endLabel}`;
}

function parseDayKey(dayKey: string): DateValue | undefined {
    try {
        return parseDate(dayKey) as DateValue;
    } catch {
        return undefined;
    }
}

function getSheetEventListToneClassesByColorKey(
    colorKey: string | null | undefined,
): { card: string; badge: string } {
    const normalized = colorKey?.trim().toLowerCase() ?? '';

    switch (normalized) {
        case 'blue':
            return {
                card: 'border-blue-500/30 bg-blue-500/10 hover:bg-blue-500/16',
                badge: 'border-blue-500/40 bg-blue-500/16 text-blue-700 dark:text-blue-300',
            };
        case 'violet':
            return {
                card: 'border-violet-500/30 bg-violet-500/10 hover:bg-violet-500/16',
                badge: 'border-violet-500/40 bg-violet-500/16 text-violet-700 dark:text-violet-300',
            };
        case 'indigo':
            return {
                card: 'border-indigo-500/30 bg-indigo-500/10 hover:bg-indigo-500/16',
                badge: 'border-indigo-500/40 bg-indigo-500/16 text-indigo-700 dark:text-indigo-300',
            };
        case 'teal':
            return {
                card: 'border-teal-500/30 bg-teal-500/10 hover:bg-teal-500/16',
                badge: 'border-teal-500/40 bg-teal-500/16 text-teal-700 dark:text-teal-300',
            };
        case 'slate':
            return {
                card: 'border-slate-500/30 bg-slate-500/10 hover:bg-slate-500/16',
                badge: 'border-slate-500/40 bg-slate-500/16 text-slate-700 dark:text-slate-300',
            };
        case 'emerald':
            return {
                card: 'border-emerald-500/30 bg-emerald-500/10 hover:bg-emerald-500/16',
                badge: 'border-emerald-500/40 bg-emerald-500/16 text-emerald-700 dark:text-emerald-300',
            };
        case 'orange':
            return {
                card: 'border-orange-500/30 bg-orange-500/10 hover:bg-orange-500/16',
                badge: 'border-orange-500/40 bg-orange-500/16 text-orange-700 dark:text-orange-300',
            };
        case 'rose':
            return {
                card: 'border-rose-500/30 bg-rose-500/10 hover:bg-rose-500/16',
                badge: 'border-rose-500/40 bg-rose-500/16 text-rose-700 dark:text-rose-300',
            };
        case 'fuchsia':
            return {
                card: 'border-fuchsia-500/30 bg-fuchsia-500/10 hover:bg-fuchsia-500/16',
                badge: 'border-fuchsia-500/40 bg-fuchsia-500/16 text-fuchsia-700 dark:text-fuchsia-300',
            };
        default:
            return {
                card: 'border-primary/30 bg-primary/10 hover:bg-primary/16',
                badge: 'border-primary/40 bg-primary/16 text-primary',
            };
    }
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex max-h-dvh flex-col sm:max-w-lg">
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div
                    class="flex min-h-0 flex-1 flex-col gap-0 overflow-hidden px-4 pb-4 text-sm"
                >
                    <SheetHeader class="space-y-0 px-0 pt-4 pb-0 text-left">
                        <SheetTitle
                            class="text-base font-semibold text-foreground"
                        >
                            {{ headerTitle }}
                        </SheetTitle>
                        <SheetDescription class="mt-2">
                            {{ headerDescription }}
                        </SheetDescription>
                    </SheetHeader>

                    <div class="mt-4 flex min-h-0 flex-1 flex-col">
                        <template v-if="view === 'list'">
                            <div class="flex min-h-0 flex-1 flex-col">
                                <div class="shrink-0 space-y-3">
                                    <InputGroup>
                                        <InputGroupAddon>
                                            <Search class="size-4" />
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            v-model="searchQuery"
                                            type="text"
                                            placeholder="Search title, location, notes, category..."
                                        />
                                    </InputGroup>

                                    <div
                                        class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] sm:items-center"
                                    >
                                        <Select
                                            :model-value="rangeSelectValue"
                                            @update:model-value="setRangeFilter"
                                        >
                                            <SelectTrigger
                                                class="w-full font-normal text-foreground"
                                            >
                                                <span
                                                    class="flex min-w-0 items-center gap-2 text-left"
                                                >
                                                    <Filter
                                                        class="size-4 text-muted-foreground"
                                                    />
                                                    <span class="truncate">
                                                        {{
                                                            rangeSelectDisplayLabel
                                                        }}
                                                    </span>
                                                </span>
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="today">
                                                    Today
                                                </SelectItem>
                                                <SelectItem value="week">
                                                    This Week
                                                </SelectItem>
                                                <SelectItem value="month">
                                                    This Month
                                                </SelectItem>
                                                <SelectItem value="year">
                                                    This Year
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>

                                        <Popover
                                            v-model:open="isCustomRangeOpen"
                                        >
                                            <PopoverTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    class="w-full justify-start"
                                                >
                                                    <Calendar
                                                        class="size-4 text-muted-foreground"
                                                    />
                                                    {{ dateAnchorLabel }}
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                class="w-80 space-y-3 p-3"
                                                align="end"
                                            >
                                                <div class="grid gap-2">
                                                    <Label
                                                        :class="
                                                            optionalLabelRowClass
                                                        "
                                                    >
                                                        <span>From</span>
                                                        <Button
                                                            v-if="customFrom"
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear from date"
                                                            @click="
                                                                clearCustomFrom
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Popover v-slot="{ close }">
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full justify-between gap-2 text-left font-normal"
                                                                aria-label="Select custom from date"
                                                            >
                                                                <span
                                                                    class="min-w-0 flex-1 truncate"
                                                                    :class="
                                                                        customFrom
                                                                            ? 'text-foreground'
                                                                            : 'text-muted-foreground'
                                                                    "
                                                                >
                                                                    {{
                                                                        fromDisplayLabel
                                                                    }}
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-auto overflow-hidden p-0"
                                                            align="start"
                                                        >
                                                            <DatePickerCalendar
                                                                layout="month-and-year"
                                                                :model-value="
                                                                    calendarFromValue
                                                                "
                                                                @update:model-value="
                                                                    (value) =>
                                                                        onCustomFromSelect(
                                                                            value,
                                                                            close,
                                                                        )
                                                                "
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        :class="
                                                            optionalLabelRowClass
                                                        "
                                                    >
                                                        <span>To</span>
                                                        <Badge
                                                            variant="outline"
                                                        >
                                                            Optional
                                                        </Badge>
                                                        <Button
                                                            v-if="customTo"
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear to date"
                                                            @click="
                                                                clearCustomTo
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Popover v-slot="{ close }">
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full justify-between gap-2 text-left font-normal"
                                                                aria-label="Select custom to date"
                                                            >
                                                                <span
                                                                    class="min-w-0 flex-1 truncate"
                                                                    :class="
                                                                        customTo
                                                                            ? 'text-foreground'
                                                                            : 'text-muted-foreground'
                                                                    "
                                                                >
                                                                    {{
                                                                        toDisplayLabel
                                                                    }}
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-auto overflow-hidden p-0"
                                                            align="start"
                                                        >
                                                            <DatePickerCalendar
                                                                layout="month-and-year"
                                                                :model-value="
                                                                    calendarToValue
                                                                "
                                                                :min-value="
                                                                    toMinValue
                                                                "
                                                                @update:model-value="
                                                                    (value) =>
                                                                        onCustomToSelect(
                                                                            value,
                                                                            close,
                                                                        )
                                                                "
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                                <div
                                                    class="grid grid-cols-2 gap-2"
                                                >
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        @click="
                                                            clearCustomRangeFilter
                                                        "
                                                    >
                                                        Clear
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        :disabled="
                                                            !isCustomRangeValid
                                                        "
                                                        @click="
                                                            applyCustomRangeFilter
                                                        "
                                                    >
                                                        Apply
                                                    </Button>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    </div>

                                    <Popover
                                        v-model:open="isCategoryFilterOpen"
                                    >
                                        <PopoverTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="w-full justify-start"
                                            >
                                                <Filter
                                                    class="size-4 text-muted-foreground"
                                                />
                                                <span class="min-w-0 truncate">
                                                    {{
                                                        selectedCategorySummary
                                                    }}
                                                </span>
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            class="w-80 space-y-3 p-3"
                                            align="end"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <p
                                                    class="text-sm font-medium text-foreground"
                                                >
                                                    Categories
                                                </p>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 px-2"
                                                    :disabled="
                                                        selectedCategoryIds.length ===
                                                        0
                                                    "
                                                    @click="
                                                        clearCategorySelection
                                                    "
                                                >
                                                    Clear
                                                </Button>
                                            </div>

                                            <ScrollArea class="h-56">
                                                <div
                                                    class="divide-y divide-border/60 px-1.5 pr-3 pb-4"
                                                >
                                                    <button
                                                        v-for="category in availableCategories"
                                                        :key="category.id"
                                                        type="button"
                                                        class="flex w-full items-center gap-2 px-2 py-2 text-left text-sm transition-colors hover:bg-accent/30"
                                                        :class="
                                                            isCategorySelected(
                                                                category.id,
                                                            )
                                                                ? 'bg-accent/40 text-foreground'
                                                                : 'text-muted-foreground'
                                                        "
                                                        @click="
                                                            toggleCategorySelection(
                                                                category.id,
                                                            )
                                                        "
                                                    >
                                                        <span
                                                            class="size-2.5 shrink-0 rounded-full bg-current ring-1 ring-border/60"
                                                            :class="
                                                                getCalendarEventCategoryChipStylesByColorKey(
                                                                    category.colorKey,
                                                                ).icon
                                                            "
                                                            aria-hidden="true"
                                                        />
                                                        <span
                                                            class="min-w-0 flex-1 truncate"
                                                        >
                                                            {{ category.name }}
                                                        </span>
                                                        <Check
                                                            v-if="
                                                                isCategorySelected(
                                                                    category.id,
                                                                )
                                                            "
                                                            class="size-4 shrink-0 text-primary"
                                                            aria-hidden="true"
                                                        />
                                                    </button>
                                                </div>
                                            </ScrollArea>
                                        </PopoverContent>
                                    </Popover>
                                </div>

                                <ScrollArea class="mt-3 min-h-0 flex-1">
                                    <div class="px-1.5 pr-3 pb-8">
                                        <div
                                            v-if="
                                                usePaginatedListEvents &&
                                                isListLoading &&
                                                filteredEvents.length === 0
                                            "
                                            class="space-y-2"
                                        >
                                            <div
                                                v-for="idx in 4"
                                                :key="`events-list-skeleton-${idx}`"
                                                class="rounded-md border border-border/60 bg-muted/20 px-3 py-2"
                                            >
                                                <div
                                                    class="h-4 w-2/3 animate-pulse rounded bg-muted"
                                                />
                                                <div
                                                    class="mt-2 h-3 w-1/2 animate-pulse rounded bg-muted/80"
                                                />
                                                <div
                                                    class="mt-2 h-3 w-1/3 animate-pulse rounded bg-muted/70"
                                                />
                                            </div>
                                        </div>
                                        <div
                                            v-else-if="
                                                filteredEvents.length === 0
                                            "
                                            class="rounded-md border border-dashed border-border/70 bg-muted/20 p-4 text-center"
                                        >
                                            No matching events found.
                                        </div>

                                        <div v-else class="space-y-2">
                                            <button
                                                v-for="event in filteredEvents"
                                                :key="event.id"
                                                type="button"
                                                class="flex w-full cursor-pointer items-start justify-between gap-3 rounded-md border px-3 py-2 text-left transition-colors"
                                                :class="
                                                    getSheetEventListToneClassesByColorKey(
                                                        eventCategoryColorKey(
                                                            event,
                                                        ),
                                                    ).card
                                                "
                                                @click="openEventDetails(event)"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="truncate text-sm font-semibold text-foreground"
                                                    >
                                                        {{ event.title }}
                                                    </p>
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        {{ event.startsAt }} -
                                                        {{ event.endsAt }}
                                                    </p>
                                                    <p
                                                        v-if="event.recurrence"
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            formatRecurrenceSummary(
                                                                event.recurrence,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="truncate text-xs text-muted-foreground"
                                                    >
                                                        {{ event.location }}
                                                    </p>
                                                </div>

                                                <Badge
                                                    variant="outline"
                                                    class="mt-0.5 shrink-0"
                                                    :class="
                                                        cn(
                                                            getSheetEventListToneClassesByColorKey(
                                                                eventCategoryColorKey(
                                                                    event,
                                                                ),
                                                            ).badge,
                                                            getCalendarEventCategoryChipStylesByColorKey(
                                                                eventCategoryColorKey(
                                                                    event,
                                                                ),
                                                            ).icon,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        eventCategoryName(event)
                                                    }}
                                                </Badge>
                                            </button>
                                        </div>
                                        <p
                                            v-if="
                                                usePaginatedListEvents &&
                                                isListLoading
                                            "
                                            class="mt-3 text-center text-xs text-muted-foreground"
                                        >
                                            Loading more events...
                                        </p>
                                        <div
                                            v-if="
                                                usePaginatedListEvents &&
                                                filteredEvents.length > 0
                                            "
                                            ref="listBottomSentinel"
                                            class="h-1 w-full"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </ScrollArea>
                            </div>
                        </template>

                        <template v-else-if="view === 'detail' && activeEvent">
                            <ScrollArea class="min-h-0 flex-1">
                                <div class="space-y-3 px-1.5 pr-3 pb-8">
                                    <div
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{
                                                activeEvent.eventKind ===
                                                'birthday'
                                                    ? 'Birthday'
                                                    : 'Event'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1.5 text-base font-semibold text-foreground"
                                        >
                                            {{ activeEvent.title }}
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            When
                                        </p>
                                        <div
                                            class="mt-1.5 space-y-1.5 text-foreground"
                                        >
                                            <p class="flex items-start gap-2">
                                                <Calendar
                                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <span>{{
                                                    formatDetailDateLabel(
                                                        activeEvent,
                                                    )
                                                }}</span>
                                            </p>
                                            <p class="flex items-start gap-2">
                                                <Clock3
                                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <span>{{
                                                    formatDetailTimeLabel(
                                                        activeEvent,
                                                    )
                                                }}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        v-if="
                                            activeEvent.eventKind ===
                                                'birthday' &&
                                            activeEvent.primaryPositionTitle
                                        "
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Primary position
                                        </p>
                                        <p
                                            class="mt-1.5 flex items-start gap-2 text-foreground"
                                        >
                                            <Briefcase
                                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <span
                                                class="min-w-0 wrap-break-word"
                                                >{{
                                                    activeEvent.primaryPositionTitle
                                                }}</span
                                            >
                                        </p>
                                    </div>

                                    <div
                                        class="grid gap-3"
                                        :class="
                                            activeEvent.eventKind === 'birthday'
                                                ? 'grid-cols-1'
                                                : 'grid-cols-1 sm:grid-cols-2'
                                        "
                                    >
                                        <div
                                            v-if="
                                                activeEvent.eventKind !==
                                                'birthday'
                                            "
                                            class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Location
                                            </p>
                                            <p
                                                class="mt-1.5 flex items-start gap-2 text-foreground"
                                            >
                                                <MapPin
                                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <span
                                                    class="min-w-0 wrap-break-word"
                                                >
                                                    {{
                                                        activeEvent.location ||
                                                        'No location set'
                                                    }}
                                                </span>
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Category
                                            </p>
                                            <div
                                                class="mt-1.5 flex items-center gap-2"
                                            >
                                                <component
                                                    :is="
                                                        activeEvent.eventKind ===
                                                        'birthday'
                                                            ? Cake
                                                            : Tag
                                                    "
                                                    class="size-4 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <Badge
                                                    variant="outline"
                                                    :class="
                                                        cn(
                                                            getSheetEventListToneClassesByColorKey(
                                                                eventCategoryColorKey(
                                                                    activeEvent,
                                                                ),
                                                            ).badge,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        eventCategoryName(
                                                            activeEvent,
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="activeEvent.recurrence"
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Repeat
                                        </p>
                                        <p
                                            class="mt-1.5 flex items-start gap-2 text-foreground"
                                        >
                                            <Repeat
                                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <span>{{
                                                formatRecurrenceSummary(
                                                    activeEvent.recurrence,
                                                )
                                            }}</span>
                                        </p>
                                    </div>

                                    <div
                                        v-if="
                                            formatOccurrenceDateLabel(
                                                activeEvent,
                                            )
                                        "
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Occurrence
                                        </p>
                                        <p class="mt-1.5 text-foreground">
                                            {{
                                                formatOccurrenceDateLabel(
                                                    activeEvent,
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="
                                            activeEvent.eventKind !== 'birthday'
                                        "
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Notes
                                        </p>
                                        <p
                                            class="mt-1.5 whitespace-pre-wrap text-foreground"
                                        >
                                            {{
                                                activeEvent.details ||
                                                'No notes provided.'
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="
                                            activeEvent.eventKind !== 'birthday'
                                        "
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Audit
                                        </p>
                                        <div
                                            class="mt-1.5 space-y-1.5 text-sm text-foreground"
                                        >
                                            <p>
                                                <span
                                                    class="text-muted-foreground"
                                                    >Set by:</span
                                                >
                                                {{ activeEvent.setBy || '—' }}
                                            </p>
                                            <p>
                                                <span
                                                    class="text-muted-foreground"
                                                    >Last edited by:</span
                                                >
                                                {{
                                                    activeEvent.lastEditedBy ||
                                                    '—'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </ScrollArea>
                        </template>

                        <template v-else>
                            <ScrollArea class="min-h-0 flex-1">
                                <div class="px-1.5 pr-3 pb-8">
                                    <form class="space-y-4" @submit.prevent>
                                        <div class="space-y-2">
                                            <Label
                                                for="event-title"
                                                :class="optionalLabelRowClass"
                                            >
                                                <span>Event title</span>
                                                <Button
                                                    v-if="form.title !== ''"
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="
                                                        clearFieldButtonClass
                                                    "
                                                    aria-label="Clear event title"
                                                    @click="form.title = ''"
                                                >
                                                    <X class="size-3.5" />
                                                </Button>
                                            </Label>
                                            <Input
                                                id="event-title"
                                                v-model="form.title"
                                                type="text"
                                                placeholder="Quarterly planning"
                                            />
                                        </div>

                                        <div
                                            class="flex items-center justify-between gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5"
                                        >
                                            <div class="min-w-0 space-y-0.5">
                                                <Label
                                                    class="text-sm font-medium text-foreground"
                                                    for="event-all-day"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1.5"
                                                    >
                                                        <span>All day</span>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex text-muted-foreground hover:text-foreground"
                                                                    aria-label="All-day event help"
                                                                >
                                                                    <CircleHelp
                                                                        class="size-3.5"
                                                                    />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                class="max-w-64 text-xs"
                                                            >
                                                                Uses date-only
                                                                scheduling. In
                                                                v1, recurrence
                                                                is hidden for
                                                                multi-day
                                                                all-day events.
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </span>
                                                </Label>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Uses start and end dates
                                                    only (no specific times).
                                                </p>
                                            </div>
                                            <Switch
                                                id="event-all-day"
                                                v-model="form.isAllDay"
                                            />
                                        </div>

                                        <div class="space-y-3">
                                            <p
                                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Date
                                            </p>
                                            <div
                                                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label
                                                        :class="
                                                            optionalLabelRowClass
                                                        "
                                                    >
                                                        <span>From</span>
                                                        <Button
                                                            v-if="formStartDate"
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear start date"
                                                            @click="
                                                                clearFormStartDate
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Popover v-slot="{ close }">
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full justify-between gap-2 text-left font-normal"
                                                                aria-label="Select start date"
                                                            >
                                                                <span
                                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                                >
                                                                    <Calendar
                                                                        class="size-4 shrink-0 text-muted-foreground"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span
                                                                        class="min-w-0 flex-1 truncate"
                                                                        :class="
                                                                            formStartDate
                                                                                ? 'text-foreground'
                                                                                : 'text-muted-foreground'
                                                                        "
                                                                    >
                                                                        {{
                                                                            formStartDateLabel
                                                                        }}
                                                                    </span>
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-auto overflow-hidden p-0"
                                                            align="start"
                                                        >
                                                            <DatePickerCalendar
                                                                layout="month-and-year"
                                                                :model-value="
                                                                    formCalendarStartValue
                                                                "
                                                                @update:model-value="
                                                                    (value) =>
                                                                        onFormStartDateSelect(
                                                                            value,
                                                                            close,
                                                                        )
                                                                "
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>

                                                <div class="grid gap-2">
                                                    <Label
                                                        :class="
                                                            optionalLabelRowClass
                                                        "
                                                    >
                                                        <span>To</span>
                                                        <Badge
                                                            v-if="
                                                                !form.isAllDay
                                                            "
                                                            variant="outline"
                                                        >
                                                            Optional
                                                        </Badge>
                                                        <Badge
                                                            v-else
                                                            variant="outline"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Button
                                                            v-if="formEndDate"
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear end date"
                                                            @click="
                                                                formEndDate =
                                                                    undefined
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Popover v-slot="{ close }">
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full justify-between gap-2 text-left font-normal"
                                                                :disabled="
                                                                    !formStartDate
                                                                "
                                                                aria-label="Select end date"
                                                            >
                                                                <span
                                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                                >
                                                                    <Calendar
                                                                        class="size-4 shrink-0 text-muted-foreground"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span
                                                                        class="min-w-0 flex-1 truncate"
                                                                        :class="
                                                                            formEndDate
                                                                                ? 'text-foreground'
                                                                                : 'text-muted-foreground'
                                                                        "
                                                                    >
                                                                        {{
                                                                            formEndDateLabel
                                                                        }}
                                                                    </span>
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-auto overflow-hidden p-0"
                                                            align="start"
                                                        >
                                                            <DatePickerCalendar
                                                                layout="month-and-year"
                                                                :model-value="
                                                                    formCalendarEndValue
                                                                "
                                                                :min-value="
                                                                    formEndDateMinValue
                                                                "
                                                                @update:model-value="
                                                                    (value) =>
                                                                        onFormEndDateSelect(
                                                                            value,
                                                                            close,
                                                                        )
                                                                "
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-if="!form.isAllDay"
                                            class="space-y-3"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Time
                                            </p>
                                            <div
                                                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label
                                                        :class="
                                                            optionalLabelRowClass
                                                        "
                                                    >
                                                        <span>Start</span>
                                                        <Button
                                                            v-if="
                                                                form.startTime
                                                            "
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear start time"
                                                            @click="
                                                                form.startTime =
                                                                    '';
                                                                form.endTime =
                                                                    '';
                                                                isStartTimeOpen = false;
                                                                isEndTimeOpen = false;
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Popover
                                                        v-model:open="
                                                            isStartTimeOpen
                                                        "
                                                    >
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full justify-between gap-2 text-left font-normal"
                                                                aria-label="Select start time"
                                                            >
                                                                <span
                                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                                >
                                                                    <Clock3
                                                                        class="size-4 shrink-0 text-muted-foreground"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span
                                                                        class="min-w-0 flex-1 truncate"
                                                                        :class="
                                                                            form.startTime
                                                                                ? 'text-foreground'
                                                                                : 'text-muted-foreground'
                                                                        "
                                                                    >
                                                                        {{
                                                                            form.startTime
                                                                                ? formatTime12h(
                                                                                      form.startTime,
                                                                                  )
                                                                                : 'Select start time'
                                                                        }}
                                                                    </span>
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-44 p-1"
                                                            align="start"
                                                        >
                                                            <ScrollArea
                                                                class="h-56"
                                                            >
                                                                <div
                                                                    class="space-y-0.5 px-1 pr-2 pb-3"
                                                                >
                                                                    <Button
                                                                        v-for="time in timeOptions"
                                                                        :key="`start-${time}`"
                                                                        type="button"
                                                                        variant="ghost"
                                                                        class="h-8 w-full justify-start px-2 text-sm font-normal"
                                                                        :class="
                                                                            form.startTime ===
                                                                            time
                                                                                ? 'bg-accent text-foreground'
                                                                                : 'text-muted-foreground'
                                                                        "
                                                                        @click="
                                                                            selectStartTime(
                                                                                time,
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            formatTime12h(
                                                                                time,
                                                                            )
                                                                        }}
                                                                    </Button>
                                                                </div>
                                                            </ScrollArea>
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>

                                                <div class="grid gap-2">
                                                    <Label
                                                        :class="
                                                            optionalLabelRowClass
                                                        "
                                                    >
                                                        <span>End</span>
                                                        <Badge
                                                            variant="outline"
                                                        >
                                                            Optional
                                                        </Badge>
                                                        <Button
                                                            v-if="form.endTime"
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            :class="
                                                                clearFieldButtonClass
                                                            "
                                                            aria-label="Clear end time"
                                                            @click="
                                                                form.endTime =
                                                                    '';
                                                                isEndTimeOpen = false;
                                                            "
                                                        >
                                                            <X
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </Label>
                                                    <Popover
                                                        v-model:open="
                                                            isEndTimeOpen
                                                        "
                                                    >
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full justify-between gap-2 text-left font-normal"
                                                                :disabled="
                                                                    !form.startTime
                                                                "
                                                                aria-label="Select end time"
                                                            >
                                                                <span
                                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                                >
                                                                    <Clock3
                                                                        class="size-4 shrink-0 text-muted-foreground"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span
                                                                        class="min-w-0 flex-1 truncate"
                                                                        :class="
                                                                            form.endTime
                                                                                ? 'text-foreground'
                                                                                : 'text-muted-foreground'
                                                                        "
                                                                    >
                                                                        {{
                                                                            form.endTime
                                                                                ? formatTime12h(
                                                                                      form.endTime,
                                                                                  )
                                                                                : 'Select end time'
                                                                        }}
                                                                    </span>
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-44 p-1"
                                                            align="start"
                                                        >
                                                            <ScrollArea
                                                                class="h-56"
                                                            >
                                                                <div
                                                                    class="space-y-0.5 px-1 pr-2 pb-3"
                                                                >
                                                                    <Button
                                                                        v-for="time in filteredEndTimeOptions"
                                                                        :key="`end-${time}`"
                                                                        type="button"
                                                                        variant="ghost"
                                                                        class="h-8 w-full justify-start px-2 text-sm font-normal"
                                                                        :class="
                                                                            form.endTime ===
                                                                            time
                                                                                ? 'bg-accent text-foreground'
                                                                                : 'text-muted-foreground'
                                                                        "
                                                                        @click="
                                                                            selectEndTime(
                                                                                time,
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            formatTime12h(
                                                                                time,
                                                                            )
                                                                        }}
                                                                    </Button>
                                                                </div>
                                                            </ScrollArea>
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-if="showRecurrenceSection"
                                            class="space-y-3"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                <span
                                                    class="inline-flex items-center gap-1.5"
                                                >
                                                    <span>Repeat</span>
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            as-child
                                                        >
                                                            <button
                                                                type="button"
                                                                class="inline-flex text-muted-foreground hover:text-foreground"
                                                                aria-label="Repeat section help"
                                                            >
                                                                <CircleHelp
                                                                    class="size-3.5"
                                                                />
                                                            </button>
                                                        </TooltipTrigger>
                                                        <TooltipContent
                                                            side="top"
                                                            class="max-w-72 text-xs"
                                                        >
                                                            Frequency sets the
                                                            pattern, interval
                                                            sets the spacing,
                                                            and Ends controls
                                                            when repetition
                                                            stops.
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </span>
                                            </p>

                                            <div class="grid gap-2">
                                                <Label
                                                    for="event-recurrence-frequency"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1.5"
                                                    >
                                                        <span>Frequency</span>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex text-muted-foreground hover:text-foreground"
                                                                    aria-label="Frequency help"
                                                                >
                                                                    <CircleHelp
                                                                        class="size-3.5"
                                                                    />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                class="max-w-64 text-xs"
                                                            >
                                                                Chooses whether
                                                                the event
                                                                repeats daily,
                                                                weekly, monthly,
                                                                or yearly.
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </span>
                                                </Label>
                                                <Select
                                                    id="event-recurrence-frequency"
                                                    :model-value="
                                                        formRecurrence.frequency
                                                    "
                                                    @update:model-value="
                                                        onRecurrenceFrequencyChange
                                                    "
                                                >
                                                    <SelectTrigger
                                                        class="w-full font-normal text-foreground"
                                                    >
                                                        <span
                                                            class="flex min-w-0 items-center gap-2 text-left"
                                                        >
                                                            <Repeat
                                                                class="size-4 shrink-0 text-muted-foreground"
                                                                aria-hidden="true"
                                                            />
                                                            <span
                                                                class="min-w-0 truncate"
                                                            >
                                                                {{
                                                                    formRecurrence.frequency ===
                                                                    'none'
                                                                        ? 'Does not repeat'
                                                                        : formRecurrence.frequency ===
                                                                            'daily'
                                                                          ? 'Daily'
                                                                          : formRecurrence.frequency ===
                                                                              'weekly'
                                                                            ? 'Weekly'
                                                                            : formRecurrence.frequency ===
                                                                                'monthly'
                                                                              ? 'Monthly'
                                                                              : 'Yearly'
                                                                }}
                                                            </span>
                                                        </span>
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value="none"
                                                        >
                                                            Does not repeat
                                                        </SelectItem>
                                                        <SelectItem
                                                            value="daily"
                                                        >
                                                            Daily
                                                        </SelectItem>
                                                        <SelectItem
                                                            value="weekly"
                                                        >
                                                            Weekly
                                                        </SelectItem>
                                                        <SelectItem
                                                            value="monthly"
                                                        >
                                                            Monthly
                                                        </SelectItem>
                                                        <SelectItem
                                                            value="yearly"
                                                        >
                                                            Yearly
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div
                                                v-if="
                                                    formRecurrence.frequency !==
                                                    'none'
                                                "
                                                class="space-y-2"
                                            >
                                                <Label
                                                    for="event-recurrence-interval"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1.5"
                                                    >
                                                        <span>Interval</span>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex text-muted-foreground hover:text-foreground"
                                                                    aria-label="Interval help"
                                                                >
                                                                    <CircleHelp
                                                                        class="size-3.5"
                                                                    />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                class="max-w-72 text-xs"
                                                            >
                                                                Spacing between
                                                                repeats.
                                                                Example:
                                                                interval 2 with
                                                                weekly means
                                                                every 2 weeks.
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </span>
                                                </Label>
                                                <Input
                                                    id="event-recurrence-interval"
                                                    v-model.number="
                                                        formRecurrence.interval
                                                    "
                                                    type="number"
                                                    min="1"
                                                    max="99"
                                                    class="max-w-32"
                                                />
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        formRecurrence.frequency ===
                                                        'daily'
                                                            ? 'Day(s) between each occurrence.'
                                                            : formRecurrence.frequency ===
                                                                'weekly'
                                                              ? 'Week(s) between occurrence weeks.'
                                                              : formRecurrence.frequency ===
                                                                  'monthly'
                                                                ? 'Month(s) between occurrences.'
                                                                : 'Year(s) between occurrences.'
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                v-if="
                                                    formRecurrence.frequency ===
                                                    'weekly'
                                                "
                                                class="space-y-2"
                                            >
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1.5"
                                                    >
                                                        <span>On weekdays</span>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex text-muted-foreground hover:text-foreground"
                                                                    aria-label="Weekly weekdays help"
                                                                >
                                                                    <CircleHelp
                                                                        class="size-3.5"
                                                                    />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                class="max-w-64 text-xs"
                                                            >
                                                                Select one or
                                                                more weekdays
                                                                used by weekly
                                                                recurrence.
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </span>
                                                </p>
                                                <div
                                                    class="flex flex-wrap gap-1.5"
                                                >
                                                    <Button
                                                        v-for="opt in recurrenceWeekdayOptions"
                                                        :key="opt.value"
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        :class="
                                                            formRecurrence.byWeekday.includes(
                                                                opt.value,
                                                            )
                                                                ? 'border-primary bg-primary/10'
                                                                : ''
                                                        "
                                                        @click="
                                                            toggleWeekday(
                                                                opt.value,
                                                            )
                                                        "
                                                    >
                                                        {{ opt.label }}
                                                    </Button>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    formRecurrence.frequency !==
                                                    'none'
                                                "
                                                class="space-y-2"
                                            >
                                                <Label
                                                    for="event-recurrence-ends"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1.5"
                                                    >
                                                        <span>Ends</span>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex text-muted-foreground hover:text-foreground"
                                                                    aria-label="Ends help"
                                                                >
                                                                    <CircleHelp
                                                                        class="size-3.5"
                                                                    />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                class="max-w-72 text-xs"
                                                            >
                                                                Never keeps
                                                                repeating, On
                                                                date stops at a
                                                                date, and After
                                                                N occurrences
                                                                stops after a
                                                                set count.
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </span>
                                                </Label>
                                                <Select
                                                    id="event-recurrence-ends"
                                                    :model-value="
                                                        formRecurrence.endsType
                                                    "
                                                    @update:model-value="
                                                        onRecurrenceEndsChange
                                                    "
                                                >
                                                    <SelectTrigger
                                                        class="w-full font-normal text-foreground"
                                                    >
                                                        <span class="truncate">
                                                            {{
                                                                formRecurrence.endsType ===
                                                                'never'
                                                                    ? 'Never'
                                                                    : formRecurrence.endsType ===
                                                                        'until'
                                                                      ? 'On date'
                                                                      : 'After N occurrences'
                                                            }}
                                                        </span>
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value="never"
                                                        >
                                                            Never
                                                        </SelectItem>
                                                        <SelectItem
                                                            value="until"
                                                        >
                                                            On date
                                                        </SelectItem>
                                                        <SelectItem
                                                            value="count"
                                                        >
                                                            After N occurrences
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>

                                                <template
                                                    v-if="
                                                        formRecurrence.endsType ===
                                                        'until'
                                                    "
                                                >
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        <span
                                                            class="inline-flex items-center gap-1.5"
                                                        >
                                                            <span
                                                                >Repeat end
                                                                date</span
                                                            >
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    as-child
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="inline-flex text-muted-foreground hover:text-foreground"
                                                                        aria-label="Repeat end date help"
                                                                    >
                                                                        <CircleHelp
                                                                            class="size-3.5"
                                                                        />
                                                                    </button>
                                                                </TooltipTrigger>
                                                                <TooltipContent
                                                                    side="top"
                                                                    class="max-w-72 text-xs"
                                                                >
                                                                    The last
                                                                    date when
                                                                    this
                                                                    repeating
                                                                    event can
                                                                    occur.
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </span>
                                                    </p>
                                                    <Popover v-slot="{ close }">
                                                        <PopoverTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                class="w-full justify-between gap-2 text-left font-normal"
                                                                :disabled="
                                                                    !formStartDate
                                                                "
                                                                aria-label="Select repeat end date"
                                                            >
                                                                <span
                                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                                >
                                                                    <Calendar
                                                                        class="size-4 shrink-0 text-muted-foreground"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span
                                                                        class="min-w-0 flex-1 truncate"
                                                                        :class="
                                                                            formRecurrence.untilDate
                                                                                ? 'text-foreground'
                                                                                : 'text-muted-foreground'
                                                                        "
                                                                    >
                                                                        {{
                                                                            formRecurrenceUntilLabel
                                                                        }}
                                                                    </span>
                                                                </span>
                                                                <ChevronDownIcon
                                                                    class="size-4 shrink-0 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            class="w-auto overflow-hidden p-0"
                                                            align="start"
                                                        >
                                                            <DatePickerCalendar
                                                                layout="month-and-year"
                                                                :model-value="
                                                                    formRecurrenceUntilCalendarValue
                                                                "
                                                                :min-value="
                                                                    formEndDateMinValue
                                                                "
                                                                @update:model-value="
                                                                    (value) =>
                                                                        onFormRecurrenceUntilSelect(
                                                                            value,
                                                                            close,
                                                                        )
                                                                "
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </template>

                                                <template
                                                    v-if="
                                                        formRecurrence.endsType ===
                                                        'count'
                                                    "
                                                >
                                                    <Label
                                                        for="event-recurrence-count"
                                                    >
                                                        <span
                                                            class="inline-flex items-center gap-1.5"
                                                        >
                                                            <span
                                                                >Number of
                                                                occurrences</span
                                                            >
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    as-child
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="inline-flex text-muted-foreground hover:text-foreground"
                                                                        aria-label="Occurrence count help"
                                                                    >
                                                                        <CircleHelp
                                                                            class="size-3.5"
                                                                        />
                                                                    </button>
                                                                </TooltipTrigger>
                                                                <TooltipContent
                                                                    side="top"
                                                                    class="max-w-72 text-xs"
                                                                >
                                                                    Total number
                                                                    of
                                                                    occurrences
                                                                    before
                                                                    recurrence
                                                                    stops,
                                                                    including
                                                                    the first
                                                                    one.
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </span>
                                                    </Label>
                                                    <Input
                                                        id="event-recurrence-count"
                                                        v-model.number="
                                                            formRecurrence.occurrenceCount
                                                        "
                                                        type="number"
                                                        min="1"
                                                        class="max-w-32"
                                                    />
                                                </template>
                                            </div>

                                            <div
                                                v-if="
                                                    formRecurrence.frequency !==
                                                        'none' &&
                                                    recurrenceValidationErrors.length >
                                                        0
                                                "
                                                class="rounded-md border border-destructive/30 bg-destructive/5 p-2.5"
                                            >
                                                <p
                                                    class="text-xs font-medium text-destructive"
                                                >
                                                    Please fix the following:
                                                </p>
                                                <ul
                                                    class="mt-1.5 list-disc space-y-1 pl-4 text-xs text-destructive"
                                                >
                                                    <li
                                                        v-for="message in recurrenceValidationErrors"
                                                        :key="message"
                                                    >
                                                        {{ message }}
                                                    </li>
                                                </ul>
                                            </div>

                                            <div
                                                v-if="
                                                    formRecurrence.frequency !==
                                                        'none' &&
                                                    recurrenceValidationHints.length >
                                                        0
                                                "
                                                class="rounded-md border border-border/60 bg-muted/30 p-2.5"
                                            >
                                                <p
                                                    class="text-xs font-medium text-foreground"
                                                >
                                                    Heads up
                                                </p>
                                                <ul
                                                    class="mt-1.5 list-disc space-y-1 pl-4 text-xs text-muted-foreground"
                                                >
                                                    <li
                                                        v-for="hint in recurrenceValidationHints"
                                                        :key="hint"
                                                    >
                                                        {{ hint }}
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <p
                                            v-if="
                                                !showRecurrenceSection &&
                                                form.isAllDay &&
                                                formStartDate &&
                                                formEndDate &&
                                                formEndDate.compare(
                                                    formStartDate as DateValue,
                                                ) !== 0
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            Recurrence is hidden for multi-day
                                            all-day events (set end date equal
                                            to start, or turn off all-day).
                                        </p>

                                        <div class="space-y-2">
                                            <Label
                                                for="event-location"
                                                :class="optionalLabelRowClass"
                                            >
                                                <span>Location</span>
                                                <Badge variant="outline">
                                                    Optional
                                                </Badge>
                                                <Button
                                                    v-if="form.location !== ''"
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="
                                                        clearFieldButtonClass
                                                    "
                                                    aria-label="Clear event location"
                                                    @click="form.location = ''"
                                                >
                                                    <X class="size-3.5" />
                                                </Button>
                                            </Label>
                                            <InputGroup>
                                                <InputGroupAddon
                                                    align="inline-start"
                                                >
                                                    <MapPin
                                                        class="size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                </InputGroupAddon>
                                                <InputGroupInput
                                                    id="event-location"
                                                    v-model="form.location"
                                                    type="text"
                                                    placeholder="Meeting room / Zoom link"
                                                />
                                            </InputGroup>
                                        </div>

                                        <div class="space-y-2">
                                            <Label
                                                :class="optionalLabelRowClass"
                                            >
                                                <span>Category</span>
                                                <Button
                                                    v-if="
                                                        form.categoryId !== null
                                                    "
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="
                                                        clearFieldButtonClass
                                                    "
                                                    aria-label="Clear category"
                                                    @click="
                                                        form.categoryId = null
                                                    "
                                                >
                                                    <X class="size-3.5" />
                                                </Button>
                                            </Label>
                                            <Select
                                                :model-value="
                                                    form.categoryId === null
                                                        ? ''
                                                        : String(
                                                              form.categoryId,
                                                          )
                                                "
                                                @update:model-value="
                                                    onFormCategoryChange
                                                "
                                            >
                                                <SelectTrigger
                                                    class="w-full font-normal text-foreground"
                                                >
                                                    <span
                                                        class="flex min-w-0 flex-1 items-center gap-2 text-left"
                                                        :class="
                                                            form.categoryId !==
                                                            null
                                                                ? 'text-foreground'
                                                                : 'text-muted-foreground'
                                                        "
                                                    >
                                                        <Tag
                                                            class="size-3.5 shrink-0 text-muted-foreground"
                                                            aria-hidden="true"
                                                        />
                                                        <Badge
                                                            v-if="
                                                                form.categoryId !==
                                                                null
                                                            "
                                                            variant="outline"
                                                            class="h-5 px-2 text-[11px] font-medium"
                                                            :class="
                                                                cn(
                                                                    getSheetEventListToneClassesByColorKey(
                                                                        categoryColorKeyById(
                                                                            form.categoryId,
                                                                        ),
                                                                    ).badge,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                categoryNameById(
                                                                    form.categoryId,
                                                                )
                                                            }}
                                                        </Badge>
                                                        <span
                                                            v-else
                                                            class="min-w-0 truncate"
                                                        >
                                                            Select category
                                                        </span>
                                                    </span>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="category in availableCategories"
                                                        :key="category.id"
                                                        :value="
                                                            String(category.id)
                                                        "
                                                    >
                                                        <span
                                                            class="flex items-center gap-2"
                                                        >
                                                            <span
                                                                class="size-2.5 shrink-0 rounded-full bg-current ring-1 ring-border/60"
                                                                :class="
                                                                    getCalendarEventCategoryChipStylesByColorKey(
                                                                        category.colorKey,
                                                                    ).icon
                                                                "
                                                                aria-hidden="true"
                                                            />
                                                            <span>{{
                                                                category.name
                                                            }}</span>
                                                        </span>
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div class="space-y-2">
                                            <Label
                                                for="event-notes"
                                                :class="optionalLabelRowClass"
                                            >
                                                <span>Notes</span>
                                                <Badge variant="outline">
                                                    Optional
                                                </Badge>
                                                <Button
                                                    v-if="form.notes !== ''"
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="
                                                        clearFieldButtonClass
                                                    "
                                                    aria-label="Clear event notes"
                                                    @click="form.notes = ''"
                                                >
                                                    <X class="size-3.5" />
                                                </Button>
                                            </Label>
                                            <Textarea
                                                id="event-notes"
                                                v-model="form.notes"
                                                rows="5"
                                                placeholder="Agenda, attendees, reminders..."
                                            />
                                        </div>
                                        <div
                                            v-if="
                                                view === 'edit' &&
                                                activeEvent?.recurrence
                                            "
                                            class="space-y-2 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5"
                                        >
                                            <div
                                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Excluded occurrences
                                            </div>
                                            <p
                                                v-if="
                                                    skippedOccurrencesPendingRestore.length ===
                                                    0
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                No occurrence has been excluded.
                                            </p>
                                            <div v-else class="space-y-1.5">
                                                <div
                                                    v-for="dayKey in skippedOccurrencesPendingRestore"
                                                    :key="dayKey"
                                                    class="flex items-center justify-between gap-2 rounded-md border border-border/60 bg-background/70 px-2 py-1.5"
                                                >
                                                    <span
                                                        class="text-sm text-foreground"
                                                    >
                                                        {{
                                                            formatOccurrenceDayKey(
                                                                dayKey,
                                                            )
                                                        }}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-7 px-2 text-xs"
                                                        @click="
                                                            toggleRestoredOccurrenceDate(
                                                                dayKey,
                                                            )
                                                        "
                                                    >
                                                        Restore
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </ScrollArea>
                        </template>
                    </div>
                </div>

                <div class="border-t border-border/60 px-4 py-3">
                    <div
                        class="grid grid-cols-1 gap-2"
                        :class="
                            view === 'detail'
                                ? 'sm:grid-cols-3'
                                : 'sm:grid-cols-2'
                        "
                    >
                        <Button
                            v-if="view !== 'list'"
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="goBack"
                        >
                            Back
                        </Button>
                        <Button
                            v-if="
                                view === 'detail' &&
                                props.canManageEvents &&
                                activeEvent?.eventKind !== 'birthday'
                            "
                            type="button"
                            variant="destructive"
                            class="w-full"
                            @click="openDeleteConfirmDialog"
                        >
                            <Trash2 class="size-4" />
                            Delete
                        </Button>
                        <Button
                            v-if="
                                view === 'detail' &&
                                props.canManageEvents &&
                                activeEvent?.eventKind !== 'birthday'
                            "
                            type="button"
                            variant="default"
                            class="w-full"
                            @click="openEditEvent"
                        >
                            <Pencil class="size-4" />
                            Edit
                        </Button>
                        <Button
                            v-if="view === 'list'"
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="open = false"
                        >
                            Close
                        </Button>
                        <Button
                            v-if="view === 'list' && props.canCreateEvent"
                            type="button"
                            class="w-full"
                            @click="openNewEvent"
                        >
                            <CalendarPlus class="size-4" />
                            New Event
                        </Button>
                        <Button
                            v-if="
                                (view === 'new' && props.canCreateEvent) ||
                                (view === 'edit' && props.canManageEvents)
                            "
                            type="button"
                            class="w-full sm:col-start-2"
                            :disabled="!canSaveForm || isSubmitting"
                            @click="submitCalendarForm"
                        >
                            <Save class="size-4" />
                            {{
                                view === 'edit' ? 'Save Changes' : 'Save Event'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </SheetContent>

        <AlertDialog v-model:open="isDeleteConfirmOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle> Delete event? </AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ deleteDialogDescription }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div v-if="isActiveRecurringOccurrence" class="space-y-1.5">
                    <Label
                        class="block text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Apply deletion to
                    </Label>
                    <Select
                        :model-value="deleteApplyScope"
                        @update:model-value="
                            (value) => {
                                if (
                                    value === 'single_occurrence' ||
                                    value === 'entire_series'
                                ) {
                                    deleteApplyScope = value;
                                }
                            }
                        "
                    >
                        <SelectTrigger class="w-full">
                            <span>
                                {{
                                    deleteApplyScope === 'single_occurrence'
                                        ? 'This occurrence only'
                                        : 'Entire series'
                                }}
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="single_occurrence">
                                This occurrence only
                            </SelectItem>
                            <SelectItem value="entire_series">
                                Entire series
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel> Cancel </AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="isDeleting"
                        @click="confirmDeleteActiveEvent"
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </Sheet>
</template>
