<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { DateValue } from '@internationalized/date';
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import {
    Banknote,
    Calendar,
    CalendarPlus,
    Check,
    ChevronDownIcon,
    Clock3,
    Filter,
    Pencil,
    Repeat,
    Save,
    Search,
    Tag,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { CalendarRecurrenceEnds } from '@/components/calendar/calendar-events';
import {
    expandHolidayOccurrencesInRange,
    formatHolidayDateRangeLabel,
    formatHolidayRecurrenceSummary,
} from '@/components/calendar/holiday-rule-expansion';
import {
    formatHolidayTypePaySummary,
    getHolidaySheetListToneClasses,
    holidayTypeColorOptions,
    resolveHolidayType,
    resolveHolidayTypeName,
} from '@/components/calendar/holiday-types-seed';
import type { HolidayTypeDefinition } from '@/components/calendar/holiday-types-seed';
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
    SelectValue,
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
import { appToast } from '@/lib/app-toast-client';
import type {
    HolidayRule,
    HolidayRuleRecurrence,
} from '@/pages/Attendance/attendanceRulesTypes';
import calendar from '@/routes/calendar';

type SheetView = 'list' | 'detail' | 'new' | 'edit';
type HolidayRangeFilter = 'today' | 'week' | 'month' | 'year' | 'custom';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        types: HolidayTypeDefinition[];
        listEndpoint?: string;
        startView?: 'list' | 'new';
        defaultRangeFilter?: HolidayRangeFilter;
        initialDayFilter?: string | null;
        initialHolidayId?: number | null;
        canManage: boolean;
        /** `YYYY-MM` for partial Inertia reloads after mutations. */
        calendarDisplayMonth: string;
    }>(),
    {
        title: 'Holiday Calendar',
        description:
            'Find holidays by date, name, or type. Add or edit entries if you have access.',
        listEndpoint: '',
        startView: 'list',
        defaultRangeFilter: 'month',
        initialDayFilter: null,
        initialHolidayId: null,
    },
);

const open = defineModel<boolean>('open', { required: true });
const holidays = defineModel<HolidayRule[]>('holidays', { required: true });

const view = ref<SheetView>('list');
const activeHoliday = ref<HolidayRule | null>(null);

const searchQuery = ref('');
const rangeFilter = ref<HolidayRangeFilter>(normalizedDefaultRangeFilter());
const isCustomRangeOpen = ref(false);
const customFrom = ref<DateValue | undefined>(undefined);
const customTo = ref<DateValue | undefined>(undefined);

const selectedTypeIds = ref<string[]>([]);
const formError = ref<string | null>(null);
const isPersistingHoliday = ref(false);
const isDeletingHoliday = ref(false);

const isTypeFilterOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const pendingDeleteHoliday = ref<HolidayRule | null>(null);
const listHolidays = ref<HolidayRule[]>([]);
const isListLoading = ref(false);
const listRequestId = ref(0);

const optionalLabelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';
const clearFieldButtonClass =
    'absolute right-0 top-1/2 z-[1] size-6 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground';

type Draft = {
    name: string;
    type_id: string;
    notes: string;
    repeatYearly: boolean;
    recurrenceEndsType: 'never' | 'until' | 'count';
    recurrenceYearCount: number;
};

const formStartDate = ref<DateValue | undefined>(undefined);
const formEndDate = ref<DateValue | undefined>(undefined);
const recurrenceUntilDate = ref<DateValue | undefined>(undefined);

function emptyDraft(): Draft {
    const firstTypeId =
        props.types.find((t) => t.kind === 'builtin')?.id ??
        props.types[0]?.id ??
        '';

    return {
        name: '',
        type_id: firstTypeId,
        notes: '',
        repeatYearly: false,
        recurrenceEndsType: 'never',
        recurrenceYearCount: 5,
    };
}

const typesSelectableForHolidayForm = computed(() => props.types);

const draft = ref<Draft>(emptyDraft());

/** CalendarDate / plain calendar day (model-value from `Calendar`). */
function dateValueToIso(dv: unknown): string {
    const v = dv as { year: number; month: number; day: number };

    return `${v.year}-${String(v.month).padStart(2, '0')}-${String(v.day).padStart(2, '0')}`;
}

function buildRecurrenceFromDraft(): HolidayRuleRecurrence | null {
    if (!draft.value.repeatYearly) {
        return null;
    }

    const ends: CalendarRecurrenceEnds =
        draft.value.recurrenceEndsType === 'never'
            ? { type: 'never' }
            : draft.value.recurrenceEndsType === 'count'
              ? {
                    type: 'count',
                    count: Math.max(
                        1,
                        Math.floor(draft.value.recurrenceYearCount),
                    ),
                }
              : {
                    type: 'until',
                    date: recurrenceUntilDate.value
                        ? dateValueToIso(recurrenceUntilDate.value)
                        : dateValueToIso(formEndDate.value),
                };

    return {
        frequency: 'yearly',
        interval: 1,
        ends,
    };
}

/**
 * Inclusive first/last calendar day for the list filter (matches prior “holiday in range” behavior using expanded days).
 */
