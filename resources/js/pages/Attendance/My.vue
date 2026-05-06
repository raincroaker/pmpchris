<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import {
    CheckCircle2,
    Clock3,
    Eye,
    FileSpreadsheet,
    Timer,
    XCircle,
    Search,
} from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import HrisKpiCard from '@/components/hris/HrisKpiCard.vue';
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
import { ScrollArea } from '@/components/ui/scroll-area';
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
        recording_style: (f.recording_style ??
            'simple') as TeamAttendanceRecordingStyleFilter,
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
        same && props.myAttendanceFilters.direction === 'asc' ? 'desc' : 'asc';
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

const {
    localSearch,
    syncFromServerSearch,
    onSearchUpdate,
    onSearchKeyup,
    onSearchCommit,
} = useDebouncedSearchInput({
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
            props.myAttendanceFilters.date_from ??
            isoFirstDayOfMonth(new Date())
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

const myAttendanceKpis = computed(() => {
    const rows = props.myAttendanceDays.data;

    return rows.reduce(
        (acc, row) => {
            if (row.punctuality === 'on_time') {
                acc.onTime += 1;
            } else if (row.punctuality === 'late') {
                acc.late += 1;
            }

            if (row.status === 'incomplete') {
                acc.incomplete += 1;
            }

            acc.netHours += Number(row.net_hours ?? 0);

            return acc;
        },
        {
            onTime: 0,
            late: 0,
            incomplete: 0,
            netHours: 0,
        },
    );
});

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

function generateMyDtrExcel(): void {
    const from = toolbarDateFromModel.value;
    const to = toolbarDateToModel.value;
    const query = new URLSearchParams({
        mode: 'sample',
        date_from: from,
        date_to: to,
    });
    window.location.assign(
        `/attendance/reports/dtr-mock-sample?${query.toString()}`,
    );
}

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
                              class: 'mt-0.5 block text-[11px] leading-tight text-muted-foreground',
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

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <HrisKpiCard
                    title="On-time rows"
                    :value="myAttendanceKpis.onTime"
                    hint="Current page records"
                    tone="emerald"
                    :icon="CheckCircle2"
                />
                <HrisKpiCard
                    title="Late rows"
                    :value="myAttendanceKpis.late"
                    hint="Current page records"
                    tone="amber"
                    :icon="Clock3"
                />
                <HrisKpiCard
                    title="Incomplete rows"
                    :value="myAttendanceKpis.incomplete"
                    hint="Missing punch details"
                    tone="rose"
                    :icon="XCircle"
                />
                <HrisKpiCard
                    title="Net hours"
                    :value="myAttendanceKpis.netHours.toFixed(1)"
                    hint="Summed in this page"
                    tone="violet"
                    :icon="Timer"
                />
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
                            @click="generateMyDtrExcel"
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
                            page: Math.max(
                                1,
                                myAttendanceDays.current_page - 1,
                            ),
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
</template>
