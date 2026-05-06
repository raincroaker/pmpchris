<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { Clock3, Plus, Search, X } from 'lucide-vue-next';
import { computed, h, ref, toRaw, watch } from 'vue';
import HrisIndexToolbar from '@/components/hris/HrisIndexToolbar.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import {
    dayOfWeekOptions,
    defaultSplitSegments,
    shiftRuleNumericDefaults,
} from '@/pages/Attendance/attendanceRulesTypes';
import type {
    ClockPattern,
    DayOfWeekKey,
    ShiftRule,
    ShiftSegment,
    SplitSegments,
} from '@/pages/Attendance/attendanceRulesTypes';
import ShiftsActionsMenu from '@/pages/Attendance/ShiftsActionsMenu.vue';
import type {
    ShiftsSchedulePatternFilter,
    ShiftsStatusFilter,
} from '@/pages/Attendance/shiftsIndexFilters';
import ShiftsIndexScheduleColumnHeader from '@/pages/Attendance/ShiftsIndexScheduleColumnHeader.vue';
import ShiftsIndexStatusColumnHeader from '@/pages/Attendance/ShiftsIndexStatusColumnHeader.vue';
import WorkScheduleAttendanceRulesPanel from '@/pages/Attendance/WorkScheduleAttendanceRulesPanel.vue';
import {
    defaultWorkScheduleAttendanceRulesDraft,
    defaultWorkScheduleOvertimeRulesDraft,
} from '@/pages/Attendance/workScheduleFormRulesTypes';
import type {
    WorkScheduleAttendanceRulesDraft,
    WorkScheduleOvertimeRulesDraft,
} from '@/pages/Attendance/workScheduleFormRulesTypes';
import WorkScheduleMetricLabel from '@/pages/Attendance/WorkScheduleMetricLabel.vue';
import WorkScheduleOvertimeRulesPanel from '@/pages/Attendance/WorkScheduleOvertimeRulesPanel.vue';
import {
    approximateWorkScheduleSpanMinutes,
    clockPatternSearchHaystack,
    formatWorkScheduleDurationLabel,
    formatWorkScheduleNetHours,
    workScheduleOvernightBadgeClass,
    workScheduleSplitSessionsBadgeClass,
} from '@/pages/Attendance/workScheduleUi';
import { shifts as attendanceShifts } from '@/routes/attendance';
import workScheduleApi from '@/routes/attendance/work-schedule-templates';
import type { BreadcrumbItem } from '@/types';

const page = usePage<{ can?: { canMutateWorkSchedules?: boolean } }>();
const canMutateWorkSchedules = computed(() =>
    Boolean(page.props.can?.canMutateWorkSchedules),
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Work Schedules', href: attendanceShifts() },
];

const props = withDefaults(
    defineProps<{
        workScheduleTemplates: ShiftRule[];
    }>(),
    {
        workScheduleTemplates: () => [],
    },
);

/** Add/edit tab body: fixed height so Reka ScrollArea’s viewport always has a bound (flex fill was collapsing). Extra bottom padding on the inner wrapper for comfortable end-of-scroll. */
const dialogScheduleFormScrollClass =
    'h-[min(60vh,520px)] min-h-0 shrink-0 pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

/** View dialog body: same fixed height as add/edit so ScrollArea scrolls reliably. */
const dialogViewScrollAreaClass =
    'h-[min(60vh,520px)] min-h-0 shrink-0 pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

/** Sentence-case headers (matches Employees / Positions HRIS tables). */
const tablePlainHeadClass = 'font-medium text-muted-foreground';

const optionalLabelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';
const clearFieldButtonClass =
    'absolute right-0 top-1/2 z-[1] size-6 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground';

const localSearch = ref('');
const perPage = ref(10);
const currentPage = ref(1);

const schedulePatternFilter = ref<ShiftsSchedulePatternFilter>('all');
const statusColumnFilter = ref<ShiftsStatusFilter>('all');

function shiftMatchesSchedulePatternFilter(
    shift: ShiftRule,
    filter: ShiftsSchedulePatternFilter,
): boolean {
    if (filter === 'all') {
        return true;
    }
    if (filter === 'split_sessions') {
        return shift.clock_pattern === 'split_sessions';
    }
    if (filter === 'single_overnight') {
        return shift.clock_pattern === 'single_pair' && shift.is_overnight;
    }
    if (filter === 'single_day') {
        return shift.clock_pattern === 'single_pair' && !shift.is_overnight;
    }
    return true;
}

function shiftMatchesStatusFilter(
    shift: ShiftRule,
    filter: ShiftsStatusFilter,
): boolean {
    if (filter === 'all') {
        return true;
    }
    if (filter === 'active') {
        return shift.is_active;
    }
    return !shift.is_active;
}

const shifts = ref<ShiftRule[]>(
    structuredClone(toRaw(props.workScheduleTemplates)),
);

watch(
    () => props.workScheduleTemplates,
    (next) => {
        shifts.value = structuredClone(toRaw(next));
    },
    { deep: true },
);

function resolveCsrfToken(): string | null {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
}

function firstApiErrorFromPayload(payload: unknown): string | null {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const record = payload as Record<string, unknown>;
    if (typeof record.message === 'string' && record.message.trim() !== '') {
        return record.message;
    }

    const errors = record.errors;
    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const bag = errors as Record<string, string[] | string>;
    for (const value of Object.values(bag)) {
        if (Array.isArray(value) && typeof value[0] === 'string') {
            return value[0];
        }
        if (typeof value === 'string' && value !== '') {
            return value;
        }
    }

    return null;
}

function reloadWorkScheduleTemplates(onSuccess?: () => void): void {
    router.reload({
        only: ['workScheduleTemplates'],
        onSuccess: () => onSuccess?.(),
    });
}

function graceSummary(shift: ShiftRule): string {
    if (shift.grace_late_arrival_minutes > 0) {
        return `Late in +${shift.grace_late_arrival_minutes}m`;
    }

    return 'None set';
}

const filteredShifts = computed(() => {
    const pattern = schedulePatternFilter.value;
    const statusF = statusColumnFilter.value;
    const q = localSearch.value.trim().toLowerCase();

    return shifts.value.filter((shift) => {
        if (!shiftMatchesSchedulePatternFilter(shift, pattern)) {
            return false;
        }
        if (!shiftMatchesStatusFilter(shift, statusF)) {
            return false;
        }
        if (q === '') {
            return true;
        }

        const daysLabel = shift.days.join(' ');
        const graceHaystack = graceSummary(shift).toLowerCase();
        const notesHaystack = (shift.notes ?? '').toLowerCase();
        const patternHaystack = clockPatternSearchHaystack(shift.clock_pattern);
        const segmentTimeHaystack =
            shift.clock_pattern === 'split_sessions' && shift.segments
                ? shift.segments
                      .map((seg) => `${seg.time_in} ${seg.time_out}`)
                      .join(' ')
                : '';
        const grossHaystack = (
            scheduleDurationLabel(shift) ?? ''
        ).toLowerCase();
        const netHaystack = (
            netWorkingDurationLabel(shift) ?? ''
        ).toLowerCase();

        return (
            shift.name.toLowerCase().includes(q) ||
            daysLabel.includes(q) ||
            patternHaystack.includes(q) ||
            segmentTimeHaystack.includes(q) ||
            formatTimeDisplay(shift.time_in).toLowerCase().includes(q) ||
            formatTimeDisplay(shift.time_out).toLowerCase().includes(q) ||
            grossHaystack.includes(q) ||
            netHaystack.includes(q) ||
            String(shift.unpaid_break_minutes).includes(q) ||
            graceHaystack.includes(q) ||
            notesHaystack.includes(q)
        );
    });
});

const paginatedShifts = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredShifts.value.slice(start, start + perPage.value);
});

const totalRows = computed(() => filteredShifts.value.length);

const emptyTableMessage = computed(() => {
    if (shifts.value.length === 0) {
        return 'No work schedules yet. Add a template to set expected days and hours for attendance.';
    }

    return 'No work schedules match your search or filters.';
});

watch([schedulePatternFilter, statusColumnFilter], () => {
    setPage(1);
});
const fromRow = computed(() => {
    if (totalRows.value === 0) return null;
    return (currentPage.value - 1) * perPage.value + 1;
});
const toRow = computed(() => {
    if (totalRows.value === 0) return null;
    return Math.min(currentPage.value * perPage.value, totalRows.value);
});
const lastPage = computed(() =>
    Math.max(1, Math.ceil(totalRows.value / perPage.value)),
);

function setPage(next: number): void {
    currentPage.value = Math.min(Math.max(next, 1), lastPage.value);
}

function onSearchUpdate(value: string | number): void {
    localSearch.value = String(value ?? '');
    setPage(1);
}

function onPerPageChange(value: number): void {
    perPage.value = value;
    setPage(1);
}

type ShiftDraft = Omit<ShiftRule, 'id' | 'notes'> & { notes: string };

const addDialogOpen = ref(false);
const editDialogOpen = ref(false);
const viewDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const activeDraft = ref<ShiftDraft>({
    name: '',
    days: [],
    clock_pattern: 'single_pair',
    segments: null,
    time_in: '',
    time_out: '',
    is_overnight: false,
    is_active: true,
    notes: '',
    ...shiftRuleNumericDefaults,
});
const editTargetId = ref<number | null>(null);
const viewTarget = ref<ShiftRule | null>(null);
const deleteTarget = ref<ShiftRule | null>(null);
const formError = ref<string | null>(null);