function getListFilterInclusiveDayBounds(): { start: Date; end: Date } | null {
    const b = dateBounds.value;
    const dayOnly = (d: Date) =>
        new Date(d.getFullYear(), d.getMonth(), d.getDate());

    switch (rangeFilter.value) {
        case 'today':
            return {
                start: dayOnly(b.startOfToday),
                end: dayOnly(b.startOfToday),
            };
        case 'week':
            return {
                start: dayOnly(b.startOfWeek),
                end: dayOnly(
                    new Date(b.endOfWeekExclusive.getTime() - 86400000),
                ),
            };
        case 'month':
            return {
                start: dayOnly(b.startOfMonth),
                end: dayOnly(
                    new Date(b.endOfMonthExclusive.getTime() - 86400000),
                ),
            };
        case 'year':
            return {
                start: dayOnly(b.startOfYear),
                end: dayOnly(
                    new Date(b.endOfYearExclusive.getTime() - 86400000),
                ),
            };
        default: {
            if (!parsedCustomFrom.value) {
                return null;
            }

            const last = parsedCustomTo.value ?? parsedCustomFrom.value;

            return {
                start: dayOnly(parsedCustomFrom.value),
                end: dayOnly(last),
            };
        }
    }
}

const calendarFormStartValue = computed(
    () => formStartDate.value as DateValue | undefined,
);
const calendarFormEndValue = computed(
    () => formEndDate.value as DateValue | undefined,
);
const formEndMinValue = computed<DateValue | undefined>(() => {
    if (
        !formStartDate.value ||
        typeof (formStartDate.value as { add?: unknown }).add !== 'function'
    ) {
        return undefined;
    }

    return (
        formStartDate.value as { add: (payload: { days: number }) => DateValue }
    ).add({
        days: 0,
    });
});
const calendarRecurrenceUntilValue = computed(
    () => recurrenceUntilDate.value as DateValue | undefined,
);

const formStartDisplayLabel = computed(() => {
    const d = formStartDate.value ? dateValueToDate(formStartDate.value) : null;

    return d ? formatShortDate(d) : 'Select start date';
});

const formEndDisplayLabel = computed(() => {
    const d = formEndDate.value ? dateValueToDate(formEndDate.value) : null;

    return d ? formatShortDate(d) : 'Select end date';
});

const recurrenceUntilDisplayLabel = computed(() => {
    const d = recurrenceUntilDate.value
        ? dateValueToDate(recurrenceUntilDate.value)
        : null;

    return d ? formatShortDate(d) : 'Select end date';
});

function onFormStartSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);
    formStartDate.value = normalized;
    close();
}

function onFormEndSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);
    formEndDate.value = normalized;
    close();
}

function onRecurrenceUntilSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);
    recurrenceUntilDate.value = normalized;
    close();
}

function startOfDay(value: Date): Date {
    return new Date(value.getFullYear(), value.getMonth(), value.getDate());
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

function parseDayKey(dayKey: string): DateValue | undefined {
    try {
        return parseDate(dayKey);
    } catch {
        return undefined;
    }
}

const parsedCustomFrom = computed(() => dateValueToDate(customFrom.value));
const parsedCustomTo = computed(() => dateValueToDate(customTo.value));

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
    if (!parsedCustomFrom.value) return false;

    if (!parsedCustomTo.value) return true;

    return parsedCustomFrom.value < parsedCustomTo.value;
});

const fromDisplayLabel = computed(() =>
    parsedCustomFrom.value
        ? formatShortDate(parsedCustomFrom.value)
        : 'Select start date',
);
const toDisplayLabel = computed(() =>
    parsedCustomTo.value
        ? formatShortDate(parsedCustomTo.value)
        : 'Select end date',
);

const dateAnchorLabel = computed(() => {
    if (rangeFilter.value === 'today')
        return formatShortDate(dateBounds.value.startOfToday);
    if (rangeFilter.value === 'week') return formatWeekRange(dateBounds.value);
    if (rangeFilter.value === 'month')
        return `${dateBounds.value.startOfMonth.toLocaleDateString(undefined, {
            month: 'long',
            year: 'numeric',
        })}`;
    if (rangeFilter.value === 'year')
        return `${dateBounds.value.startOfYear.getFullYear()}`;

    if (parsedCustomFrom.value && parsedCustomTo.value)
        return `${formatShortDate(parsedCustomFrom.value)} - ${formatShortDate(parsedCustomTo.value)}`;

    if (parsedCustomFrom.value) return formatFullDate(parsedCustomFrom.value);

    return 'Custom range';
});

const rangeSelectValue = computed(() =>
    rangeFilter.value === 'custom' ? '' : rangeFilter.value,
);

const rangeSelectDisplayLabel = computed(() => {
    if (rangeFilter.value === 'custom') return 'Custom';
    if (rangeFilter.value === 'today') return 'Today';
    if (rangeFilter.value === 'week') return 'This Week';
    if (rangeFilter.value === 'month') return 'This Month';

    return 'This Year';
});

const calendarFromValue = computed(
    () => customFrom.value as DateValue | undefined,
);
const calendarToValue = computed(() => customTo.value as DateValue | undefined);

const toMinValue = computed<DateValue | undefined>(() => {
    if (
        !customFrom.value ||
        typeof (customFrom.value as { add?: unknown }).add !== 'function'
    )
        return undefined;

    return (
        customFrom.value as { add: (payload: { days: number }) => DateValue }
    ).add({
        days: 1,
    });
});

const headerTitle = computed(() => {
    if (view.value === 'detail') return 'Holiday details';
    if (view.value === 'new') return 'New holiday';
    if (view.value === 'edit') {
        const name = draft.value.name.trim();

        return name === '' ? 'Edit holiday' : `Edit ${name}`;
    }

    return props.title ?? 'Holiday Calendar';
});

const headerDescription = computed(() => {
    if (view.value === 'detail' && activeHoliday.value)
        return 'Dates, type, pay behavior, and optional notes for this entry.';
    if (view.value === 'new')
        return 'Set dates, type, optional notes, and optional yearly repetition.';
    if (view.value === 'edit') return 'Update this holiday row.';

    return props.description ?? '';
});

