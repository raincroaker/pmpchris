<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import {
    Calendar as CalendarIcon,
    ChevronDown,
    Eye,
    FileSpreadsheet,
    Search,
} from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import TeamIndexDateRangePickers from '@/components/hris/TeamIndexDateRangePickers.vue';
import TeamTableSortHeader from '@/components/hris/TeamTableSortHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { TooltipProvider } from '@/components/ui/tooltip';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    isoFirstDayOfMonth,
    isoLastDayOfMonth,
} from '@/lib/calendarMonthRange';
import AttendanceEntryViewContent from '@/pages/Attendance/AttendanceEntryViewContent.vue';
import AttendanceGrossNetColumnHeader from '@/pages/Attendance/AttendanceGrossNetColumnHeader.vue';
import type {
    TeamAttendanceRecordStatus,
    TeamAttendanceRecordingStyleFilter,
    TeamAttendanceRow,
    TeamAttendanceStatusFilter,
} from '@/pages/Attendance/teamAttendanceTypes';
import {
    attendanceActualDutyGrossDisplay,
    attendanceNetWithinScheduledOverlapDisplay,
    clockInOutDisplay,
    attendanceStatusBadgeClass,
    attendanceStatusLabel,
    punctualityBadgeClass,
    punctualityLabel,
} from '@/pages/Attendance/teamAttendanceUi';
import {
    team as attendanceTeam,
    my as attendanceMy,
} from '@/routes/attendance';
import { dtrMockSample } from '@/routes/attendance/reports';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Attendance', href: attendanceMy() },
];

type MyAttendanceFiltersProp = {
    page: number;
    per_page: number;
    q: string;
    date_from: string;
    date_to: string;
    status: TeamAttendanceStatusFilter;
    recording_style: TeamAttendanceRecordingStyleFilter;
    sort: 'work_date';
    direction: 'asc' | 'desc';
};

type MyAttendancePaginator = {
    data: TeamAttendanceRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = withDefaults(
    defineProps<{
        myAttendanceDays: MyAttendancePaginator;
        myAttendanceFilters: MyAttendanceFiltersProp;
        hasEmployeeRecord: boolean;
    }>(),
    {
        myAttendanceDays: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
            from: null,
            to: null,
        }),
        myAttendanceFilters: () => ({
            page: 1,
            per_page: 10,
            q: '',
            date_from: isoFirstDayOfMonth(new Date()),
            date_to: isoLastDayOfMonth(new Date()),
            status: 'all',
            recording_style: 'simple',
            sort: 'work_date',
            direction: 'desc',
        }),
        hasEmployeeRecord: true,
    },
);

const page = usePage<{
    auth: {
        user: { name?: string; email?: string } | null;
    };
    branchContext: { id: number; code: string; name: string } | null;
}>();

const chartBranchId = computed(() => page.props.branchContext?.id ?? null);

const tablePlainHeadClass = 'font-medium text-muted-foreground';
const dialogScrollAreaClass =
    'max-h-[min(70vh,520px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

function normalizedMyAttendanceFilters(): MyAttendanceFiltersProp {
    const f = props.myAttendanceFilters;

    return {
        page: f.page ?? 1,
        per_page: f.per_page ?? 10,
        q: f.q ?? '',
        date_from: f.date_from ?? isoFirstDayOfMonth(new Date()),
        date_to: f.date_to ?? isoLastDayOfMonth(new Date()),
        status: (f.status ?? 'all') as TeamAttendanceStatusFilter,
        recording_style:
            (f.recording_style ?? 'simple') as TeamAttendanceRecordingStyleFilter,
        sort: 'work_date',
        direction: (f.direction ?? 'desc') === 'asc' ? 'asc' : 'desc',
    };
}