/** Add/Edit dialog tab + attendance / overtime JSON persisted on the template. */
const scheduleFormTab = ref<'schedule' | 'attendance' | 'overtime'>('schedule');
const attendanceRulesDraft = ref(defaultWorkScheduleAttendanceRulesDraft());
const overtimeRulesDraft = ref(defaultWorkScheduleOvertimeRulesDraft());

function resetFormRulesDraft(): void {
    attendanceRulesDraft.value = defaultWorkScheduleAttendanceRulesDraft();
    overtimeRulesDraft.value = defaultWorkScheduleOvertimeRulesDraft();
    scheduleFormTab.value = 'schedule';
}

function mergeAttendanceRulesFromPayload(
    stored: Partial<WorkScheduleAttendanceRulesDraft> | null | undefined,
): WorkScheduleAttendanceRulesDraft {
    return { ...defaultWorkScheduleAttendanceRulesDraft(), ...(stored ?? {}) };
}

function mergeOvertimeRulesFromPayload(
    stored: Partial<WorkScheduleOvertimeRulesDraft> | null | undefined,
): WorkScheduleOvertimeRulesDraft {
    return { ...defaultWorkScheduleOvertimeRulesDraft(), ...(stored ?? {}) };
}

/** Read-only view dialog: merged attendance rules when stored JSON exists. */
const viewAttendanceDisplay = computed(
    (): WorkScheduleAttendanceRulesDraft | null => {
        const t = viewTarget.value;
        if (t === null || t.attendance_rules == null) {
            return null;
        }

        return mergeAttendanceRulesFromPayload(t.attendance_rules);
    },
);

/** Read-only view dialog: merged overtime rules when stored JSON exists. */
const viewOvertimeDisplay = computed(
    (): WorkScheduleOvertimeRulesDraft | null => {
        const t = viewTarget.value;
        if (t === null || t.overtime_rules == null) {
            return null;
        }

        return mergeOvertimeRulesFromPayload(t.overtime_rules);
    },
);

function viewClockInRoundingSummary(
    rules: WorkScheduleAttendanceRulesDraft,
): string {
    const custom = rules.clockInRoundingCustomMinutes;
    const map: Record<string, string> = {
        none: 'No rounding',
        '5': 'Nearest 5 minutes',
        '15': 'Nearest 15 minutes',
        custom:
            custom !== undefined && custom !== null
                ? `Custom (${custom} min)`
                : 'Custom',
    };

    return map[rules.clockInRounding] ?? String(rules.clockInRounding);
}

const viewOvertimeSpanSummary = computed((): string | null => {
    const r = viewOvertimeDisplay.value;
    if (r === null || !r.otBlockEnabled) {
        return null;
    }

    const mins = approximateWorkScheduleSpanMinutes(
        r.otTimeIn,
        r.otTimeOut,
        r.otIsOvernight,
    );
    if (mins === null || mins <= 0) {
        return null;
    }

    return formatWorkScheduleDurationLabel(mins);
});

function resetDraft(): void {
    activeDraft.value = {
        name: '',
        days: [],
        clock_pattern: 'single_pair',
        segments: null,
        time_in: '',
        time_out: '',
        is_overnight: false,
        is_active: true,
        notes: '',
        ...shiftRuleNumericDefaults,
    };
    formError.value = null;
    resetFormRulesDraft();
}

function onClockPatternChange(value: unknown): void {
    const v = value as ClockPattern;
    if (v !== 'single_pair' && v !== 'split_sessions') {
        return;
    }

    activeDraft.value.clock_pattern = v;
    if (v === 'split_sessions') {
        activeDraft.value.segments =
            activeDraft.value.segments ?? defaultSplitSegments();
        syncMirrorFromSegments(activeDraft.value);
    } else {
        activeDraft.value.segments = null;
    }
}

function normalizeSplitSegmentLabels(draft: ShiftDraft): void {
    if (draft.clock_pattern !== 'split_sessions' || !draft.segments) {
        return;
    }

    draft.segments.forEach((seg, idx) => {
        if (seg.label.trim() === '') {
            seg.label = `Session ${idx + 1}`;
        }
    });
}

watch(
    () =>
        [activeDraft.value.clock_pattern, activeDraft.value.segments] as const,
    () => {
        if (
            activeDraft.value.clock_pattern === 'split_sessions' &&
            activeDraft.value.segments
        ) {
            syncMirrorFromSegments(activeDraft.value);
        }
    },
    { deep: true },
);

function cloneSplitSegmentsForDraft(
    segments: readonly ShiftSegment[],
): SplitSegments {
    if (segments.length < 2) {
        return [...defaultSplitSegments()];
    }

    return segments.map((seg, idx) => {
        const label =
            typeof seg.label === 'string' && seg.label.trim() !== ''
                ? seg.label.trim()
                : `Session ${idx + 1}`;

        return { ...seg, label };
    });
}

function openAddDialog(): void {
    if (!canMutateWorkSchedules.value) return;
    resetDraft();
    addDialogOpen.value = true;
}

function applyShiftToDraft(shift: ShiftRule): void {
    activeDraft.value = {
        name: shift.name,
        days: [...shift.days],
        clock_pattern: shift.clock_pattern,
        segments:
            shift.segments !== null
                ? cloneSplitSegmentsForDraft(shift.segments)
                : null,
        time_in: shift.time_in,
        time_out: shift.time_out,
        is_overnight: shift.is_overnight,
        is_active: shift.is_active,
        unpaid_break_minutes: shift.unpaid_break_minutes,
        grace_late_arrival_minutes: shift.grace_late_arrival_minutes,
        notes: shift.notes ?? '',
    };
    formError.value = null;
}

function openEditDialog(shift: ShiftRule): void {
    if (!canMutateWorkSchedules.value) return;
    editTargetId.value = shift.id;
    applyShiftToDraft(shift);
    attendanceRulesDraft.value = mergeAttendanceRulesFromPayload(
        shift.attendance_rules,
    );
    overtimeRulesDraft.value = mergeOvertimeRulesFromPayload(
        shift.overtime_rules,
    );
    scheduleFormTab.value = 'schedule';
    editDialogOpen.value = true;
}

function openViewDialog(shift: ShiftRule): void {
    viewTarget.value = shift;
    viewDialogOpen.value = true;
}

function openDeleteDialog(shift: ShiftRule): void {
    if (!canMutateWorkSchedules.value) return;
    deleteTarget.value = shift;
    deleteDialogOpen.value = true;
}

function toggleDraftDay(
    day: DayOfWeekKey,
    checked: boolean | 'indeterminate',
): void {
    const enabled = checked === true;
    if (enabled) {
        if (!activeDraft.value.days.includes(day)) {
            activeDraft.value.days = [...activeDraft.value.days, day];
        }
        return;
    }
    activeDraft.value.days = activeDraft.value.days.filter(
        (value) => value !== day,
    );
}

const numericDraftFields: {
    key: keyof Pick<
        ShiftDraft,
        'unpaid_break_minutes' | 'grace_late_arrival_minutes'
    >;
    label: string;
}[] = [
    { key: 'unpaid_break_minutes', label: 'Breaktime' },
    { key: 'grace_late_arrival_minutes', label: 'Late arrival grace' },
];

function validateSegmentWindow(
    seg: ShiftSegment,
    sessionLabel: string,
): boolean {
    if (seg.time_in === '' || seg.time_out === '') {
        formError.value = `${sessionLabel}: set both time in and time out.`;
        return false;
    }

    if (seg.is_overnight) {
        formError.value = `${sessionLabel}: turn off overnight for split sessions (same calendar day only).`;
        return false;
    }

    const start = parseTimeToMinutes(seg.time_in);
    const end = parseTimeToMinutes(seg.time_out);
    if (start === null || end === null) {
        formError.value = `${sessionLabel}: times must be valid HH:mm values.`;
        return false;
    }

    if (end <= start) {
        formError.value = `${sessionLabel}: time out must be after time in (same day).`;
        return false;
    }

    const dur = approximateScheduleDurationMinutes(
        seg.time_in,
        seg.time_out,
        seg.is_overnight,
    );
    if (dur === null || dur <= 0) {
        formError.value = `${sessionLabel}: session length must be greater than zero.`;
        return false;
    }

    return true;
}

function validateOvertimeRules(): boolean {
    const ot = overtimeRulesDraft.value;
    if (!ot.otBlockEnabled) {
        return true;
    }

    if (ot.otTimeIn === '' || ot.otTimeOut === '') {
        formError.value =
            'Set both OT time in and time out when the overtime block is enabled.';
        return false;
    }

    const otInM = parseTimeToMinutes(ot.otTimeIn);
    if (otInM === null || parseTimeToMinutes(ot.otTimeOut) === null) {
        formError.value = 'OT times must be valid HH:mm values.';
        return false;
    }

    const otSpan = approximateScheduleDurationMinutes(
        ot.otTimeIn,
        ot.otTimeOut,
        ot.otIsOvernight,
    );
    if (otSpan === null || otSpan <= 0) {
        formError.value =
            'OT time out must be after OT time in, or enable Overnight OT span for the OT block when it crosses midnight.';
        return false;
    }

    const d = activeDraft.value;
    const lastOutStr = ((): string => {
        if (d.clock_pattern === 'split_sessions' && d.segments) {
            const segs = d.segments;
            if (segs.length >= 3) {
                const penultimate = segs[segs.length - 2];
                if (penultimate !== undefined && penultimate.time_out !== '') {
                    return penultimate.time_out;
                }
            }

            const lastSeg = segs.at(-1);
            if (lastSeg !== undefined && lastSeg.time_out !== '') {
                return lastSeg.time_out;
            }
        }

        return d.time_out;
    })();
    const lastOutM = parseTimeToMinutes(lastOutStr);
    if (lastOutM === null) {
        return true;
    }

    const enforceOtAfterRegularEnd =
        d.clock_pattern === 'split_sessions' ||
        (d.clock_pattern === 'single_pair' && !d.is_overnight);

    if (enforceOtAfterRegularEnd && otInM < lastOutM) {
        formError.value =
            'OT time in must be at or after the end of the last regular session.';
        return false;
    }

    return true;
}