function formatWeekRange(b: typeof dateBounds.value): string {
    return `${formatShortDate(b.startOfWeek)} – ${formatShortDate(
        new Date(b.endOfWeekExclusive.getTime() - 86400000),
    )}`;
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

const selectedTypeSummaryComputed = computed(() => {
    if (selectedTypeIds.value.length === 0) return 'All types';
    if (selectedTypeIds.value.length === 1)
        return resolveHolidayTypeName(props.types, selectedTypeIds.value[0]);

    return `${selectedTypeIds.value.length} types`;
});

const listFilterKey = computed(() =>
    JSON.stringify({
        month: props.calendarDisplayMonth,
        search: searchQuery.value.trim(),
        typeIds: [...selectedTypeIds.value].sort(),
        range: rangeFilter.value,
        customFrom: parsedCustomFrom.value
            ? toIsoKey(parsedCustomFrom.value)
            : null,
        customTo: parsedCustomTo.value ? toIsoKey(parsedCustomTo.value) : null,
    }),
);

const filteredHolidays = computed(() => {
    if (props.listEndpoint.trim() !== '') {
        return listHolidays.value;
    }

    const query = searchQuery.value.trim().toLowerCase();
    const bounds = getListFilterInclusiveDayBounds();
    if (!bounds) {
        return [];
    }

    const occ = expandHolidayOccurrencesInRange(
        holidays.value,
        bounds.start,
        bounds.end,
    );
    const ruleIds = new Set(occ.map((o) => o.rule.id));
    let rows = holidays.value.filter((h) => ruleIds.has(h.id));

    rows = rows.filter((h) => {
        const t = resolveHolidayType(props.types, h.type_id);

        return t !== undefined;
    });

    if (selectedTypeIds.value.length > 0) {
        const allowed = new Set(selectedTypeIds.value);
        rows = rows.filter((h) => allowed.has(h.type_id));
    }

    if (query !== '') {
        rows = rows.filter((h) => {
            const typeRow = resolveHolidayType(props.types, h.type_id);
            const payLine = formatHolidayTypePaySummary(typeRow).toLowerCase();
            const recurSummary =
                formatHolidayRecurrenceSummary(h).toLowerCase();

            const notesLine = (h.notes ?? '').toLowerCase();

            return (
                h.name.toLowerCase().includes(query) ||
                h.start_date.includes(query) ||
                h.end_date.includes(query) ||
                notesLine.includes(query) ||
                resolveHolidayTypeName(props.types, h.type_id)
                    .toLowerCase()
                    .includes(query) ||
                payLine.includes(query) ||
                recurSummary.includes(query)
            );
        });
    }

    rows.sort((a, b) =>
        a.start_date === b.start_date
            ? a.name.localeCompare(b.name)
            : a.start_date.localeCompare(b.start_date),
    );

    return rows;
});

function listRequestQuery(): URLSearchParams {
    const params = new URLSearchParams();
    params.set('month', props.calendarDisplayMonth);
    params.set('page', '1');
    params.set('per_page', '200');
    params.set('search', searchQuery.value.trim());
    if (selectedTypeIds.value.length > 0) {
        params.set('type_ids', selectedTypeIds.value.join(','));
    }
    params.set('range', rangeFilter.value);
    if (rangeFilter.value === 'custom') {
        if (parsedCustomFrom.value) {
            params.set('custom_from', toIsoKey(parsedCustomFrom.value));
        }
        if (parsedCustomTo.value) {
            params.set('custom_to', toIsoKey(parsedCustomTo.value));
        }
    }

    return params;
}

async function fetchHolidayList(): Promise<void> {
    if (props.listEndpoint.trim() === '') {
        listHolidays.value = [];

        return;
    }

    const requestId = listRequestId.value + 1;
    listRequestId.value = requestId;
    isListLoading.value = true;

    try {
        const response = await fetch(
            `${props.listEndpoint}?${listRequestQuery().toString()}`,
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            },
        );
        if (!response.ok) {
            return;
        }

        const payload = await response.json().catch(() => ({}));
        if (requestId !== listRequestId.value) {
            return;
        }

        const rows = Array.isArray((payload as { data?: unknown }).data)
            ? (payload as { data: HolidayRule[] }).data
            : [];
        listHolidays.value = [...rows];
    } finally {
        if (requestId === listRequestId.value) {
            isListLoading.value = false;
        }
    }
}

function resetListFilters(): void {
    searchQuery.value = '';
    selectedTypeIds.value = [];
    rangeFilter.value = normalizedDefaultRangeFilter();
    isCustomRangeOpen.value = false;
    customFrom.value = undefined;
    customTo.value = undefined;
}

