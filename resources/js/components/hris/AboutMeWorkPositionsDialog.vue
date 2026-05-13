<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import type { DateValue } from '@internationalized/date';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarIcon, Minus, Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import syncEmployeeEmploymentPositionsAffiliations from '@/actions/App/Http/Controllers/SyncEmployeeEmploymentPositionsAffiliationsController';
import type { EmployeePositionOption } from '@/components/employees/EmployeePositionPicker.vue';
import EmployeePositionPicker from '@/components/employees/EmployeePositionPicker.vue';
import CurrentPasswordConfirmDialog from '@/components/hris/CurrentPasswordConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { appToast } from '@/lib/app-toast-client';
import { calendarDateValueToIsoYmd } from '@/lib/calendarDateValueToIsoYmd';
import { formatCalendarTriggerFromDate } from '@/lib/formatCalendarTriggerDate';
import { cn } from '@/lib/utils';
import type { AboutMeWorkPayload } from '@/pages/Employees/aboutMeWorkTypes';

const dialogScrollAreaClass =
    'max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

type PositionDraftRow = {
    key: string;
    dbId: number | null;
    positionId: string;
    startDate: string;
    endDate: string;
    isPrimary: boolean;
};

type PageErrorsBag = Record<string, string>;

const PASSWORD_FIELD_KEYS = new Set([
    'current_password',
    'current_password_confirmation',
]);

function partitionPasswordFieldErrors(pageErrors: PageErrorsBag): {
    main: PageErrorsBag;
    password: PageErrorsBag;
} {
    const main: PageErrorsBag = {};
    const password: PageErrorsBag = {};
    for (const [key, message] of Object.entries(pageErrors)) {
        if (PASSWORD_FIELD_KEYS.has(key)) {
            password[key] = message;
        } else {
            main[key] = message;
        }
    }

    return { main, password };
}