function validateDraft(): boolean {
    if (activeDraft.value.name.trim() === '') {
        formError.value = 'Schedule name is required.';
        return false;
    }
    if (activeDraft.value.days.length === 0) {
        formError.value = 'Select at least one working day.';
        return false;
    }

    if (activeDraft.value.clock_pattern === 'split_sessions') {
        const segs = activeDraft.value.segments;
        if (!segs || segs.length < 2 || segs.length > 8) {
            formError.value =
                'Split Sessions require at least two session rows (and at most eight). Each next time in must be at or after the previous time out.';
            return false;
        }

        for (let i = 0; i < segs.length; i++) {
            const seg = segs[i];
            const sessionTitle =
                seg.label.trim() !== '' ? seg.label : `Session ${i + 1}`;
            if (!validateSegmentWindow(seg, sessionTitle)) return false;
        }

        for (let i = 0; i < segs.length - 1; i++) {
            const gap = interSessionGapMinutes(
                segs[i].time_out,
                segs[i + 1].time_in,
            );
            if (gap === null) {
                formError.value = `Session ${i + 2} time in must be at or after Session ${i + 1} time out.`;
                return false;
            }
        }

        syncMirrorFromSegments(activeDraft.value);
    } else {
        if (
            activeDraft.value.time_in === '' ||
            activeDraft.value.time_out === ''
        ) {
            formError.value = 'Time in and time out are required.';
            return false;
        }

        const start = parseTimeToMinutes(activeDraft.value.time_in);
        const end = parseTimeToMinutes(activeDraft.value.time_out);
        if (
            !activeDraft.value.is_overnight &&
            start !== null &&
            end !== null &&
            end <= start
        ) {
            formError.value =
                'Time out must be after time in, or enable overnight shift.';
            return false;
        }

        const gross = approximateScheduleDurationMinutes(
            activeDraft.value.time_in,
            activeDraft.value.time_out,
            activeDraft.value.is_overnight,
        );
        if (gross !== null && activeDraft.value.unpaid_break_minutes > gross) {
            formError.value = 'Breaktime cannot exceed the gross on-duty span.';
            return false;
        }

        if (
            !Number.isFinite(activeDraft.value.unpaid_break_minutes) ||
            activeDraft.value.unpaid_break_minutes < 0 ||
            !Number.isInteger(activeDraft.value.unpaid_break_minutes)
        ) {
            formError.value = 'Breaktime must be a whole number ≥ 0.';
            return false;
        }
    }

    const graceFields = numericDraftFields.filter(
        (f) => f.key !== 'unpaid_break_minutes',
    );
    for (const { key, label } of graceFields) {
        const value = activeDraft.value[key];
        if (!Number.isFinite(value) || value < 0 || !Number.isInteger(value)) {
            formError.value = `${label} must be a whole number ≥ 0.`;
            return false;
        }
    }

    if (!validateOvertimeRules()) {
        return false;
    }

    formError.value = null;
    return true;
}

async function submitAdd(): Promise<void> {
    if (!validateDraft()) return;

    normalizeSplitSegmentLabels(activeDraft.value);

    const createdScheduleName = activeDraft.value.name.trim();

    const operation = async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(workScheduleApi.store.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify(draftToPayload(activeDraft.value)),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstApiErrorFromPayload(payload) ??
                    'Unable to save work schedule.',
            );
        }

        reloadWorkScheduleTemplates(() => {
            addDialogOpen.value = false;
            resetDraft();
        });
    };

    await appToast.promise(operation(), {
        loading: 'Saving work schedule…',
        success: {
            message: 'Work schedule created.',
            description: createdScheduleName,
        },
        error: (error: unknown) =>
            error instanceof Error && error.message.trim() !== ''
                ? error.message
                : 'Unable to save work schedule.',
    });
}

async function submitEdit(): Promise<void> {
    if (!validateDraft() || editTargetId.value === null) return;

    normalizeSplitSegmentLabels(activeDraft.value);

    const targetId = editTargetId.value;
    const updatedScheduleName = activeDraft.value.name.trim();

    const operation = async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(
            workScheduleApi.update.url({ workScheduleTemplate: targetId }),
            {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify(draftToPayload(activeDraft.value)),
            },
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstApiErrorFromPayload(payload) ??
                    'Unable to update work schedule.',
            );
        }

        reloadWorkScheduleTemplates(() => {
            const id = editTargetId.value;
            if (id === null) {
                return;
            }
            const updated = props.workScheduleTemplates.find(
                (row) => row.id === id,
            );
            if (updated) {
                applyShiftToDraft(updated);
            }
        });
    };

    await appToast.promise(operation(), {
        loading: 'Updating work schedule…',
        success: {
            message: 'Work schedule updated.',
            description: updatedScheduleName,
        },
        error: (error: unknown) =>
            error instanceof Error && error.message.trim() !== ''
                ? error.message
                : 'Unable to update work schedule.',
    });
}

async function confirmDelete(): Promise<void> {
    if (!deleteTarget.value) return;

    const target = deleteTarget.value;

    const operation = async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(
            workScheduleApi.destroy.url({ workScheduleTemplate: target.id }),
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
            },
        );

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(
                firstApiErrorFromPayload(payload) ??
                    'Unable to delete work schedule.',
            );
        }

        reloadWorkScheduleTemplates(() => {
            deleteTarget.value = null;
            deleteDialogOpen.value = false;
        });
    };

    await appToast.promise(operation(), {
        loading: 'Deleting work schedule…',
        success: {
            message: 'Work schedule deleted.',
            description: target.name.trim(),
        },
        error: (error: unknown) =>
            error instanceof Error && error.message.trim() !== ''
                ? error.message
                : 'Unable to delete work schedule.',
    });
}

function formatTimeDisplay(value: string): string {
    if (!value.includes(':')) return value;
    const [hoursRaw, minutes] = value.split(':');
    const hours = Number.parseInt(hoursRaw, 10);
    if (!Number.isFinite(hours)) return value;
    const suffix = hours >= 12 ? 'PM' : 'AM';
    const normalized = hours % 12 === 0 ? 12 : hours % 12;
    return `${normalized}:${minutes} ${suffix}`;
}

/** Padded 12h times for Window column (aligned with shell clock typography). */
const windowTableClockFormatter = new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
});

function parseHhMmToLocalWallDate(value: string): Date | null {
    if (!value.includes(':')) {
        return null;
    }
    const [hoursRaw, minutesRaw] = value.split(':');
    const hours = Number.parseInt(hoursRaw, 10);
    const minutes = Number.parseInt(minutesRaw, 10);
    if (
        !Number.isFinite(hours) ||
        !Number.isFinite(minutes) ||
        hours < 0 ||
        hours > 23 ||
        minutes < 0 ||
        minutes > 59
    ) {
        return null;
    }

    return new Date(2000, 0, 1, hours, minutes, 0, 0);
}

function formatWindowTableClock(value: string): string {
    const d = parseHhMmToLocalWallDate(value);
    if (d === null) {
        return value;
    }

    return windowTableClockFormatter.format(d);
}

/** One line: “08:00 AM to 05:00 PM” (shell-style muted + foreground numerals). */
function renderWindowTimeSpanRow(timeIn: string, timeOut: string) {
    return h(
        'div',
        {
            class: 'flex flex-wrap items-baseline gap-x-1 leading-snug',
        },
        [
            h(
                'span',
                {
                    class: 'font-mono text-sm tabular-nums leading-normal text-foreground',
                },
                formatWindowTableClock(timeIn),
            ),
            h('span', { class: 'font-normal text-muted-foreground' }, 'to'),
            h(
                'span',
                {
                    class: 'font-mono text-sm tabular-nums leading-normal text-foreground',
                },
                formatWindowTableClock(timeOut),
            ),
        ],
    );
}

/** Minutes from midnight for HH:mm (24h). */
function parseTimeToMinutes(value: string): number | null {
    if (!value.includes(':')) return null;
    const [hRaw, mRaw] = value.split(':');
    const h = Number.parseInt(hRaw, 10);
    const m = Number.parseInt(mRaw, 10);
    if (!Number.isFinite(h) || !Number.isFinite(m)) return null;

    return h * 60 + m;
}

/**
 * Approximate on-duty span (breaks not deducted). Overnight crosses midnight into the next calendar day.
 */
function approximateScheduleDurationMinutes(
    timeIn: string,
    timeOut: string,
    isOvernight: boolean,
): number | null {
    const start = parseTimeToMinutes(timeIn);
    const end = parseTimeToMinutes(timeOut);
    if (start === null || end === null) return null;

    if (isOvernight || end <= start) {
        return 24 * 60 - start + end;
    }

    return end - start;
}

/** Minutes between one session's end and the next session's start (same calendar day). */
function interSessionGapMinutes(
    sessionEnd: string,
    nextSessionStart: string,
): number | null {
    const outM = parseTimeToMinutes(sessionEnd);
    const inM = parseTimeToMinutes(nextSessionStart);
    if (outM === null || inM === null) return null;
    if (inM < outM) return null;

    return inM - outM;
}