function buildQuery(
    overrides: Partial<{
        page: number;
        per_page: number;
        q: string;
        date_from: string;
        date_to: string;
        status: TeamAttendanceStatusFilter;
        recording_style: TeamAttendanceRecordingStyleFilter;
        sort: 'work_date';
        direction: 'asc' | 'desc';
    }> = {},
): Record<string, string | number> {
    const merged = { ...normalizedMyAttendanceFilters(), ...overrides };
    const pageNumber =
        overrides.page !== undefined
            ? overrides.page
            : props.myAttendanceDays.current_page;

    const q: Record<string, string | number> = {
        page: pageNumber,
        per_page: merged.per_page,
        date_from: merged.date_from,
        date_to: merged.date_to,
        status: merged.status,
        recording_style: merged.recording_style,
        sort: merged.sort,
        direction: merged.direction,
    };

    const trimmed = merged.q.trim();
    if (trimmed !== '') {
        q.q = trimmed;
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        page: number;
        per_page: number;
        q: string;
        date_from: string;
        date_to: string;
        status: TeamAttendanceStatusFilter;
        recording_style: TeamAttendanceRecordingStyleFilter;
        sort: 'work_date';
        direction: 'asc' | 'desc';
    }> = {},
): void {
    router.get(
        attendanceMy.url({ query: buildQuery(overrides) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function toggleSort(column: MyAttendanceFiltersProp['sort']): void {
    const same = props.myAttendanceFilters.sort === column;
    const nextDir =
        same && props.myAttendanceFilters.direction === 'asc'
            ? 'desc'
            : 'asc';
    applyQuery({ sort: column, direction: nextDir, page: 1 });
}

function sortDirectionFor(
    column: MyAttendanceFiltersProp['sort'],
): 'asc' | 'desc' | null {
    if (props.myAttendanceFilters.sort !== column) {
        return null;
    }

    return props.myAttendanceFilters.direction;
}

const { localSearch, syncFromServerSearch, onSearchUpdate, onSearchKeyup, onSearchCommit } =
    useDebouncedSearchInput({
        initialValue: props.myAttendanceFilters.q ?? '',
        debounceMs: 300,
        onDebouncedSearch: (value) => applyQuery({ q: value, page: 1 }),
    });

watch(
    () => props.myAttendanceFilters.q,
    (s) => syncFromServerSearch(s ?? ''),
);

function onPerPageChange(value: number): void {
    applyQuery({ per_page: value, page: 1 });
}

const recordingStyleChipOptions: Array<{
    value: TeamAttendanceRecordingStyleFilter;
    label: string;
}> = [
    { value: 'simple', label: 'Simple session' },
    { value: 'split', label: 'Split sessions' },
    { value: 'overnight', label: 'Overnight' },
];

const viewDialogOpen = ref(false);
const viewTarget = ref<TeamAttendanceRow | null>(null);

type DtrMonthPart = 'whole' | 'first_half' | 'second_half';

const dtrDialogOpen = ref(false);
const dtrFormError = ref<string | null>(null);
const dtrYearMonth = ref('');
const dtrMonthPart = ref<DtrMonthPart>('whole');
const dtrMonthPopoverOpen = ref(false);

const DTR_YEAR_LOOKBACK = 5;

const DTR_YEAR_LOOKAHEAD = 3;

const dtrPickerParts = computed((): { year: number; month: number } => {
    const ym = dtrYearMonth.value.trim();
    if (/^\d{4}-\d{2}$/.test(ym)) {
        const [ys, ms] = ym.split('-');
        const y = Number.parseInt(ys ?? '', 10);
        const m = Number.parseInt(ms ?? '', 10);
        if (!Number.isNaN(y) && !Number.isNaN(m) && m >= 1 && m <= 12) {
            return { year: y, month: m };
        }
    }

    const now = new Date();

    return { year: now.getFullYear(), month: now.getMonth() + 1 };
});

function patchMyDtrYearMonth(year: number, month: number): void {
    dtrYearMonth.value = `${year}-${String(month).padStart(2, '0')}`;
    dtrFormError.value = null;
}

function onMyDtrPickerMonthPick(v: unknown): void {
    const m = Number.parseInt(String(v ?? ''), 10);
    if (Number.isNaN(m) || m < 1 || m > 12) {
        return;
    }

    patchMyDtrYearMonth(dtrPickerParts.value.year, m);
}

function onMyDtrPickerYearPick(v: unknown): void {
    const y = Number.parseInt(String(v ?? ''), 10);
    if (Number.isNaN(y)) {
        return;
    }

    patchMyDtrYearMonth(y, dtrPickerParts.value.month);
}

const dtrYearChoices = computed((): number[] => {
    const anchor = new Date().getFullYear();
    const out: number[] = [];
    for (
        let y = anchor - DTR_YEAR_LOOKBACK;
        y <= anchor + DTR_YEAR_LOOKAHEAD;
        y++
    ) {
        out.push(y);
    }

    return out;
});

const dtrMonthChoices = computed(
    (): Array<{ value: number; label: string }> => {
        return Array.from({ length: 12 }, (_, i) => {
            const month = i + 1;
            const label = new Date(2000, i, 1).toLocaleDateString(undefined, {
                month: 'long',
            });

            return { value: month, label };
        });
    },
);

function myDtrYearMonthPickerLabel(isoYm: string): string {
    const ym = isoYm.trim();
    if (!/^\d{4}-\d{2}$/.test(ym)) {
        return 'Pick month…';
    }

    return new Date(`${ym}-01T12:00:00`).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
}

function resetMyDtrForm(): void {
    const now = new Date();
    dtrYearMonth.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    dtrMonthPart.value = 'whole';
    dtrFormError.value = null;
    dtrMonthPopoverOpen.value = false;
}

function openMyDtrDialog(): void {
    resetMyDtrForm();
    dtrDialogOpen.value = true;
}

function myDtrResolvedDateRange(): { from: string; to: string } | null {
    const ym = dtrYearMonth.value.trim();
    if (!/^\d{4}-\d{2}$/.test(ym)) {
        return null;
    }

    const parts = ym.split('-').map((s) => Number.parseInt(s, 10));
    const y = parts[0];
    const mo = parts[1];
    if (
        Number.isNaN(y) ||
        Number.isNaN(mo) ||
        mo === undefined ||
        mo < 1 ||
        mo > 12
    ) {
        return null;
    }

    const lastDay = new Date(y, mo, 0).getDate();
    let fromDay = 1;
    let toDay = lastDay;
    switch (dtrMonthPart.value) {
        case 'first_half':
            fromDay = 1;
            toDay = Math.min(15, lastDay);

            break;
        case 'second_half':
            fromDay = Math.min(16, lastDay);
            toDay = lastDay;

            break;
        default:
            fromDay = 1;
            toDay = lastDay;

            break;
    }

    if (fromDay > toDay) {
        return null;
    }

    const pad = (n: number): string => String(n).padStart(2, '0');

    return {
        from: `${ym}-${pad(fromDay)}`,
        to: `${ym}-${pad(toDay)}`,
    };
}

function validateMyDtrForm(): string | null {
    if (chartBranchId.value === null) {
        return 'Select a workspace branch (header) before generating a DTR.';
    }

    if (myDtrResolvedDateRange() === null) {
        return 'Pick a valid calendar month and segment.';
    }

    return null;
}

function generateMyDtrExcel(): void {
    const err = validateMyDtrForm();
    if (err) {
        dtrFormError.value = err;

        return;
    }

    if (myDtrResolvedDateRange() === null) {
        dtrFormError.value = 'Could not derive a date range.';

        return;
    }

    dtrFormError.value = null;
    dtrDialogOpen.value = false;
    window.location.assign(dtrMockSample.url());
}

const statusFilterOptions = computed(() => [
    {
        value: 'complete',
        label: attendanceStatusLabel('complete'),
        searchText: 'complete ok',
    },
    {
        value: 'ongoing',
        label: attendanceStatusLabel('ongoing'),
        searchText: 'ongoing partial',
    },
    {
        value: 'incomplete',
        label: attendanceStatusLabel('incomplete'),
        searchText: 'incomplete missing',
    },
]);

function statusPopoverModel(): TeamAttendanceRecordStatus | null {
    const status = props.myAttendanceFilters.status;

    return status === 'all' ? null : status;
}

function onStatusPopoverUpdate(v: string | number | null): void {
    if (v === null || v === '') {
        applyQuery({ status: 'all', page: 1 });

        return;
    }

    applyQuery({
        status: v as Exclude<TeamAttendanceStatusFilter, 'all'>,
        page: 1,
    });
}

const recordingStyleFilter = computed({
    get(): TeamAttendanceRecordingStyleFilter {
        return props.myAttendanceFilters.recording_style ?? 'simple';
    },
    set(value: TeamAttendanceRecordingStyleFilter): void {
        applyQuery({ recording_style: value, page: 1 });
    },
});

const toolbarDateFromModel = computed({
    get(): string {
        return (
            props.myAttendanceFilters.date_from ?? isoFirstDayOfMonth(new Date())
        );
    },
    set(iso: string): void {
        applyQuery({ date_from: iso, page: 1 });
    },
});

const toolbarDateToModel = computed({
    get(): string {
        return (
            props.myAttendanceFilters.date_to ?? isoLastDayOfMonth(new Date())
        );
    },
    set(iso: string): void {
        applyQuery({ date_to: iso, page: 1 });
    },
});

const totalRows = computed(() => props.myAttendanceDays.total);

const emptyMessage = computed((): string => {
    if (!props.hasEmployeeRecord) {
        return 'No employee profile is linked to this account.';
    }

    if (props.myAttendanceDays.data.length === 0) {
        return 'No attendance rows match your current filters.';
    }

    return 'No attendance rows to show.';
});

const fromRow = computed(() => props.myAttendanceDays.from);
const toRow = computed(() => props.myAttendanceDays.to);
const lastPage = computed(() => Math.max(1, props.myAttendanceDays.last_page));

function openView(row: TeamAttendanceRow): void {
    viewTarget.value = row;
    viewDialogOpen.value = true;
}

const columns = computed((): ColumnDef<TeamAttendanceRow>[] => [
    {
        id: 'work_date',
        header: () =>
            h(TeamTableSortHeader, {
                columnTitle: 'Work date',
                sortDirection: sortDirectionFor('work_date'),
                onToggleSort: () => toggleSort('work_date'),
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm tabular-nums text-foreground' },
                new Date(
                    `${row.original.work_date}T12:00:00`,
                ).toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                }),
            ),
    },
    {
        id: 'attendance_id',
        meta: {
            headClass: 'min-w-[7rem]',
            cellClass:
                'align-middle font-mono text-sm tabular-nums text-muted-foreground',
        },
        header: () =>
            h('span', { class: tablePlainHeadClass }, 'Attendance ID'),
        cell: ({ row }) => {
            const r = row.original;
            const profile = r.attendance_id?.trim();
            const ingest = r.ingest_key?.trim();

            return h('span', { class: 'block py-0.5' }, [
                h(
                    'span',
                    {
                        class: profile
                            ? 'text-foreground'
                            : 'text-muted-foreground',
                    },
                    profile || '—',
                ),
                ingest
                    ? h(
                          'span',
                          {
                              class:
                                  'mt-0.5 block text-[11px] leading-tight text-muted-foreground',
                          },
                          ingest,
                      )
                    : null,
            ]);
        },
    },
    {
        id: 'time_clock',
        meta: {
            headClass: 'min-w-[9rem]',
            cellClass:
                'align-middle max-w-[16rem] font-mono text-sm tabular-nums whitespace-pre-line leading-snug text-foreground',
        },
        header: () =>
            h('span', { class: tablePlainHeadClass }, 'Time (clock in / out)'),
        cell: ({ row }) => {
            const r = row.original;

            return h(
                'span',
                { class: 'block py-0.5' },
                clockInOutDisplay(r.clock_pattern, r.segments),
            );
        },
    },
    {
        id: 'gross_time',
        meta: {
            headClass: 'min-w-[6.5rem]',
            cellClass: 'align-middle text-sm tabular-nums text-foreground',
        },
        header: () => h(AttendanceGrossNetColumnHeader, { metric: 'gross' }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'block py-0.5 font-mono' },
                attendanceActualDutyGrossDisplay(row.original.segments),
            ),
    },
    {
        id: 'net_time',
        meta: {
            headClass: 'min-w-[6.5rem]',
            cellClass: 'align-middle text-sm tabular-nums text-foreground',
        },
        header: () => h(AttendanceGrossNetColumnHeader, { metric: 'net' }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'block py-0.5 font-mono' },
                attendanceNetWithinScheduledOverlapDisplay(
                    row.original.segments,
                    row.original.punctuality,
                    {
                        clockPattern: row.original.clock_pattern,
                        unpaidBreakMinutesFromTemplate:
                            row.original.unpaid_break_minutes ?? 0,
                    },
                ),
            ),
    },
    {
        id: 'punctuality',
        meta: { headClass: 'min-w-[8rem]', cellClass: 'align-middle' },
        header: () => h('span', { class: tablePlainHeadClass }, 'Punctuality'),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'outline',
                    class: punctualityBadgeClass(row.original.punctuality),
                },
                () => punctualityLabel(row.original.punctuality),
            ),
    },
    {
        id: 'status',
        meta: { headClass: 'min-w-[8rem]', cellClass: 'align-middle' },
        header: () =>
            h(HrisColumnFilterPopover, {
                label: 'Status',
                triggerAriaLabel:
                    props.myAttendanceFilters.status === 'all'
                        ? 'Status filter: all.'
                        : `Status filter: ${attendanceStatusLabel(props.myAttendanceFilters.status)}`,
                modelValue: statusPopoverModel(),
                options: statusFilterOptions.value,
                isActive: props.myAttendanceFilters.status !== 'all',
                showClear: props.myAttendanceFilters.status !== 'all',
                clearAriaLabel: 'Clear status filter',
                allLabel: 'All statuses',
                searchPlaceholder: 'Search status…',
                emptyText: 'No matching options.',
                'onUpdate:modelValue': onStatusPopoverUpdate,
                onClear: () => {
                    applyQuery({ status: 'all', page: 1 });
                },
            }),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'outline',
                    class: attendanceStatusBadgeClass(row.original.status),
                },
                () => attendanceStatusLabel(row.original.status),
            ),
    },
    {
        id: 'actions',
        meta: {
            headClass: 'w-[3.25rem] min-w-[3.25rem] text-center',
            cellClass: 'text-center',
        },
        header: () =>
            h(
                'div',
                { class: `w-full text-center ${tablePlainHeadClass}` },
                'Action',
            ),
        cell: ({ row }) =>
            h(
                Button,
                {
                    type: 'button',
                    variant: 'ghost',
                    size: 'icon-sm',
                    'aria-label': 'View attendance entry',
                    onClick: () => openView(row.original),
                },
                () => h(Eye, { class: 'size-4' }),
            ),
    },
]);