const props = defineProps<{
    work: AboutMeWorkPayload | null;
    open: boolean;
    inertiaReloadOnly?: string[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

function newRowKey(prefix: string): string {
    return `${prefix}-${Math.random().toString(36).slice(2, 11)}`;
}

const positionDrafts = ref<PositionDraftRow[]>([]);
const fieldErrors = ref<PageErrorsBag>({});
const passwordConfirmOpen = ref(false);
const passwordConfirmErrors = ref<PageErrorsBag>({});
const processing = ref(false);

const pickerPositions = computed(
    (): EmployeePositionOption[] =>
        props.work?.positions_catalog.map((p) => ({
            id: p.id,
            code: p.code,
            title: p.title,
        })) ?? [],
);

const todayLocal = computed(() => today(getLocalTimeZone()));

const todayIsoDate = computed(() => calendarDateValueToIsoYmd(todayLocal.value));

function isoToCalendarValue(iso: string): DateValue | undefined {
    const t = iso.trim().slice(0, 10);
    if (t.length < 10) {
        return undefined;
    }
    try {
        return parseDate(t) as DateValue;
    } catch {
        return undefined;
    }
}

function isoTriggerLabel(iso: string): string {
    const v = isoToCalendarValue(iso);
    if (v === undefined || !('toDate' in v) || typeof v.toDate !== 'function') {
        return '';
    }

    return formatCalendarTriggerFromDate(v.toDate(getLocalTimeZone()));
}

const hireCalendarMin = computed(() =>
    isoToCalendarValue(props.work?.hire_date ?? ''),
);

type AffiliationRowPayload = {
    id: number;
    root_unit_id: number | null;
    start_date: string;
    end_date: string | null;
    is_primary: boolean;
};

type PositionRowPayload = {
    position_id: number;
    start_date: string;
    end_date: string | null;
    is_primary: boolean;
    id?: number;
};

function affiliationsPayloadFromWork(
    w: AboutMeWorkPayload,
): AffiliationRowPayload[] {
    return w.affiliations.map((row) => ({
        id: row.id,
        root_unit_id: row.root_unit_id,
        start_date: row.start_date,
        end_date: row.end_date,
        is_primary: row.is_primary,
    }));
}

function resetDraftsFromWork(): void {
    if (!props.work) {
        positionDrafts.value = [];

        return;
    }

    positionDrafts.value = props.work.positions.map((row) => ({
        key: newRowKey('pos'),
        dbId: row.id,
        positionId: String(row.position_id),
        startDate: row.start_date,
        endDate: row.end_date ?? '',
        isPrimary: row.is_primary,
    }));
}

function resetFormErrors(): void {
    fieldErrors.value = {};
    passwordConfirmErrors.value = {};
    passwordConfirmOpen.value = false;
}

watch(
    () => [props.open, props.work?.employment_id] as const,
    ([isOpen]) => {
        if (!isOpen) {
            passwordConfirmOpen.value = false;
            passwordConfirmErrors.value = {};
        }
        if (isOpen && props.work) {
            resetDraftsFromWork();
            resetFormErrors();
        }
    },
);

function closeDialog(): void {
    passwordConfirmOpen.value = false;
    emit('update:open', false);
}

function addPositionRow(): void {
    positionDrafts.value.push({
        key: newRowKey('pos'),
        dbId: null,
        positionId: '',
        startDate: '',
        endDate: '',
        isPrimary: false,
    });
}

function removePositionRow(index: number): void {
    if (positionDrafts.value.length <= 1) {
        return;
    }
    positionDrafts.value.splice(index, 1);
}

function setPrimaryPosition(index: number, checked: unknown): void {
    const flag = Boolean(checked);
    positionDrafts.value.forEach((row, idx) => {
        row.isPrimary = flag && idx === index;
    });
}

function onPositionStart(
    index: number,
    value: unknown,
    close: () => void,
): void {
    const row = positionDrafts.value[index];
    if (
        row === undefined ||
        !value ||
        Array.isArray(value) ||
        typeof value !== 'object' ||
        value === null ||
        !('toDate' in value)
    ) {
        if (row !== undefined) {
            row.startDate = '';
        }
        close();

        return;
    }
    row.startDate = calendarDateValueToIsoYmd(value as DateValue);
    if (row.endDate !== '' && row.endDate < row.startDate) {
        row.endDate = '';
    }
    close();
}

function onPositionEnd(index: number, value: unknown, close: () => void): void {
    const row = positionDrafts.value[index];
    if (
        row === undefined ||
        !value ||
        Array.isArray(value) ||
        typeof value !== 'object' ||
        value === null ||
        !('toDate' in value)
    ) {
        if (row !== undefined) {
            row.endDate = '';
        }
        close();

        return;
    }
    row.endDate = calendarDateValueToIsoYmd(value as DateValue);
    close();
}

function positionEndCalendarMin(index: number): DateValue | undefined {
    const row = positionDrafts.value[index];
    if (!row?.startDate) {
        return hireCalendarMin.value;
    }
    return isoToCalendarValue(row.startDate) ?? hireCalendarMin.value;
}

function spanStartCalendarMaxAt(index: number): DateValue | undefined {
    const row = positionDrafts.value[index];
    if (row === undefined) {
        return undefined;
    }
    if (row.endDate.trim() !== '') {
        return isoToCalendarValue(row.endDate) ?? undefined;
    }

    return todayLocal.value;
}

function buildPayload(auth: {
    current_password: string;
    current_password_confirmation: string;
}): RequestPayload {
    const w = props.work;
    if (!w) {
        return {};
    }

    const positions: PositionRowPayload[] = positionDrafts.value.map((row) => {
        const o: PositionRowPayload = {
            position_id: Number(row.positionId),
            start_date: row.startDate,
            end_date: row.endDate === '' ? null : row.endDate,
            is_primary: row.isPrimary,
        };
        if (row.dbId !== null) {
            o.id = row.dbId;
        }

        return o;
    });

    return {
        positions,
        affiliations: affiliationsPayloadFromWork(w),
        current_password: auth.current_password,
        current_password_confirmation: auth.current_password_confirmation,
    };
}

function clientValidate(): PageErrorsBag {
    const err: PageErrorsBag = {};

    const w = props.work;
    const hireIso = w?.hire_date.trim().slice(0, 10) ?? '';

    positionDrafts.value.forEach((row, index) => {
        if (!row.positionId) {
            err[`positions.${index}.position_id`] = 'Pick a position.';
        }
        if (!row.startDate.trim()) {
            err[`positions.${index}.start_date`] = 'Start date is required.';

            return;
        }

        const startIso = row.startDate.trim().slice(0, 10);
        if (hireIso.length === 10 && startIso < hireIso) {
            err[`positions.${index}.start_date`] =
                'Start date must be on or after hire date.';
        }

        if (row.endDate.trim() === '' && startIso > todayIsoDate.value) {
            err[`positions.${index}.start_date`] =
                'Open-ended rows cannot start in the future.';
        }

        const endIso = row.endDate.trim().slice(0, 10);
        if (row.endDate.trim() !== '' && endIso.length === 10) {
            if (hireIso.length === 10 && endIso < hireIso) {
                err[`positions.${index}.end_date`] =
                    'End date must be on or after hire date.';
            } else if (startIso > endIso) {
                err[`positions.${index}.end_date`] =
                    'End date must be on or after start date.';
            }
        }
    });

    return err;
}

function submit(): void {
    const w = props.work;
    if (!w) {
        return;
    }

    const client = clientValidate();
    if (Object.keys(client).length > 0) {
        fieldErrors.value = client;

        return;
    }

    fieldErrors.value = {};
    passwordConfirmErrors.value = {};
    passwordConfirmOpen.value = true;
}

function submitWithPassword(auth: {
    current_password: string;
    current_password_confirmation: string;
}): void {
    const w = props.work;
    if (!w) {
        return;
    }

    passwordConfirmErrors.value = {};
    processing.value = true;

    router.patch(
        syncEmployeeEmploymentPositionsAffiliations.url({
            employment: w.employment_id,
        }),
        buildPayload(auth),
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                appToast.success('Positions updated.');
                passwordConfirmOpen.value = false;
                closeDialog();
                if (
                    Array.isArray(props.inertiaReloadOnly) &&
                    props.inertiaReloadOnly.length > 0
                ) {
                    router.reload({
                        only: [...props.inertiaReloadOnly],
                    });
                }
            },
            onError: (pageErrors: PageErrorsBag) => {
                const bag = pageErrors ?? {};
                const { main, password } = partitionPasswordFieldErrors(bag);
                fieldErrors.value = main;
                if (Object.keys(main).length > 0) {
                    passwordConfirmOpen.value = false;
                    passwordConfirmErrors.value = {};
                } else {
                    passwordConfirmErrors.value = password;
                }
                if (
                    pageErrors !== null &&
                    typeof pageErrors === 'object' &&
                    Object.keys(pageErrors).length === 0
                ) {
                    appToast.error('Could not save changes. Please try again.');
                }
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="gap-4 sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Edit positions</DialogTitle>
                <DialogDescription>
                    Catalog-linked titles for your current employment at
                    {{
                        work?.affiliation_organization?.name ??
                        'your organization'
                    }}. Affiliations are unchanged unless you edit them in the
                    separate affiliation dialog.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea v-if="work" :class="dialogScrollAreaClass">
                <div class="grid gap-6 px-1 py-1">
                    <section class="space-y-4">
                        <div
                            class="flex flex-wrap items-end justify-between gap-2"
                        >
                            <div class="space-y-1">
                                <p class="text-xs text-muted-foreground">
                                    Catalog-linked titles for this employment.
                                    Exactly one row must be primary; end dates are
                                    optional and must be on or after each row’s
                                    start (and on or after hire).
                                </p>
                                <p
                                    v-if="
                                        typeof fieldErrors.positions ===
                                        'string'
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{ fieldErrors.positions }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-1 rounded-md"
                                @click="addPositionRow"
                            >
                                <Plus class="size-3.5" aria-hidden="true" />
                                Add row
                            </Button>
                        </div>

                        <div
                            v-for="(row, index) in positionDrafts"
                            :key="row.key"
                            class="rounded-lg border border-border/70 bg-muted/10 p-4"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3 pb-4"
                            >
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        :id="`ampw_pos_primary_${row.key}`"
                                        :model-value="row.isPrimary"
                                        @update:model-value="
                                            setPrimaryPosition(index, $event)
                                        "
                                    />
                                    <Label
                                        :for="`ampw_pos_primary_${row.key}`"
                                        class="inline-flex cursor-pointer flex-wrap items-center gap-2"
                                    >
                                        <Badge>Primary</Badge>
                                    </Label>
                                </div>
                                <Button
                                    v-if="positionDrafts.length > 1"
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground hover:text-destructive"
                                    aria-label="Remove position row"
                                    @click="removePositionRow(index)"
                                >
                                    <Minus class="size-4" />
                                </Button>
                            </div>
                            <div
                                class="grid gap-4 md:grid-cols-12 md:items-end"
                            >
                                <div class="grid gap-2 md:col-span-5">
                                    <Label>Position</Label>
                                    <EmployeePositionPicker
                                        v-model="row.positionId"
                                        :positions="pickerPositions"
                                        :trigger-id="`ampw_pos_pick_${row.key}`"
                                        :aria-invalid="
                                            Boolean(
                                                fieldErrors[
                                                    `positions.${index}.position_id`
                                                ],
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            fieldErrors[
                                                `positions.${index}.position_id`
                                            ]
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            fieldErrors[
                                                `positions.${index}.position_id`
                                            ]
                                        }}
                                    </p>
                                </div>
                                <div class="grid gap-2 md:col-span-3">
                                    <Label>Start date</Label>
                                    <Popover v-slot="{ close }">
                                        <PopoverTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                :class="
                                                    cn(
                                                        'w-full justify-between gap-2 font-normal',
                                                        fieldErrors[
                                                            `positions.${index}.start_date`
                                                        ] &&
                                                            'border-destructive',
                                                    )
                                                "
                                            >
                                                <span
                                                    v-if="
                                                        isoTriggerLabel(
                                                            row.startDate,
                                                        ) !== ''
                                                    "
                                                >
                                                    {{
                                                        isoTriggerLabel(
                                                            row.startDate,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                >
                                                    Pick date</span
                                                >
                                                <CalendarIcon
                                                    class="size-4 shrink-0 opacity-50"
                                                />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            class="w-auto overflow-hidden p-0"
                                            align="start"
                                        >
                                            <Calendar
                                                layout="month-and-year"
                                                :model-value="
                                                    isoToCalendarValue(
                                                        row.startDate,
                                                    )
                                                "
                                                :min-value="hireCalendarMin"
                                                :max-value="
                                                    spanStartCalendarMaxAt(
                                                        index,
                                                    )
                                                "
                                                @update:model-value="
                                                    onPositionStart(
                                                        index,
                                                        $event,
                                                        close,
                                                    )
                                                "
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    <p
                                        v-if="
                                            fieldErrors[
                                                `positions.${index}.start_date`
                                            ]
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            fieldErrors[
                                                `positions.${index}.start_date`
                                            ]
                                        }}
                                    </p>
                                </div>
                                <div class="grid gap-2 md:col-span-4">
                                    <Label
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        End date
                                        <Badge variant="outline"
                                            >Optional</Badge
                                        >
                                        <Button
                                            v-if="row.endDate !== ''"
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="ml-auto size-6 shrink-0 rounded-md"
                                            aria-label="Clear end date"
                                            @click="row.endDate = ''"
                                        >
                                            <X class="size-3.5" />
                                        </Button>
                                    </Label>
                                    <Popover v-slot="{ close }">
                                        <PopoverTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="w-full justify-between gap-2 font-normal"
                                            >
                                                <span
                                                    v-if="
                                                        isoTriggerLabel(
                                                            row.endDate,
                                                        ) !== ''
                                                    "
                                                >
                                                    {{
                                                        isoTriggerLabel(
                                                            row.endDate,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                >
                                                    Present / open-ended</span
                                                >
                                                <CalendarIcon
                                                    class="size-4 shrink-0 opacity-50"
                                                />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            class="w-auto overflow-hidden p-0"
                                            align="start"
                                        >
                                            <Calendar
                                                layout="month-and-year"
                                                :model-value="
                                                    isoToCalendarValue(
                                                        row.endDate,
                                                    )
                                                "
                                                :min-value="
                                                    positionEndCalendarMin(
                                                        index,
                                                    )
                                                "
                                                @update:model-value="
                                                    onPositionEnd(
                                                        index,
                                                        $event,
                                                        close,
                                                    )
                                                "
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    <p
                                        v-if="
                                            fieldErrors[
                                                `positions.${index}.end_date`
                                            ]
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            fieldErrors[
                                                `positions.${index}.end_date`
                                            ]
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </ScrollArea>

            <DialogFooter class="gap-2">
                <Button type="button" variant="outline" @click="closeDialog">
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="work === null || processing"
                    @click="submit"
                >
                    Save positions
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <CurrentPasswordConfirmDialog
        v-model:open="passwordConfirmOpen"
        :submitting="processing"
        :errors="passwordConfirmErrors"
        title="Confirm your password"
        description="Enter your current account password to save these position assignments."
        confirm-label="Save positions"
        @confirm="submitWithPassword"
    />
</template>