function splitInterSessionGapsTotalMinutes(
    segments: readonly ShiftSegment[],
): number | null {
    let total = 0;
    for (let i = 0; i < segments.length - 1; i++) {
        const gap = interSessionGapMinutes(
            segments[i].time_out,
            segments[i + 1].time_in,
        );
        if (gap === null) {
            return null;
        }
        total += gap;
    }

    return total;
}

function syncMirrorFromSegments(draft: ShiftDraft): void {
    if (draft.clock_pattern !== 'split_sessions' || !draft.segments) {
        return;
    }

    const segs = draft.segments;
    const first = segs[0];
    const last = segs[segs.length - 1];
    draft.time_in = first.time_in;
    draft.time_out = last.time_out;
    draft.is_overnight = false;
    const gaps = splitInterSessionGapsTotalMinutes(segs);
    draft.unpaid_break_minutes = gaps !== null ? gaps : 0;
}

function shiftGrossMinutes(shift: ShiftRule): number | null {
    if (shift.clock_pattern === 'split_sessions' && shift.segments) {
        let sum = 0;
        for (const seg of shift.segments) {
            const d = approximateScheduleDurationMinutes(
                seg.time_in,
                seg.time_out,
                seg.is_overnight,
            );
            if (d === null) return null;
            sum += d;
        }

        return sum;
    }

    return approximateScheduleDurationMinutes(
        shift.time_in,
        shift.time_out,
        shift.is_overnight,
    );
}

function shiftNetMinutes(shift: ShiftRule): number | null {
    const gross = shiftGrossMinutes(shift);
    if (gross === null) return null;

    return Math.max(0, gross - shift.unpaid_break_minutes);
}

function formatDurationMinutes(totalMinutes: number): string {
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    if (m === 0) {
        return `${h}h`;
    }

    return `${h}h ${m}m`;
}

function scheduleDurationLabel(shift: ShiftRule): string | null {
    const mins = shiftGrossMinutes(shift);
    if (mins === null) return null;

    return formatDurationMinutes(mins);
}

function netWorkingMinutes(shift: ShiftRule): number | null {
    return shiftNetMinutes(shift);
}

function netWorkingDurationLabel(shift: ShiftRule): string | null {
    const net = netWorkingMinutes(shift);
    if (net === null) return null;

    return formatDurationMinutes(net);
}

function normalizedNotesFromDraft(draft: ShiftDraft): string | null {
    const trimmed = draft.notes.trim();

    return trimmed === '' ? null : trimmed;
}

function draftToPayload(draft: ShiftDraft): Record<string, unknown> {
    return {
        name: draft.name.trim(),
        clock_pattern: draft.clock_pattern,
        days: [...draft.days],
        segments:
            draft.clock_pattern === 'split_sessions' && draft.segments
                ? draft.segments.map((segment) => ({
                      label: segment.label,
                      time_in: segment.time_in,
                      time_out: segment.time_out,
                      is_overnight: segment.is_overnight,
                  }))
                : null,
        time_in: draft.time_in,
        time_out: draft.time_out,
        is_overnight: draft.is_overnight,
        is_active: draft.is_active,
        unpaid_break_minutes: draft.unpaid_break_minutes,
        grace_late_arrival_minutes: draft.grace_late_arrival_minutes,
        notes: normalizedNotesFromDraft(draft),
        attendance_rules: { ...attendanceRulesDraft.value },
        overtime_rules: { ...overtimeRulesDraft.value },
    };
}

const draftGrossDurationLabel = computed(() => {
    if (
        activeDraft.value.clock_pattern === 'split_sessions' &&
        activeDraft.value.segments
    ) {
        let sum = 0;
        for (const seg of activeDraft.value.segments) {
            const d = approximateScheduleDurationMinutes(
                seg.time_in,
                seg.time_out,
                seg.is_overnight,
            );
            if (d === null) {
                return null;
            }
            sum += d;
        }

        return formatDurationMinutes(sum);
    }

    const mins = approximateScheduleDurationMinutes(
        activeDraft.value.time_in,
        activeDraft.value.time_out,
        activeDraft.value.is_overnight,
    );
    if (mins === null) {
        return null;
    }

    return formatDurationMinutes(mins);
});

const draftNetDurationLabel = computed(() => {
    if (
        activeDraft.value.clock_pattern === 'split_sessions' &&
        activeDraft.value.segments
    ) {
        const segs = activeDraft.value.segments;
        let gross = 0;
        for (const seg of segs) {
            const d = approximateScheduleDurationMinutes(
                seg.time_in,
                seg.time_out,
                seg.is_overnight,
            );
            if (d === null) {
                return null;
            }
            gross += d;
        }
        const gaps = splitInterSessionGapsTotalMinutes(segs);
        if (gaps === null) {
            return null;
        }

        return formatDurationMinutes(Math.max(0, gross - gaps));
    }

    const gross = approximateScheduleDurationMinutes(
        activeDraft.value.time_in,
        activeDraft.value.time_out,
        activeDraft.value.is_overnight,
    );
    if (gross === null) {
        return null;
    }

    return formatDurationMinutes(
        Math.max(0, gross - activeDraft.value.unpaid_break_minutes),
    );
});

/** Net working minutes from the current schedule draft (single pair or split); null if times are incomplete. */
function computeActiveDraftScheduledNetMinutes(): number | null {
    const d = activeDraft.value;
    if (d.clock_pattern === 'split_sessions' && d.segments) {
        const segs = d.segments;
        let gross = 0;
        for (const seg of segs) {
            const m = approximateScheduleDurationMinutes(
                seg.time_in,
                seg.time_out,
                seg.is_overnight,
            );
            if (m === null) {
                return null;
            }
            gross += m;
        }
        const gaps = splitInterSessionGapsTotalMinutes(segs);
        if (gaps === null) {
            return null;
        }

        return Math.max(0, gross - gaps);
    }

    const gross = approximateScheduleDurationMinutes(
        d.time_in,
        d.time_out,
        d.is_overnight,
    );
    if (gross === null) {
        return null;
    }

    return Math.max(0, gross - d.unpaid_break_minutes);
}

const draftScheduledNetHours = computed((): number | null => {
    const minutes = computeActiveDraftScheduledNetMinutes();
    if (minutes === null) {
        return null;
    }

    return minutes / 60;
});

/** Schedule tab "Overnight Shift" — single-session only (split sessions are same calendar day). */
const regularScheduleOvernight = computed(
    () =>
        activeDraft.value.clock_pattern === 'single_pair' &&
        activeDraft.value.is_overnight,
);

watch(regularScheduleOvernight, (enabled) => {
    if (!enabled) {
        overtimeRulesDraft.value = {
            ...overtimeRulesDraft.value,
            otIsOvernight: false,
        };
    }
});

watch(
    draftScheduledNetHours,
    (hours) => {
        if (hours === null) {
            return;
        }
        attendanceRulesDraft.value = {
            ...attendanceRulesDraft.value,
            netRegularHoursCap: hours,
        };
    },
    { immediate: true },
);

const draftSplitUnpaidGapsLabel = computed(() => {
    if (
        activeDraft.value.clock_pattern !== 'split_sessions' ||
        !activeDraft.value.segments
    ) {
        return null;
    }

    const gaps = splitInterSessionGapsTotalMinutes(activeDraft.value.segments);
    if (gaps === null) {
        return null;
    }

    return formatDurationMinutes(gaps);
});

function formatUnpaidBreakDisplay(minutes: number): string {
    if (minutes <= 0) {
        return '—';
    }

    return formatDurationMinutes(minutes);
}

function viewSplitUnpaidGapsLabel(shift: ShiftRule): string | null {
    if (shift.clock_pattern !== 'split_sessions' || !shift.segments) {
        return null;
    }

    const gaps = splitInterSessionGapsTotalMinutes(shift.segments);
    if (gaps === null) {
        return null;
    }

    return formatDurationMinutes(gaps);
}

function daysDisplay(days: DayOfWeekKey[]): string {
    return dayOfWeekOptions
        .filter((option) => days.includes(option.key))
        .map((option) => option.label)
        .join(', ');
}