function toggleTypeFilter(id: string): void {
    const next = new Set(selectedTypeIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selectedTypeIds.value = [...next];
}

function clearTypeFilters(): void {
    selectedTypeIds.value = [];
}

function isTypeCheckboxSelected(id: string): boolean {
    return selectedTypeIds.value.includes(id);
}

function listToneForHoliday(
    holiday: HolidayRule,
): ReturnType<typeof getHolidaySheetListToneClasses> {
    const def = props.types.find((t) => t.id === holiday.type_id);

    return getHolidaySheetListToneClasses(def?.colorKey);
}

function openHolidayDetail(row: HolidayRule): void {
    activeHoliday.value = row;
    view.value = 'detail';
}

function goBackToList(): void {
    activeHoliday.value = null;
    formError.value = null;
    view.value = 'list';
}

function validateDraft(row: Draft): boolean {
    if (!formStartDate.value || !formEndDate.value) {
        formError.value = 'Start and end dates are required.';

        return false;
    }

    const startIso = dateValueToIso(formStartDate.value);
    const endIso = dateValueToIso(formEndDate.value);

    if (endIso < startIso) {
        formError.value = 'End date must be on or after the start date.';

        return false;
    }

    if (
        row.repeatYearly &&
        row.recurrenceEndsType === 'until' &&
        !recurrenceUntilDate.value
    ) {
        formError.value = 'Choose the last year for yearly repetition.';

        return false;
    }

    if (row.name.trim() === '') {
        formError.value = 'Holiday name is required.';

        return false;
    }
    const typeRow = resolveHolidayType(props.types, row.type_id);
    if (!typeRow) {
        formError.value = 'Choose a valid holiday type.';

        return false;
    }
    formError.value = null;

    return true;
}

function resolveCsrfToken(): string | null {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
}

function firstErrorMessage(payload: unknown): string | null {
    if (!payload || typeof payload !== 'object' || !('errors' in payload)) {
        return null;
    }

    const errors = (payload as { errors?: Record<string, string[] | string> })
        .errors;
    if (!errors || typeof errors !== 'object') {
        return null;
    }

    for (const value of Object.values(errors)) {
        if (Array.isArray(value) && typeof value[0] === 'string') {
            return value[0];
        }
        if (typeof value === 'string' && value !== '') {
            return value;
        }
    }

    return null;
}

function reloadHolidayCalendarPartial(onSuccess?: () => void): void {
    router.get(
        calendar.holidays.url({
            query: { month: props.calendarDisplayMonth },
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
            onSuccess: () => onSuccess?.(),
        },
    );
}

async function persistDraft(existingId?: number): Promise<void> {
    if (!props.canManage || isPersistingHoliday.value) {
        return;
    }

    const row = draft.value;
    if (!validateDraft(row)) {
        return;
    }

    const notesTrimmed = row.notes.trim();

    const body: Record<string, unknown> = {
        start_date: dateValueToIso(formStartDate.value),
        end_date: dateValueToIso(formEndDate.value),
        name: row.name.trim(),
        type_id: row.type_id,
        notes: notesTrimmed === '' ? null : notesTrimmed,
        recurrence: buildRecurrenceFromDraft(),
    };

    isPersistingHoliday.value = true;

    const operation = (async (): Promise<void> => {
        const csrf = resolveCsrfToken();

        if (existingId === undefined) {
            const response = await fetch(
                calendar.organizationHolidays.store.url(),
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    body: JSON.stringify(body),
                },
            );

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(
                    firstErrorMessage(payload) ?? 'Unable to save holiday.',
                );
            }

            reloadHolidayCalendarPartial(() => goBackToList());

            return;
        }

        const response = await fetch(
            calendar.organizationHolidays.update.url({
                organizationHoliday: existingId,
            }),
            {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify(body),
            },
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstErrorMessage(payload) ?? 'Unable to save holiday.',
            );
        }

        const data = (payload as { data?: HolidayRule }).data;
        if (data) {
            activeHoliday.value = data;
        }

        reloadHolidayCalendarPartial(() => {
            view.value = 'detail';
        });
    })();

    try {
        await appToast.promise(operation, {
            loading:
                existingId === undefined
                    ? 'Creating holiday...'
                    : 'Saving holiday...',
            success:
                existingId === undefined
                    ? 'Holiday created.'
                    : 'Holiday updated.',
            error: (error: unknown) =>
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : 'Unable to save holiday.',
        });
    } finally {
        isPersistingHoliday.value = false;
    }
}

function openNewHoliday(): void {
    if (!props.canManage) return;
    const hint = parsedDayHint(props.initialDayFilter);
    draft.value = emptyDraft();
    formStartDate.value = parseDate(hint);
    formEndDate.value = parseDate(hint);
    recurrenceUntilDate.value = undefined;
    formError.value = null;
    view.value = 'new';
}

function parsedDayHint(day: string | null): string {
    return day ?? toIsoKey(startOfDay(new Date()));
}

function toIsoKey(d: Date): string {
    return [
        d.getFullYear(),
        String(d.getMonth() + 1).padStart(2, '0'),
        String(d.getDate()).padStart(2, '0'),
    ].join('-');
}

function openEditFromDetail(): void {
    if (!props.canManage || !activeHoliday.value) return;

    const h = activeHoliday.value;
    const endsType =
        h.recurrence?.ends.type === 'until'
            ? 'until'
            : h.recurrence?.ends.type === 'count'
              ? 'count'
              : 'never';

    draft.value = {
        name: h.name,
        type_id: h.type_id,
        notes: h.notes ?? '',
        repeatYearly: Boolean(h.recurrence?.frequency === 'yearly'),
        recurrenceEndsType: endsType,
        recurrenceYearCount:
            h.recurrence?.ends.type === 'count' ? h.recurrence.ends.count : 5,
    };
    formStartDate.value = parseDate(h.start_date);
    formEndDate.value = parseDate(h.end_date);
    recurrenceUntilDate.value =
        h.recurrence?.ends.type === 'until'
            ? parseDate(h.recurrence.ends.date)
            : undefined;
    formError.value = null;
    view.value = 'edit';
}