const table = useVueTable({
    get data() {
        return props.myAttendanceDays.data;
    },
    get columns() {
        return columns.value;
    },
    getCoreRowModel: getCoreRowModel(),
});
</script>

<template>
    <Head title="My Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto overflow-y-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    My Attendance
                </h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    Clock-in/out view for your account. Rows load from the
                    server using your employee profile and current filters. Team
                    HR entry lives on
                    <Link
                        :href="attendanceTeam()"
                        class="font-medium text-foreground underline-offset-4 hover:underline"
                        >Team Attendance</Link
                    >. Dates use work date.
                </p>
            </div>

            <div class="flex flex-col gap-3">
                <div class="w-full max-w-md min-w-0">
                    <InputGroup>
                        <InputGroupAddon align="inline-start">
                            <Search
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </InputGroupAddon>
                        <InputGroupInput
                            id="my-attendance-search"
                            :model-value="localSearch"
                            type="search"
                            autocomplete="off"
                            placeholder="Search punch times or status hints…"
                            aria-label="Search my attendance rows"
                            @update:model-value="onSearchUpdate"
                            @keyup="onSearchKeyup"
                            @change="onSearchCommit"
                            @search="onSearchCommit"
                        />
                    </InputGroup>
                </div>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="opt in recordingStyleChipOptions"
                            :key="opt.value"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 rounded-full px-3"
                            :class="
                                recordingStyleFilter === opt.value
                                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
                                    : ''
                            "
                            :aria-pressed="recordingStyleFilter === opt.value"
                            @click="recordingStyleFilter = opt.value"
                        >
                            {{ opt.label }}
                        </Button>
                    </div>
                    <div
                        class="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end"
                    >
                        <TeamIndexDateRangePickers
                            v-model:date-from="toolbarDateFromModel"
                            v-model:date-to="toolbarDateToModel"
                            compact-row
                            class="min-w-0"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="h-9 shrink-0 border-primary/60 text-primary hover:bg-primary/10 hover:text-primary dark:border-primary/70 dark:hover:bg-primary/15"
                            @click="openMyDtrDialog"
                        >
                            <FileSpreadsheet
                                class="size-4"
                                aria-hidden="true"
                            />
                            <span class="ml-1">Generate DTR</span>
                        </Button>
                    </div>
                </div>
            </div>

            <div class="w-full">
                <TooltipProvider :delay-duration="200">
                    <HrisTanStackTable
                        :table="table"
                        :empty-message="emptyMessage"
                    />
                </TooltipProvider>
                <HrisServerTablePagination
                    :total="totalRows"
                    :from="fromRow"
                    :to="toRow"
                    :current-page="myAttendanceDays.current_page"
                    :last-page="lastPage"
                    :per-page="myAttendanceDays.per_page"
                    :can-previous-page="myAttendanceDays.current_page > 1"
                    :can-next-page="myAttendanceDays.current_page < lastPage"
                    @update:per-page="onPerPageChange"
                    @go-first="applyQuery({ page: 1 })"
                    @go-prev="
                        applyQuery({
                            page: Math.max(1, myAttendanceDays.current_page - 1),
                        })
                    "
                    @go-next="
                        applyQuery({
                            page: Math.min(
                                lastPage,
                                myAttendanceDays.current_page + 1,
                            ),
                        })
                    "
                    @go-last="applyQuery({ page: lastPage })"
                />
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="viewDialogOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>View attendance entry</DialogTitle>
                <DialogDescription>
                    Read-only summary from saved data.
                </DialogDescription>
            </DialogHeader>
            <ScrollArea v-if="viewTarget" :class="dialogScrollAreaClass">
                <AttendanceEntryViewContent :row="viewTarget" />
            </ScrollArea>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="viewDialogOpen = false"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="dtrDialogOpen">
        <DialogContent class="gap-4 sm:max-w-lg">
            <DialogHeader class="gap-2 text-left">
                <DialogTitle>Generate DTR</DialogTitle>
                <DialogDescription>
                    Pick month and segment, then generate to download the sample
                    Excel file for now — filters apply when export is wired.
                    Choose a workspace branch in the header first if needed.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-3">
                <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                    <div class="grid gap-2">
                        <Label for="my-dtr-calendar-month-trigger">
                            Calendar month
                        </Label>
                        <Popover v-model:open="dtrMonthPopoverOpen">
                            <PopoverTrigger as-child>
                                <Button
                                    id="my-dtr-calendar-month-trigger"
                                    type="button"
                                    variant="outline"
                                    class="h-9 w-full justify-between gap-2 font-normal"
                                    aria-label="Choose month and year"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <CalendarIcon
                                            class="size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate tabular-nums">{{
                                            myDtrYearMonthPickerLabel(
                                                dtrYearMonth,
                                            )
                                        }}</span>
                                    </span>
                                    <ChevronDown
                                        class="size-4 shrink-0 opacity-50"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                class="w-[calc(100vw-2rem)] max-w-[20rem] p-4 sm:w-80"
                                align="start"
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="my-dtr-pop-month"
                                            >Month</Label
                                        >
                                        <Select
                                            :model-value="
                                                String(dtrPickerParts.month)
                                            "
                                            @update:model-value="
                                                onMyDtrPickerMonthPick
                                            "
                                        >
                                            <SelectTrigger
                                                id="my-dtr-pop-month"
                                                class="h-9 w-full"
                                                aria-label="Month"
                                            >
                                                <SelectValue
                                                    placeholder="Month"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="opt in dtrMonthChoices"
                                                    :key="opt.value"
                                                    :value="String(opt.value)"
                                                >
                                                    {{ opt.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="my-dtr-pop-year"
                                            >Year</Label
                                        >
                                        <Select
                                            :model-value="
                                                String(dtrPickerParts.year)
                                            "
                                            @update:model-value="
                                                onMyDtrPickerYearPick
                                            "
                                        >
                                            <SelectTrigger
                                                id="my-dtr-pop-year"
                                                class="h-9 w-full"
                                                aria-label="Year"
                                            >
                                                <SelectValue
                                                    placeholder="Year"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="y in dtrYearChoices"
                                                    :key="y"
                                                    :value="String(y)"
                                                >
                                                    {{ y }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </PopoverContent>
                        </Popover>
                    </div>
                    <div class="grid gap-2">
                        <Label for="my-dtr-month-part">Segment</Label>
                        <Select
                            :model-value="dtrMonthPart"
                            @update:model-value="
                                (v: unknown) => {
                                    const s = String(v ?? '');
                                    if (
                                        s === 'whole' ||
                                        s === 'first_half' ||
                                        s === 'second_half'
                                    ) {
                                        dtrMonthPart = s;
                                        dtrFormError = null;
                                    }
                                }
                            "
                        >
                            <SelectTrigger
                                id="my-dtr-month-part"
                                class="h-9 w-full"
                                aria-label="Segment"
                            >
                                <SelectValue placeholder="Whole month" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="whole"
                                    >Whole month</SelectItem
                                >
                                <SelectItem value="first_half"
                                    >Days 1–15</SelectItem
                                >
                                <SelectItem value="second_half"
                                    >Days 16 – end</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <p
                    v-if="dtrFormError"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ dtrFormError }}
                </p>
            </div>
            <DialogFooter class="gap-2 pt-0 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    @click="dtrDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button type="button" @click="generateMyDtrExcel">
                    Generate DTR
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