const columns = computed<ColumnDef<ShiftRule>[]>(() => [
    {
        accessorKey: 'name',
        meta: { headClass: 'min-w-[12rem]' },
        header: () =>
            h(ShiftsIndexScheduleColumnHeader, {
                modelValue: schedulePatternFilter.value,
                'onUpdate:modelValue': (v: ShiftsSchedulePatternFilter) => {
                    schedulePatternFilter.value = v;
                },
            }),
        cell: ({ row }) => {
            const shift = row.original;
            const titleBits: ReturnType<typeof h>[] = [
                h(
                    'span',
                    {
                        class: 'min-w-0 font-medium leading-snug text-foreground',
                    },
                    shift.name,
                ),
            ];
            if (shift.clock_pattern === 'split_sessions') {
                titleBits.push(
                    h(
                        Badge,
                        {
                            variant: 'outline',
                            class: workScheduleSplitSessionsBadgeClass,
                        },
                        () => 'Split Sessions',
                    ),
                );
            }
            if (shift.clock_pattern === 'single_pair' && shift.is_overnight) {
                titleBits.push(
                    h(
                        Badge,
                        {
                            variant: 'outline',
                            class: workScheduleOvernightBadgeClass,
                        },
                        () => 'Overnight',
                    ),
                );
            }
            const titleRow = h(
                'div',
                { class: 'flex min-w-0 flex-wrap items-center gap-2' },
                titleBits,
            );

            return h(
                'div',
                { class: 'flex max-w-[18rem] flex-col gap-0.5 py-1' },
                [
                    titleRow,
                    h(
                        'span',
                        { class: 'text-xs leading-snug text-muted-foreground' },
                        daysDisplay(shift.days),
                    ),
                ],
            );
        },
    },
    {
        id: 'window',
        meta: {
            headClass: 'min-w-[12rem]',
            cellClass: 'whitespace-normal',
        },
        header: () => h('span', { class: tablePlainHeadClass }, 'Window'),
        cell: ({ row }) => {
            const shift = row.original;

            if (shift.clock_pattern === 'split_sessions' && shift.segments) {
                return h(
                    'div',
                    {
                        class: 'flex max-w-[16rem] flex-col gap-1.5 py-1 text-sm tabular-nums text-muted-foreground',
                    },
                    shift.segments.map((seg) =>
                        renderWindowTimeSpanRow(seg.time_in, seg.time_out),
                    ),
                );
            }

            return h(
                'div',
                {
                    class: 'max-w-[16rem] py-1 text-sm tabular-nums text-muted-foreground',
                },
                [renderWindowTimeSpanRow(shift.time_in, shift.time_out)],
            );
        },
    },
    {
        id: 'gross_time',
        meta: {
            headClass: 'min-w-[6.5rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(WorkScheduleMetricLabel, {
                metric: 'gross',
                variant: 'table',
            }),
        cell: ({ row }) => {
            const label = scheduleDurationLabel(row.original);

            return h(
                'span',
                {
                    class: 'font-mono text-sm tabular-nums leading-normal text-foreground',
                },
                label ?? '—',
            );
        },
    },
    {
        id: 'net_time',
        meta: {
            headClass: 'min-w-[6.5rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(WorkScheduleMetricLabel, {
                metric: 'net',
                variant: 'table',
            }),
        cell: ({ row }) => {
            const label = netWorkingDurationLabel(row.original);

            return h(
                'span',
                {
                    class: 'font-mono text-sm tabular-nums leading-normal text-foreground',
                },
                label ?? '—',
            );
        },
    },
    {
        id: 'unpaid_break',
        header: () => h('span', { class: tablePlainHeadClass }, 'Breaktime'),
        cell: ({ row }) => {
            const shift = row.original;
            const label = formatUnpaidBreakDisplay(shift.unpaid_break_minutes);

            return h(
                'span',
                {
                    class: 'font-mono text-sm tabular-nums leading-normal text-foreground',
                },
                label,
            );
        },
    },
    {
        id: 'status',
        header: () =>
            h(ShiftsIndexStatusColumnHeader, {
                modelValue: statusColumnFilter.value,
                'onUpdate:modelValue': (v: ShiftsStatusFilter) => {
                    statusColumnFilter.value = v;
                },
            }),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'outline',
                    class: row.original.is_active
                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300'
                        : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300',
                },
                () => (row.original.is_active ? 'Active' : 'Inactive'),
            ),
    },
    {
        id: 'actions',
        meta: { headClass: 'w-[100px] text-center', cellClass: 'text-center' },
        header: () =>
            h(
                'div',
                {
                    class: `w-full text-center ${tablePlainHeadClass}`,
                },
                'Actions',
            ),
        cell: ({ row }) =>
            h(ShiftsActionsMenu, {
                row: row.original,
                canManage: canMutateWorkSchedules.value,
                onView: openViewDialog,
                onEdit: openEditDialog,
                onDelete: openDeleteDialog,
            }),
    },
]);

const table = useVueTable({
    get data() {
        return paginatedShifts.value;
    },
    get columns() {
        return columns.value;
    },
    getCoreRowModel: getCoreRowModel(),
});
</script>