function askDeleteFromDetail(): void {
    if (!props.canManage || !activeHoliday.value) return;

    pendingDeleteHoliday.value = activeHoliday.value;
    isDeleteDialogOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    if (
        !props.canManage ||
        !pendingDeleteHoliday.value ||
        isDeletingHoliday.value
    ) {
        return;
    }

    const id = pendingDeleteHoliday.value.id;

    isDeletingHoliday.value = true;

    const operation = (async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(
            calendar.organizationHolidays.destroy.url({
                organizationHoliday: id,
            }),
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
            },
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstErrorMessage(payload) ?? 'Unable to delete holiday.',
            );
        }

        reloadHolidayCalendarPartial(() => {
            pendingDeleteHoliday.value = null;
            isDeleteDialogOpen.value = false;
            goBackToList();
        });
    })();

    try {
        await appToast.promise(operation, {
            loading: 'Deleting holiday...',
            success: 'Holiday deleted.',
            error: (error: unknown) =>
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : 'Unable to delete holiday.',
        });
    } finally {
        isDeletingHoliday.value = false;
    }
}

function applyInitialDayFilter(dayKey: string): void {
    const parsed = parseDayKey(dayKey);
    if (!parsed) return;

    customFrom.value = parsed;
    customTo.value = undefined;
    rangeFilter.value = 'custom';
}

function setQuickRange(filter: Exclude<HolidayRangeFilter, 'custom'>): void {
    customFrom.value = undefined;
    customTo.value = undefined;
    rangeFilter.value = filter;
    isCustomRangeOpen.value = false;
}

function setRangeFilter(value: unknown): void {
    if (typeof value !== 'string' || value === '') return;

    setQuickRange(value as Exclude<HolidayRangeFilter, 'custom'>);
}

function applyCustomRangeFilter(): void {
    if (!isCustomRangeValid.value) return;

    rangeFilter.value = 'custom';
    isCustomRangeOpen.value = false;
}

function clearCustomRangeFilter(): void {
    customFrom.value = undefined;
    customTo.value = undefined;
    rangeFilter.value = normalizedDefaultRangeFilter();
    isCustomRangeOpen.value = false;
}

function normalizedDefaultRangeFilter(): HolidayRangeFilter {
    const allowed: HolidayRangeFilter[] = [
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

function clearCustomFrom(): void {
    customFrom.value = undefined;
}

function clearCustomTo(): void {
    customTo.value = undefined;
}

function onCustomFromSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);
    customFrom.value = normalized;
    close();
}

function onCustomToSelect(value: unknown, close: () => void): void {
    const normalized = normalizeDateValue(value);
    customTo.value = normalized;
    close();
}

function applyOpenFromParent(): void {
    resetListFilters();
    activeHoliday.value = null;
    formError.value = null;

    if (props.initialHolidayId != null) {
        const row = holidays.value.find((h) => h.id === props.initialHolidayId);
        if (row) {
            activeHoliday.value = row;
            view.value = 'detail';

            return;
        }
    }

    if (props.startView === 'new' && props.canManage) {
        const hint = parsedDayHint(props.initialDayFilter);
        draft.value = emptyDraft();
        formStartDate.value = parseDate(hint);
        formEndDate.value = parseDate(hint);
        recurrenceUntilDate.value = undefined;
        view.value = 'new';

        return;
    }

    view.value = 'list';

    if (
        props.initialDayFilter !== null &&
        props.initialDayFilter !== '' &&
        props.startView === 'list'
    ) {
        applyInitialDayFilter(props.initialDayFilter);
    }
}

watch(
    () => open.value,
    (isOpen) => {
        if (!isOpen) return;

        applyOpenFromParent();
    },
);

watch(
    () => [open.value, view.value, props.listEndpoint] as const,
    ([isOpen, activeView]) => {
        if (!isOpen || activeView !== 'list') return;

        void fetchHolidayList();
    },
);

watch(listFilterKey, () => {
    if (!open.value || view.value !== 'list') return;

    void fetchHolidayList();
});

watch(
    () => holidays.value,
    () => {
        if (!open.value || view.value !== 'list') return;

        void fetchHolidayList();
    },
    { deep: true },
);

watch(
    () => props.initialHolidayId,
    (nextId) => {
        if (!open.value || nextId === null || nextId === undefined) return;

        const row = holidays.value.find((h) => h.id === nextId);
        if (row) {
            activeHoliday.value = row;
            view.value = 'detail';
        }
    },
);

watch(
    () => props.initialDayFilter,
    (nextDay) => {
        if (!open.value || !nextDay || view.value !== 'list') return;

        applyInitialDayFilter(nextDay);
    },
);

watch(formStartDate, (next) => {
    if (!next || !formEndDate.value) {
        return;
    }

    if (dateValueToIso(formEndDate.value) < dateValueToIso(next)) {
        formEndDate.value = next;
    }
});

watch(
    () => draft.value.recurrenceEndsType,
    (endsType) => {
        if (
            endsType !== 'until' ||
            recurrenceUntilDate.value ||
            !formEndDate.value
        ) {
            return;
        }

        recurrenceUntilDate.value = formEndDate.value;
    },
);

function submitHolidayFromForm(): void {
    if (view.value === 'edit') {
        if (!activeHoliday.value) return;
        persistDraft(activeHoliday.value.id);

        return;
    }

    persistDraft(undefined);
}

function typeSwatchClass(type: HolidayTypeDefinition): string {
    return (
        holidayTypeColorOptions.find((option) => option.key === type.colorKey)
            ?.swatchClass ?? 'bg-primary'
    );
}