<template>
    <Head title="Work Schedules" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Work Schedules
                </h1>
                <p class="text-sm text-muted-foreground">
                    Define reusable work schedules—when people are expected to
                    work and how attendance rules apply to those schedules.
                </p>
            </div>

            <HrisIndexToolbar>
                <template #start>
                    <div class="w-full max-w-md">
                        <InputGroup class="max-w-md">
                            <InputGroupAddon align="inline-start">
                                <Search
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="work-schedules-search"
                                :model-value="localSearch"
                                placeholder="Search schedules, days, or times…"
                                @update:model-value="onSearchUpdate"
                            />
                        </InputGroup>
                    </div>
                </template>
                <template #end>
                    <Button
                        v-if="canMutateWorkSchedules"
                        type="button"
                        class="shrink-0"
                        @click="openAddDialog"
                    >
                        <Plus class="size-4" />
                        <span class="mr-1">Add Work Schedule</span>
                    </Button>
                </template>
            </HrisIndexToolbar>

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    :empty-message="emptyTableMessage"
                />

                <HrisServerTablePagination
                    :total="totalRows"
                    :from="fromRow"
                    :to="toRow"
                    :current-page="currentPage"
                    :last-page="lastPage"
                    :per-page="perPage"
                    :can-previous-page="currentPage > 1"
                    :can-next-page="currentPage < lastPage"
                    @update:per-page="onPerPageChange"
                    @go-first="setPage(1)"
                    @go-prev="setPage(currentPage - 1)"
                    @go-next="setPage(currentPage + 1)"
                    @go-last="setPage(lastPage)"
                />
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="viewDialogOpen">
        <DialogContent
            class="flex max-h-[90vh] min-h-0 w-full max-w-xl flex-col gap-4 overflow-hidden sm:max-w-xl"
        >
            <DialogHeader class="shrink-0 space-y-2">
                <DialogTitle>View Work Schedule</DialogTitle>
                <DialogDescription>
                    Scheduled window, breaktime, computed hours, late-arrival
                    grace (first start of day), saved attendance and overtime
                    rule blocks (when present), and optional notes.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="dialogViewScrollAreaClass">
                <div class="grid gap-3 px-2 pt-2 pb-20 text-sm sm:grid-cols-2">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Schedule name
                        </p>
                        <div
                            class="flex min-h-10 flex-wrap items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                        >
                            <span class="min-w-0 font-medium">{{
                                viewTarget?.name ?? '—'
                            }}</span>
                            <Badge
                                v-if="
                                    viewTarget?.clock_pattern ===
                                    'split_sessions'
                                "
                                variant="outline"
                                :class="workScheduleSplitSessionsBadgeClass"
                            >
                                Split Sessions
                            </Badge>
                            <Badge
                                v-if="
                                    viewTarget?.clock_pattern ===
                                        'single_pair' &&
                                    viewTarget?.is_overnight
                                "
                                variant="outline"
                                :class="workScheduleOvernightBadgeClass"
                            >
                                Overnight
                            </Badge>
                        </div>
                    </div>

                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Working days
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                        >
                            {{
                                viewTarget ? daysDisplay(viewTarget.days) : '—'
                            }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                        <div
                            v-if="viewTarget?.clock_pattern === 'single_pair'"
                            class="grid gap-1.5"
                        >
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Overnight Shift
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                            >
                                {{ viewTarget?.is_overnight ? 'Yes' : 'No' }}
                            </p>
                        </div>
                        <div
                            class="grid gap-1.5"
                            :class="
                                viewTarget?.clock_pattern === 'split_sessions'
                                    ? 'sm:col-span-2'
                                    : ''
                            "
                        >
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Active
                            </p>
                            <div
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                            >
                                <Badge
                                    variant="outline"
                                    :class="
                                        viewTarget?.is_active
                                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300'
                                            : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300'
                                    "
                                >
                                    {{
                                        viewTarget?.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="viewTarget?.clock_pattern === 'single_pair'"
                        class="grid gap-3 rounded-lg border border-border/60 bg-muted/15 p-4 sm:col-span-2"
                    >
                        <div>
                            <p class="text-sm font-medium text-foreground">
                                Working schedule
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Single Session — one continuous clock window;
                                breaktime is explicit below.
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Clock pattern
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                            >
                                Single Session
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-foreground">
                                Schedule window & hours
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Clock times and breaktime; gross and net are
                                computed from the window.
                            </p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Time in
                                </p>
                                <p
                                    class="inline-flex items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                                >
                                    <Clock3
                                        class="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {{
                                        viewTarget
                                            ? formatTimeDisplay(
                                                  viewTarget.time_in,
                                              )
                                            : '—'
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Time out
                                </p>
                                <p
                                    class="inline-flex items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                                >
                                    <Clock3
                                        class="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {{
                                        viewTarget
                                            ? formatTimeDisplay(
                                                  viewTarget.time_out,
                                              )
                                            : '—'
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="grid gap-1.5">
                                <WorkScheduleMetricLabel
                                    metric="gross"
                                    variant="view"
                                />
                                <p
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                                >
                                    <template
                                        v-if="
                                            viewTarget &&
                                            scheduleDurationLabel(viewTarget)
                                        "
                                    >
                                        {{ scheduleDurationLabel(viewTarget) }}
                                    </template>
                                    <template v-else> — </template>
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Breaktime
                                </p>
                                <p
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                                >
                                    {{
                                        viewTarget
                                            ? formatUnpaidBreakDisplay(
                                                  viewTarget.unpaid_break_minutes,
                                              )
                                            : '—'
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <WorkScheduleMetricLabel
                                    metric="net"
                                    variant="view"
                                />
                                <p
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                                >
                                    <template
                                        v-if="
                                            viewTarget &&
                                            netWorkingDurationLabel(viewTarget)
                                        "
                                    >
                                        {{
                                            netWorkingDurationLabel(viewTarget)
                                        }}
                                    </template>
                                    <template v-else> — </template>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="
                            viewTarget?.clock_pattern === 'split_sessions' &&
                            viewTarget.segments
                        "
                        class="grid gap-3 rounded-lg border border-border/60 bg-muted/15 p-4 sm:col-span-2"
                    >
                        <div>
                            <p class="text-sm font-medium text-foreground">
                                Working schedule
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Split Sessions — gaps between Session 1 and 2
                                are unpaid breaktime (derived). Scheduled
                                overtime uses the Overtime tab (not an extra
                                session row here).
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Clock pattern
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                            >
                                Split Sessions
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-foreground">
                                Schedule window & hours
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Session times and derived breaktime; gross and
                                net sum sessions minus unpaid breaktime.
                            </p>
                        </div>
                        <div class="grid gap-3">
                            <div
                                v-for="(seg, idx) in viewTarget.segments"
                                :key="`view-seg-${idx}`"
                                class="rounded-md border border-border/50 bg-muted/20 px-3 py-2"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Session {{ idx + 1 }}
                                </p>
                                <p
                                    class="mt-1 inline-flex flex-wrap items-center gap-2 text-sm text-foreground tabular-nums"
                                >
                                    <Clock3
                                        class="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {{ formatTimeDisplay(seg.time_in) }} –
                                    {{ formatTimeDisplay(seg.time_out) }}
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="grid gap-1.5">
                                <WorkScheduleMetricLabel
                                    metric="gross"
                                    variant="view"
                                />
                                <p
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                                >
                                    <template
                                        v-if="
                                            viewTarget &&
                                            scheduleDurationLabel(viewTarget)
                                        "
                                    >
                                        {{ scheduleDurationLabel(viewTarget) }}
                                    </template>
                                    <template v-else> — </template>
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Breaktime (minutes)
                                </p>
                                <p
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                                >
                                    {{
                                        viewTarget
                                            ? (viewSplitUnpaidGapsLabel(
                                                  viewTarget,
                                              ) ?? '—')
                                            : '—'
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <WorkScheduleMetricLabel
                                    metric="net"
                                    variant="view"
                                />
                                <p
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                                >
                                    <template
                                        v-if="
                                            viewTarget &&
                                            netWorkingDurationLabel(viewTarget)
                                        "
                                    >
                                        {{
                                            netWorkingDurationLabel(viewTarget)
                                        }}
                                    </template>
                                    <template v-else> — </template>
                                </p>
                            </div>
                        </div>
                    </div>

                    <Separator class="sm:col-span-2" />

                    <div class="grid gap-2 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Late arrival grace
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                        >
                            {{ viewTarget?.grace_late_arrival_minutes ?? 0 }}
                            min
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Applies to the scheduled first start of the day only
                            (Single Session: time in; Split Sessions: Session 1
                            time in only—not Session 2 or 3). Not for clock-out
                            or overtime rules (policy).
                        </p>
                    </div>

                    <Separator class="sm:col-span-2" />

                    <div class="grid gap-2 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Attendance rules (saved)
                        </p>
                        <template v-if="viewAttendanceDisplay === null">
                            <p class="text-xs text-muted-foreground">
                                Not saved yet. Until you save a template from
                                the editor, attendance tab defaults apply only
                                during editing — nothing extra is persisted.
                            </p>
                        </template>
                        <div
                            v-else
                            class="grid gap-3 rounded-lg border border-border/60 bg-muted/15 p-3 text-sm"
                        >
                            <div
                                class="grid gap-2 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-2"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Round clock-in to</span
                                >
                                <span class="text-foreground tabular-nums">{{
                                    viewClockInRoundingSummary(
                                        viewAttendanceDisplay,
                                    )
                                }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >Grace uses cap per month</span
                                >
                                <span class="text-foreground">{{
                                    viewAttendanceDisplay.graceUsesLimitEnabled
                                        ? `Yes — ${viewAttendanceDisplay.graceUsesPerMonth} uses`
                                        : 'No limit'
                                }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >Cap net regular hours</span
                                >
                                <span class="text-foreground tabular-nums">{{
                                    viewAttendanceDisplay.netRegularHoursCapEnabled
                                        ? `${formatWorkScheduleNetHours(viewAttendanceDisplay.netRegularHoursCap)} h`
                                        : 'No cap'
                                }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Late-arrival grace (minutes) for the first start
                                of day is above; clock-in rounding and caps are
                                engine-oriented fields stored with this
                                template.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Overtime rules (saved)
                        </p>
                        <template v-if="viewOvertimeDisplay === null">
                            <p class="text-xs text-muted-foreground">
                                Not saved yet. Editor defaults apply until you
                                save the template with an overtime block.
                            </p>
                        </template>
                        <div
                            v-else
                            class="grid gap-3 rounded-lg border border-border/60 bg-muted/15 p-3 text-sm"
                        >
                            <div
                                class="grid gap-2 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-2"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Schedule overtime block</span
                                >
                                <span class="text-foreground">{{
                                    viewOvertimeDisplay.otBlockEnabled
                                        ? 'Yes'
                                        : 'No'
                                }}</span>
                            </div>
                            <div
                                v-if="viewOvertimeDisplay.otBlockEnabled"
                                class="grid gap-3 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-2"
                            >
                                <div class="grid gap-1">
                                    <span class="text-xs text-muted-foreground"
                                        >OT time in</span
                                    >
                                    <span
                                        class="inline-flex items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-foreground tabular-nums"
                                    >
                                        <Clock3
                                            class="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        {{
                                            formatTimeDisplay(
                                                viewOvertimeDisplay.otTimeIn,
                                            )
                                        }}
                                    </span>
                                </div>
                                <div class="grid gap-1">
                                    <span class="text-xs text-muted-foreground"
                                        >OT time out</span
                                    >
                                    <span
                                        class="inline-flex items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-foreground tabular-nums"
                                    >
                                        <Clock3
                                            class="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        {{
                                            formatTimeDisplay(
                                                viewOvertimeDisplay.otTimeOut,
                                            )
                                        }}
                                    </span>
                                </div>
                                <span class="text-xs text-muted-foreground"
                                    >Implied OT span</span
                                >
                                <span class="text-foreground tabular-nums">{{
                                    viewOvertimeSpanSummary ?? '—'
                                }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >Overnight OT span</span
                                >
                                <span class="text-foreground">{{
                                    viewOvertimeDisplay.otIsOvernight
                                        ? 'Yes'
                                        : 'No'
                                }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >Continuous after regular net</span
                                >
                                <span class="text-foreground">{{
                                    viewOvertimeDisplay.continuousAfterRegularNet
                                        ? 'Yes'
                                        : 'No'
                                }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >OT boundary grace</span
                                >
                                <span class="text-foreground tabular-nums"
                                    >{{
                                        viewOvertimeDisplay.otGraceMinutes
                                    }}
                                    min</span
                                >
                            </div>
                        </div>
                    </div>

                    <Separator class="sm:col-span-2" />

                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Notes
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm whitespace-pre-wrap text-foreground"
                        >
                            <template
                                v-if="
                                    viewTarget?.notes &&
                                    viewTarget.notes.trim() !== ''
                                "
                            >
                                {{ viewTarget.notes }}
                            </template>
                            <span v-else class="text-xs text-muted-foreground"
                                >No notes.</span
                            >
                        </p>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter class="shrink-0 gap-2">
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

    <Dialog v-model:open="addDialogOpen">
        <DialogContent
            class="flex max-h-[90vh] min-h-0 w-full max-w-2xl flex-col gap-4 overflow-hidden sm:max-w-2xl"
        >
            <DialogHeader class="shrink-0 space-y-2">
                <DialogTitle>Add Work Schedule</DialogTitle>
                <DialogDescription>
                    Define the schedule window, then set attendance and overtime
                    rules (attendance &amp; OT below are UI-only until the
                    engine ships). Breaktime uses whole minutes; overnight means
                    time out is the next calendar day.
                </DialogDescription>
            </DialogHeader>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <Tabs
                    v-model="scheduleFormTab"
                    class="flex min-h-0 flex-1 flex-col gap-3"
                >
                    <TabsList
                        class="grid h-auto w-full shrink-0 grid-cols-3 gap-1 rounded-md border border-border/50 bg-muted/15 p-1"
                    >
                        <TabsTrigger value="schedule">Schedule</TabsTrigger>
                        <TabsTrigger value="attendance">Attendance</TabsTrigger>
                        <TabsTrigger value="overtime">Overtime</TabsTrigger>
                    </TabsList>

                    <ScrollArea :class="dialogScheduleFormScrollClass">
                        <div class="px-1 pt-1 pb-20">
                            <TabsContent value="schedule" class="mt-0">
                                <div class="grid gap-4">
                                    <div class="grid gap-2">
                                        <Label for="shift-name"
                                            >Schedule name</Label
                                        >
                                        <Input
                                            id="shift-name"
                                            v-model="activeDraft.name"
                                            placeholder="e.g. Weekday office template"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label>Working days</Label>
                                        <div
                                            class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                                        >
                                            <label
                                                v-for="day in dayOfWeekOptions"
                                                :key="day.key"
                                                class="flex items-center gap-2 rounded-md border border-border/60 px-2 py-1.5"
                                            >
                                                <Checkbox
                                                    :model-value="
                                                        activeDraft.days.includes(
                                                            day.key,
                                                        )
                                                    "
                                                    @update:model-value="
                                                        toggleDraftDay(
                                                            day.key,
                                                            $event,
                                                        )
                                                    "
                                                />
                                                <span
                                                    class="text-sm text-foreground"
                                                    >{{ day.label }}</span
                                                >
                                            </label>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                    >
                                        <template
                                            v-if="
                                                activeDraft.clock_pattern ===
                                                'single_pair'
                                            "
                                        >
                                            <div
                                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-3"
                                            >
                                                <span
                                                    class="text-sm font-medium text-foreground"
                                                    >Overnight Shift</span
                                                >
                                                <Switch
                                                    v-model="
                                                        activeDraft.is_overnight
                                                    "
                                                />
                                            </div>
                                            <div
                                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-3"
                                            >
                                                <span
                                                    class="text-sm font-medium text-foreground"
                                                    >Active</span
                                                >
                                                <Switch
                                                    v-model="
                                                        activeDraft.is_active
                                                    "
                                                />
                                            </div>
                                        </template>
                                        <div
                                            v-else
                                            class="flex w-full min-w-0 items-center justify-between rounded-md border border-border/60 px-3 py-3 sm:col-span-2"
                                        >
                                            <span
                                                class="text-sm font-medium text-foreground"
                                                >Active</span
                                            >
                                            <Switch
                                                v-model="activeDraft.is_active"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="space-y-4 rounded-lg border border-border/60 bg-muted/15 p-4"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                Working schedule
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                Choose Single Session (one
                                                window) or Split Sessions (gaps
                                                between blocks are unpaid
                                                breaktime, derived from times).
                                            </p>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="add-clock-pattern"
                                                >Clock pattern</Label
                                            >
                                            <Select
                                                :model-value="
                                                    activeDraft.clock_pattern
                                                "
                                                @update:model-value="
                                                    onClockPatternChange
                                                "
                                            >
                                                <SelectTrigger
                                                    id="add-clock-pattern"
                                                    class="w-full"
                                                >
                                                    <SelectValue
                                                        placeholder="Clock pattern"
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value="single_pair"
                                                    >
                                                        Single Session
                                                    </SelectItem>
                                                    <SelectItem
                                                        value="split_sessions"
                                                    >
                                                        Split Sessions
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <Separator class="bg-border/70" />

                                        <template
                                            v-if="
                                                activeDraft.clock_pattern ===
                                                'single_pair'
                                            "
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-medium text-foreground"
                                                >
                                                    Schedule window & hours
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Set wall-clock times and
                                                    breaktime; gross and net
                                                    update here so you can tune
                                                    without scrolling.
                                                </p>
                                            </div>
                                            <div
                                                class="grid gap-3 sm:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label for="shift-time-in"
                                                        >Time in</Label
                                                    >
                                                    <Input
                                                        id="shift-time-in"
                                                        v-model="
                                                            activeDraft.time_in
                                                        "
                                                        type="time"
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label for="shift-time-out"
                                                        >Time out</Label
                                                    >
                                                    <Input
                                                        id="shift-time-out"
                                                        v-model="
                                                            activeDraft.time_out
                                                        "
                                                        type="time"
                                                    />
                                                </div>
                                            </div>
                                            <div
                                                class="grid gap-3 sm:grid-cols-3"
                                            >
                                                <div class="grid gap-2">
                                                    <div
                                                        class="flex min-h-9 items-center"
                                                    >
                                                        <WorkScheduleMetricLabel
                                                            metric="gross"
                                                        />
                                                    </div>
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftGrossDurationLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="grid gap-2">
                                                    <div
                                                        class="flex min-h-9 items-center"
                                                    >
                                                        <Label
                                                            for="add-break-time"
                                                            >Breaktime
                                                            (minutes)</Label
                                                        >
                                                    </div>
                                                    <Input
                                                        id="add-break-time"
                                                        v-model.number="
                                                            activeDraft.unpaid_break_minutes
                                                        "
                                                        type="number"
                                                        min="0"
                                                        step="5"
                                                        class="tabular-nums"
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <div
                                                        class="flex min-h-9 items-center"
                                                    >
                                                        <WorkScheduleMetricLabel
                                                            metric="net"
                                                        />
                                                    </div>
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftNetDurationLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </template>

                                        <template
                                            v-else-if="activeDraft.segments"
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-medium text-foreground"
                                                >
                                                    Schedule window & hours
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    At least two same-day
                                                    sessions; each next time in
                                                    must be at or after the
                                                    previous time out. Breaktime
                                                    (minutes) should match the
                                                    sum of unpaid gaps between
                                                    sessions—add a third row
                                                    when overtime is modeled as
                                                    its own session window, or
                                                    use the Overtime tab for a
                                                    parallel OT block.
                                                </p>
                                            </div>
                                            <div
                                                v-for="(
                                                    seg, segIdx
                                                ) in activeDraft.segments"
                                                :key="`add-seg-${segIdx}`"
                                                class="space-y-3 rounded-md border border-border/50 bg-background/50 p-3"
                                            >
                                                <p
                                                    class="text-sm font-medium text-foreground"
                                                >
                                                    {{
                                                        seg.label.trim() !== ''
                                                            ? seg.label
                                                            : `Session ${segIdx + 1}`
                                                    }}
                                                </p>
                                                <div
                                                    class="grid gap-2 sm:grid-cols-2"
                                                >
                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`add-seg-in-${segIdx}`"
                                                            >Time in</Label
                                                        >
                                                        <Input
                                                            :id="`add-seg-in-${segIdx}`"
                                                            v-model="
                                                                seg.time_in
                                                            "
                                                            type="time"
                                                        />
                                                    </div>
                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`add-seg-out-${segIdx}`"
                                                            >Time out</Label
                                                        >
                                                        <Input
                                                            :id="`add-seg-out-${segIdx}`"
                                                            v-model="
                                                                seg.time_out
                                                            "
                                                            type="time"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="grid gap-3 sm:grid-cols-3"
                                            >
                                                <div class="grid gap-2">
                                                    <WorkScheduleMetricLabel
                                                        metric="gross"
                                                    />
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftGrossDurationLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        >Breaktime
                                                        (minutes)</Label
                                                    >
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftSplitUnpaidGapsLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="grid gap-2">
                                                    <WorkScheduleMetricLabel
                                                        metric="net"
                                                    />
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftNetDurationLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="space-y-2">
                                        <Label
                                            for="add-shift-notes"
                                            :class="optionalLabelRowClass"
                                        >
                                            <span>Notes</span>
                                            <Badge variant="outline">
                                                Optional
                                            </Badge>
                                            <Button
                                                v-if="
                                                    (
                                                        activeDraft.notes ?? ''
                                                    ).trim() !== ''
                                                "
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                :class="clearFieldButtonClass"
                                                aria-label="Clear notes"
                                                @click="activeDraft.notes = ''"
                                            >
                                                <X class="size-3.5" />
                                            </Button>
                                        </Label>
                                        <Textarea
                                            id="add-shift-notes"
                                            v-model="activeDraft.notes"
                                            rows="4"
                                            placeholder="Policy context, coverage, location…"
                                            class="resize-y"
                                        />
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="attendance" class="mt-0">
                                <WorkScheduleAttendanceRulesPanel
                                    v-model="attendanceRulesDraft"
                                    v-model:grace-minutes="
                                        activeDraft.grace_late_arrival_minutes
                                    "
                                    id-prefix="add-att"
                                    :clock-pattern="activeDraft.clock_pattern"
                                    :scheduled-net-hours="
                                        draftScheduledNetHours
                                    "
                                />
                            </TabsContent>

                            <TabsContent value="overtime" class="mt-0">
                                <WorkScheduleOvertimeRulesPanel
                                    v-model="overtimeRulesDraft"
                                    id-prefix="add-ot"
                                    :clock-pattern="activeDraft.clock_pattern"
                                    :regular-schedule-overnight="
                                        regularScheduleOvernight
                                    "
                                    :scheduled-net-hours="
                                        draftScheduledNetHours
                                    "
                                />
                            </TabsContent>

                            <p
                                v-if="formError"
                                class="mt-4 text-sm text-destructive"
                            >
                                {{ formError }}
                            </p>
                        </div>
                    </ScrollArea>
                </Tabs>
            </div>

            <DialogFooter class="shrink-0 gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="addDialogOpen = false"
                    >Cancel</Button
                >
                <Button type="button" @click="submitAdd">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="editDialogOpen">
        <DialogContent
            class="flex max-h-[90vh] min-h-0 w-full max-w-2xl flex-col gap-4 overflow-hidden sm:max-w-2xl"
        >
            <DialogHeader class="shrink-0 space-y-2">
                <DialogTitle>Edit Work Schedule</DialogTitle>
                <DialogDescription>
                    Update the schedule window and attendance / overtime rule
                    drafts (attendance &amp; OT tabs are UI-only until
                    persisted). Breaktime uses whole minutes; overnight means
                    time out is the next calendar day.
                </DialogDescription>
            </DialogHeader>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <Tabs
                    v-model="scheduleFormTab"
                    class="flex min-h-0 flex-1 flex-col gap-3"
                >
                    <TabsList
                        class="grid h-auto w-full shrink-0 grid-cols-3 gap-1 rounded-md border border-border/50 bg-muted/15 p-1"
                    >
                        <TabsTrigger value="schedule">Schedule</TabsTrigger>
                        <TabsTrigger value="attendance">Attendance</TabsTrigger>
                        <TabsTrigger value="overtime">Overtime</TabsTrigger>
                    </TabsList>

                    <ScrollArea :class="dialogScheduleFormScrollClass">
                        <div class="px-1 pt-1 pb-20">
                            <TabsContent value="schedule" class="mt-0">
                                <div class="grid gap-4">
                                    <div class="grid gap-2">
                                        <Label for="edit-shift-name"
                                            >Schedule name</Label
                                        >
                                        <Input
                                            id="edit-shift-name"
                                            v-model="activeDraft.name"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label>Working days</Label>
                                        <div
                                            class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                                        >
                                            <label
                                                v-for="day in dayOfWeekOptions"
                                                :key="`edit-${day.key}`"
                                                class="flex items-center gap-2 rounded-md border border-border/60 px-2 py-1.5"
                                            >
                                                <Checkbox
                                                    :model-value="
                                                        activeDraft.days.includes(
                                                            day.key,
                                                        )
                                                    "
                                                    @update:model-value="
                                                        toggleDraftDay(
                                                            day.key,
                                                            $event,
                                                        )
                                                    "
                                                />
                                                <span
                                                    class="text-sm text-foreground"
                                                    >{{ day.label }}</span
                                                >
                                            </label>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                    >
                                        <template
                                            v-if="
                                                activeDraft.clock_pattern ===
                                                'single_pair'
                                            "
                                        >
                                            <div
                                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-3"
                                            >
                                                <span
                                                    class="text-sm font-medium text-foreground"
                                                    >Overnight Shift</span
                                                >
                                                <Switch
                                                    v-model="
                                                        activeDraft.is_overnight
                                                    "
                                                />
                                            </div>
                                            <div
                                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-3"
                                            >
                                                <span
                                                    class="text-sm font-medium text-foreground"
                                                    >Active</span
                                                >
                                                <Switch
                                                    v-model="
                                                        activeDraft.is_active
                                                    "
                                                />
                                            </div>
                                        </template>
                                        <div
                                            v-else
                                            class="flex w-full min-w-0 items-center justify-between rounded-md border border-border/60 px-3 py-3 sm:col-span-2"
                                        >
                                            <span
                                                class="text-sm font-medium text-foreground"
                                                >Active</span
                                            >
                                            <Switch
                                                v-model="activeDraft.is_active"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="space-y-4 rounded-lg border border-border/60 bg-muted/15 p-4"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                Working schedule
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                Choose Single Session (one
                                                window) or Split Sessions (gaps
                                                between blocks are unpaid
                                                breaktime, derived from times).
                                            </p>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="edit-clock-pattern"
                                                >Clock pattern</Label
                                            >
                                            <Select
                                                :model-value="
                                                    activeDraft.clock_pattern
                                                "
                                                @update:model-value="
                                                    onClockPatternChange
                                                "
                                            >
                                                <SelectTrigger
                                                    id="edit-clock-pattern"
                                                    class="w-full"
                                                >
                                                    <SelectValue
                                                        placeholder="Clock pattern"
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value="single_pair"
                                                    >
                                                        Single Session
                                                    </SelectItem>
                                                    <SelectItem
                                                        value="split_sessions"
                                                    >
                                                        Split Sessions
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <Separator class="bg-border/70" />

                                        <template
                                            v-if="
                                                activeDraft.clock_pattern ===
                                                'single_pair'
                                            "
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-medium text-foreground"
                                                >
                                                    Schedule window & hours
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Set wall-clock times and
                                                    breaktime; gross and net
                                                    update here so you can tune
                                                    without scrolling.
                                                </p>
                                            </div>
                                            <div
                                                class="grid gap-3 sm:grid-cols-2"
                                            >
                                                <div class="grid gap-2">
                                                    <Label
                                                        for="edit-shift-time-in"
                                                        >Time in</Label
                                                    >
                                                    <Input
                                                        id="edit-shift-time-in"
                                                        v-model="
                                                            activeDraft.time_in
                                                        "
                                                        type="time"
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        for="edit-shift-time-out"
                                                        >Time out</Label
                                                    >
                                                    <Input
                                                        id="edit-shift-time-out"
                                                        v-model="
                                                            activeDraft.time_out
                                                        "
                                                        type="time"
                                                    />
                                                </div>
                                            </div>
                                            <div
                                                class="grid gap-3 sm:grid-cols-3"
                                            >
                                                <div class="grid gap-2">
                                                    <div
                                                        class="flex min-h-9 items-center"
                                                    >
                                                        <WorkScheduleMetricLabel
                                                            metric="gross"
                                                        />
                                                    </div>
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftGrossDurationLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="grid gap-2">
                                                    <div
                                                        class="flex min-h-9 items-center"
                                                    >
                                                        <Label
                                                            for="edit-break-time"
                                                            >Breaktime
                                                            (minutes)</Label
                                                        >
                                                    </div>
                                                    <Input
                                                        id="edit-break-time"
                                                        v-model.number="
                                                            activeDraft.unpaid_break_minutes
                                                        "
                                                        type="number"
                                                        min="0"
                                                        step="5"
                                                        class="tabular-nums"
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <div
                                                        class="flex min-h-9 items-center"
                                                    >
                                                        <WorkScheduleMetricLabel
                                                            metric="net"
                                                        />
                                                    </div>
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftNetDurationLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </template>

                                        <template
                                            v-else-if="activeDraft.segments"
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-medium text-foreground"
                                                >
                                                    Schedule window & hours
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    At least two same-day
                                                    sessions; each next time in
                                                    must be at or after the
                                                    previous time out. Breaktime
                                                    (minutes) should match the
                                                    sum of unpaid gaps between
                                                    sessions—add a third row
                                                    when overtime is modeled as
                                                    its own session window, or
                                                    use the Overtime tab for a
                                                    parallel OT block.
                                                </p>
                                            </div>
                                            <div
                                                v-for="(
                                                    seg, segIdx
                                                ) in activeDraft.segments"
                                                :key="`edit-seg-${segIdx}`"
                                                class="space-y-3 rounded-md border border-border/50 bg-background/50 p-3"
                                            >
                                                <p
                                                    class="text-sm font-medium text-foreground"
                                                >
                                                    {{
                                                        seg.label.trim() !== ''
                                                            ? seg.label
                                                            : `Session ${segIdx + 1}`
                                                    }}
                                                </p>
                                                <div
                                                    class="grid gap-2 sm:grid-cols-2"
                                                >
                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`edit-seg-in-${segIdx}`"
                                                            >Time in</Label
                                                        >
                                                        <Input
                                                            :id="`edit-seg-in-${segIdx}`"
                                                            v-model="
                                                                seg.time_in
                                                            "
                                                            type="time"
                                                        />
                                                    </div>
                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`edit-seg-out-${segIdx}`"
                                                            >Time out</Label
                                                        >
                                                        <Input
                                                            :id="`edit-seg-out-${segIdx}`"
                                                            v-model="
                                                                seg.time_out
                                                            "
                                                            type="time"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="grid gap-3 sm:grid-cols-3"
                                            >
                                                <div class="grid gap-2">
                                                    <WorkScheduleMetricLabel
                                                        metric="gross"
                                                    />
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftGrossDurationLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        >Breaktime
                                                        (minutes)</Label
                                                    >
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftSplitUnpaidGapsLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="grid gap-2">
                                                    <WorkScheduleMetricLabel
                                                        metric="net"
                                                    />
                                                    <p
                                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                                    >
                                                        {{
                                                            draftNetDurationLabel ??
                                                            '—'
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="space-y-2">
                                        <Label
                                            for="edit-shift-notes"
                                            :class="optionalLabelRowClass"
                                        >
                                            <span>Notes</span>
                                            <Badge variant="outline">
                                                Optional
                                            </Badge>
                                            <Button
                                                v-if="
                                                    (
                                                        activeDraft.notes ?? ''
                                                    ).trim() !== ''
                                                "
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                :class="clearFieldButtonClass"
                                                aria-label="Clear notes"
                                                @click="activeDraft.notes = ''"
                                            >
                                                <X class="size-3.5" />
                                            </Button>
                                        </Label>
                                        <Textarea
                                            id="edit-shift-notes"
                                            v-model="activeDraft.notes"
                                            rows="4"
                                            placeholder="Policy context, coverage, location…"
                                            class="resize-y"
                                        />
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="attendance" class="mt-0">
                                <WorkScheduleAttendanceRulesPanel
                                    v-model="attendanceRulesDraft"
                                    v-model:grace-minutes="
                                        activeDraft.grace_late_arrival_minutes
                                    "
                                    id-prefix="edit-att"
                                    :clock-pattern="activeDraft.clock_pattern"
                                    :scheduled-net-hours="
                                        draftScheduledNetHours
                                    "
                                />
                            </TabsContent>

                            <TabsContent value="overtime" class="mt-0">
                                <WorkScheduleOvertimeRulesPanel
                                    v-model="overtimeRulesDraft"
                                    id-prefix="edit-ot"
                                    :clock-pattern="activeDraft.clock_pattern"
                                    :regular-schedule-overnight="
                                        regularScheduleOvernight
                                    "
                                    :scheduled-net-hours="
                                        draftScheduledNetHours
                                    "
                                />
                            </TabsContent>

                            <p
                                v-if="formError"
                                class="mt-4 text-sm text-destructive"
                            >
                                {{ formError }}
                            </p>
                        </div>
                    </ScrollArea>
                </Tabs>
            </div>

            <DialogFooter class="shrink-0 gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="editDialogOpen = false"
                    >Cancel</Button
                >
                <Button type="button" @click="submitEdit">Save changes</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Work Schedule?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="deleteTarget">
                        This permanently deletes the template
                        <span class="font-medium text-foreground">{{
                            deleteTarget.name
                        }}</span>
                        from your organization.
                    </template>
                    <template v-else>
                        This removes the selected work schedule from the
                        organization catalog.
                    </template>
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