function paySummaryForHoliday(holiday: HolidayRule): string {
    return formatHolidayTypePaySummary(
        resolveHolidayType(props.types, holiday.type_id),
    );
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            side="right"
            class="flex max-h-dvh flex-col p-0 sm:max-w-lg"
        >
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden text-sm">
                <div
                    class="flex min-h-0 flex-1 flex-col gap-0 overflow-hidden px-4"
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
                                            placeholder="Search name, dates, notes, type..."
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
                                                        class="size-4 shrink-0 text-muted-foreground"
                                                    />
                                                    <span class="truncate">{{
                                                        rangeSelectDisplayLabel
                                                    }}</span>
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
                                                    class="w-full justify-start gap-2"
                                                >
                                                    <Calendar
                                                        class="size-4 shrink-0 text-muted-foreground"
                                                    />
                                                    <span
                                                        class="min-w-0 truncate"
                                                        >{{
                                                            dateAnchorLabel
                                                        }}</span
                                                    >
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
                                                        <Badge variant="outline"
                                                            >Optional</Badge
                                                        >
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

                                    <div>
                                        <Popover
                                            v-model:open="isTypeFilterOpen"
                                        >
                                            <PopoverTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    class="w-full justify-start"
                                                >
                                                    <Tag
                                                        class="mr-2 size-4 shrink-0 text-muted-foreground"
                                                    />
                                                    <span
                                                        class="min-w-0 truncate"
                                                        >{{
                                                            selectedTypeSummaryComputed
                                                        }}</span
                                                    >
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                class="w-80 space-y-3 p-3"
                                                align="start"
                                            >
                                                <div
                                                    class="flex items-center justify-between gap-2"
                                                >
                                                    <p
                                                        class="text-sm font-medium text-foreground"
                                                    >
                                                        Holiday types
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 px-2"
                                                        :disabled="
                                                            selectedTypeIds.length ===
                                                            0
                                                        "
                                                        @click="
                                                            clearTypeFilters
                                                        "
                                                    >
                                                        Clear
                                                    </Button>
                                                </div>
                                                <ScrollArea class="max-h-56">
                                                    <div
                                                        class="divide-y divide-border/60 pr-3"
                                                    >
                                                        <button
                                                            v-for="tp in props.types"
                                                            :key="tp.id"
                                                            type="button"
                                                            class="flex w-full items-center gap-2 px-2 py-2 text-left text-sm transition-colors hover:bg-accent/30"
                                                            :class="
                                                                isTypeCheckboxSelected(
                                                                    tp.id,
                                                                )
                                                                    ? 'bg-accent/40 text-foreground'
                                                                    : 'text-muted-foreground'
                                                            "
                                                            @click="
                                                                toggleTypeFilter(
                                                                    tp.id,
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="size-2.5 shrink-0 rounded-full ring-1 ring-border/60"
                                                                :class="
                                                                    typeSwatchClass(
                                                                        tp,
                                                                    )
                                                                "
                                                            />
                                                            <span
                                                                class="min-w-0 flex-1 truncate font-medium"
                                                                >{{
                                                                    tp.name
                                                                }}</span
                                                            >
                                                            <Check
                                                                v-if="
                                                                    isTypeCheckboxSelected(
                                                                        tp.id,
                                                                    )
                                                                "
                                                                class="size-4 shrink-0 text-primary"
                                                            />
                                                        </button>
                                                    </div>
                                                </ScrollArea>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
                                </div>

                                <ScrollArea class="mt-3 min-h-0 flex-1">
                                    <div class="px-1.5 pr-3 pb-8">
                                        <div
                                            v-if="
                                                isListLoading &&
                                                filteredHolidays.length === 0
                                            "
                                            class="mb-2 rounded-md border border-dashed border-border/70 bg-muted/20 p-3 text-muted-foreground"
                                        >
                                            Loading holidays...
                                        </div>
                                        <div
                                            v-if="filteredHolidays.length === 0"
                                            class="rounded-md border border-dashed border-border/70 bg-muted/20 p-4 text-muted-foreground"
                                        >
                                            No matching holidays found.
                                        </div>
                                        <div v-else class="space-y-2">
                                            <button
                                                v-for="row in filteredHolidays"
                                                :key="row.id"
                                                type="button"
                                                class="flex w-full cursor-pointer items-start justify-between gap-3 rounded-md border px-3 py-2 text-left text-sm transition-colors"
                                                :class="
                                                    listToneForHoliday(row).card
                                                "
                                                @click="openHolidayDetail(row)"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="truncate font-semibold text-foreground"
                                                    >
                                                        {{ row.name }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            formatHolidayDateRangeLabel(
                                                                row.start_date,
                                                                row.end_date,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            row.recurrence
                                                                ?.frequency ===
                                                            'yearly'
                                                        "
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            formatHolidayRecurrenceSummary(
                                                                row,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="truncate text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            paySummaryForHoliday(
                                                                row,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant="outline"
                                                    class="mt-0.5 shrink-0 border-0 font-normal"
                                                    :class="
                                                        listToneForHoliday(row)
                                                            .badge
                                                    "
                                                >
                                                    {{
                                                        resolveHolidayTypeName(
                                                            props.types,
                                                            row.type_id,
                                                        )
                                                    }}
                                                </Badge>
                                            </button>
                                        </div>
                                    </div>
                                </ScrollArea>

                                <div
                                    v-if="canManage"
                                    class="-mx-4 shrink-0 border-t border-border/60 px-4 py-3"
                                >
                                    <div
                                        class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                    >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="w-full"
                                            @click="open = false"
                                        >
                                            Close
                                        </Button>
                                        <Button
                                            type="button"
                                            class="w-full"
                                            @click="openNewHoliday"
                                        >
                                            <CalendarPlus class="size-4" />
                                            Add Holiday
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="view === 'detail'">
                            <template
                                v-for="h in activeHoliday
                                    ? [activeHoliday]
                                    : []"
                                :key="h.id"
                            >
                                <ScrollArea class="min-h-0 flex-1">
                                    <div class="space-y-3 px-1.5 pr-3 pb-8">
                                        <div
                                            class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Holiday
                                            </p>
                                            <p
                                                class="mt-1.5 text-base font-semibold text-foreground"
                                            >
                                                {{ h.name }}
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
                                                <p
                                                    class="flex items-start gap-2"
                                                >
                                                    <Calendar
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>{{
                                                        formatHolidayDateRangeLabel(
                                                            h.start_date,
                                                            h.end_date,
                                                        )
                                                    }}</span>
                                                </p>
                                                <p
                                                    class="flex items-start gap-2"
                                                >
                                                    <Clock3
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>All day</span>
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                h.recurrence?.frequency ===
                                                'yearly'
                                            "
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
                                                    formatHolidayRecurrenceSummary(
                                                        h,
                                                    )
                                                }}</span>
                                            </p>
                                        </div>

                                        <div
                                            class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                        >
                                            <div
                                                class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                            >
                                                <p
                                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                                >
                                                    Type
                                                </p>
                                                <div
                                                    class="mt-1.5 flex items-center gap-2"
                                                >
                                                    <Tag
                                                        class="size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <Badge
                                                        variant="outline"
                                                        class="border-0 font-normal"
                                                        :class="
                                                            listToneForHoliday(
                                                                h,
                                                            ).badge
                                                        "
                                                    >
                                                        {{
                                                            resolveHolidayTypeName(
                                                                props.types,
                                                                h.type_id,
                                                            )
                                                        }}
                                                    </Badge>
                                                </div>
                                            </div>

                                            <div
                                                class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                            >
                                                <p
                                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                                >
                                                    Pay
                                                </p>
                                                <p
                                                    class="mt-1.5 flex items-start gap-2 text-foreground"
                                                >
                                                    <Banknote
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span class="min-w-0">{{
                                                        paySummaryForHoliday(h)
                                                    }}</span>
                                                </p>
                                            </div>
                                        </div>

                                        <div
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
                                                    h.notes ||
                                                    'No notes provided.'
                                                }}
                                            </p>
                                        </div>

                                        <div
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
                                                    {{ h.setBy || '—' }}
                                                </p>
                                                <p>
                                                    <span
                                                        class="text-muted-foreground"
                                                        >Last edited by:</span
                                                    >
                                                    {{ h.lastEditedBy || '—' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </ScrollArea>
                                <div
                                    class="-mx-4 shrink-0 border-t border-border/60 px-4 py-3"
                                >
                                    <div
                                        class="grid grid-cols-1 gap-2"
                                        :class="
                                            canManage ? 'sm:grid-cols-3' : ''
                                        "
                                    >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="w-full"
                                            @click="goBackToList"
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            v-if="canManage"
                                            type="button"
                                            variant="destructive"
                                            class="w-full"
                                            @click="askDeleteFromDetail"
                                        >
                                            <Trash2 class="size-4" />
                                            Delete
                                        </Button>
                                        <Button
                                            v-if="canManage"
                                            type="button"
                                            class="w-full"
                                            @click="openEditFromDetail"
                                        >
                                            <Pencil class="size-4" />
                                            Edit
                                        </Button>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <template v-else-if="view === 'new' || view === 'edit'">
                            <ScrollArea class="min-h-0 flex-1">
                                <div class="space-y-4 px-1.5 pt-2 pr-3 pb-8">
                                    <div class="grid w-full gap-4">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div class="grid gap-2">
                                                <Label
                                                    :class="
                                                        optionalLabelRowClass
                                                    "
                                                >
                                                    <span>Start date</span>
                                                </Label>
                                                <Popover v-slot="{ close }">
                                                    <PopoverTrigger as-child>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            class="h-11 w-full justify-between gap-2 text-left font-normal"
                                                            aria-label="Select holiday start date"
                                                        >
                                                            <span
                                                                class="min-w-0 flex-1 truncate"
                                                                :class="
                                                                    formStartDate
                                                                        ? 'text-foreground'
                                                                        : 'text-muted-foreground'
                                                                "
                                                            >
                                                                {{
                                                                    formStartDisplayLabel
                                                                }}
                                                            </span>
                                                            <ChevronDownIcon
                                                                class="size-4 shrink-0 opacity-50"
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
                                                                calendarFormStartValue
                                                            "
                                                            @update:model-value="
                                                                (value) =>
                                                                    onFormStartSelect(
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
                                                    <span>End date</span>
                                                </Label>
                                                <Popover v-slot="{ close }">
                                                    <PopoverTrigger as-child>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            class="h-11 w-full justify-between gap-2 text-left font-normal"
                                                            aria-label="Select holiday end date"
                                                        >
                                                            <span
                                                                class="min-w-0 flex-1 truncate"
                                                                :class="
                                                                    formEndDate
                                                                        ? 'text-foreground'
                                                                        : 'text-muted-foreground'
                                                                "
                                                            >
                                                                {{
                                                                    formEndDisplayLabel
                                                                }}
                                                            </span>
                                                            <ChevronDownIcon
                                                                class="size-4 shrink-0 opacity-50"
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
                                                                calendarFormEndValue
                                                            "
                                                            :min-value="
                                                                formEndMinValue
                                                            "
                                                            @update:model-value="
                                                                (value) =>
                                                                    onFormEndSelect(
                                                                        value,
                                                                        close,
                                                                    )
                                                            "
                                                        />
                                                    </PopoverContent>
                                                </Popover>
                                            </div>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="h-name"
                                                >Holiday name</Label
                                            >
                                            <Input
                                                id="h-name"
                                                v-model="draft.name"
                                                class="h-11"
                                                placeholder="e.g. National Heroes Day"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                for="h-notes"
                                                class="flex flex-wrap items-center gap-2 font-medium"
                                            >
                                                <span>Notes</span>
                                                <Badge
                                                    variant="outline"
                                                    class="border-0 px-1.5 py-0 text-[10px] font-normal text-muted-foreground"
                                                >
                                                    Optional
                                                </Badge>
                                            </Label>
                                            <Textarea
                                                id="h-notes"
                                                v-model="draft.notes"
                                                rows="3"
                                                class="min-h-20 resize-y"
                                                placeholder="Policy notes, proclamation reference, etc."
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Holiday type</Label>
                                            <Select v-model="draft.type_id">
                                                <SelectTrigger class="w-full"
                                                    ><SelectValue
                                                        placeholder="Choose type"
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="tp in typesSelectableForHolidayForm"
                                                        :key="tp.id"
                                                        :value="tp.id"
                                                    >
                                                        {{ tp.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                Pay behavior follows this type.
                                                Change it under Holiday types
                                                settings.
                                            </p>
                                        </div>
                                        <div
                                            class="flex items-center justify-between gap-3 rounded-lg border border-border/60 bg-muted/15 px-3 py-2.5"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="text-sm font-medium text-foreground"
                                                >
                                                    Repeat yearly
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Same month and day span each
                                                    calendar year.
                                                </p>
                                            </div>
                                            <Switch
                                                v-model="draft.repeatYearly"
                                            />
                                        </div>
                                        <div
                                            v-if="draft.repeatYearly"
                                            class="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3"
                                        >
                                            <div
                                                class="flex items-center gap-2 text-sm font-medium text-foreground"
                                            >
                                                <Repeat
                                                    class="size-4 shrink-0 text-muted-foreground"
                                                />
                                                Repetition
                                            </div>
                                            <div class="grid gap-2">
                                                <Label
                                                    class="text-xs text-muted-foreground"
                                                    >Ends</Label
                                                >
                                                <Select
                                                    v-model="
                                                        draft.recurrenceEndsType
                                                    "
                                                >
                                                    <SelectTrigger
                                                        class="w-full font-normal"
                                                    >
                                                        <SelectValue
                                                            placeholder="How it ends"
                                                        />
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
                                                            Until a date
                                                        </SelectItem>
                                                        <SelectItem
                                                            value="count"
                                                        >
                                                            After a number of
                                                            years
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div
                                                v-if="
                                                    draft.recurrenceEndsType ===
                                                    'until'
                                                "
                                                class="grid gap-2"
                                            >
                                                <Label
                                                    class="text-xs text-muted-foreground"
                                                    >Until</Label
                                                >
                                                <Popover v-slot="{ close }">
                                                    <PopoverTrigger as-child>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            class="h-11 w-full justify-between gap-2 text-left font-normal"
                                                            aria-label="Select last repetition date"
                                                        >
                                                            <span
                                                                class="min-w-0 flex-1 truncate"
                                                                :class="
                                                                    recurrenceUntilDate
                                                                        ? 'text-foreground'
                                                                        : 'text-muted-foreground'
                                                                "
                                                            >
                                                                {{
                                                                    recurrenceUntilDisplayLabel
                                                                }}
                                                            </span>
                                                            <ChevronDownIcon
                                                                class="size-4 shrink-0 opacity-50"
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
                                                                calendarRecurrenceUntilValue
                                                            "
                                                            @update:model-value="
                                                                (value) =>
                                                                    onRecurrenceUntilSelect(
                                                                        value,
                                                                        close,
                                                                    )
                                                            "
                                                        />
                                                    </PopoverContent>
                                                </Popover>
                                            </div>
                                            <div
                                                v-if="
                                                    draft.recurrenceEndsType ===
                                                    'count'
                                                "
                                                class="grid gap-2"
                                            >
                                                <Label
                                                    for="h-rec-count"
                                                    class="text-xs text-muted-foreground"
                                                    >Years</Label
                                                >
                                                <Input
                                                    id="h-rec-count"
                                                    v-model.number="
                                                        draft.recurrenceYearCount
                                                    "
                                                    type="number"
                                                    min="1"
                                                    max="500"
                                                    class="h-11"
                                                />
                                            </div>
                                        </div>
                                        <p
                                            v-if="formError"
                                            class="text-sm text-destructive"
                                        >
                                            {{ formError }}
                                        </p>
                                    </div>
                                </div>
                            </ScrollArea>
                            <div
                                class="-mx-4 shrink-0 border-t border-border/60 px-4 py-3"
                            >
                                <div
                                    class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="w-full"
                                        @click="goBackToList"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        class="w-full"
                                        @click="submitHolidayFromForm"
                                    >
                                        <Save class="size-4" />
                                        {{
                                            view === 'edit'
                                                ? 'Save changes'
                                                : 'Save Holiday'
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <AlertDialog v-model:open="isDeleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete holiday?</AlertDialogTitle>
                <AlertDialogDescription>
                    Remove
                    <span class="font-medium text-foreground">{{
                        pendingDeleteHoliday?.name ?? 'this holiday'
                    }}</span>
                    from the local list?
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="confirmDelete"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
