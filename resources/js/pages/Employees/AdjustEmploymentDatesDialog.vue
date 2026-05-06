<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { DateValue } from '@internationalized/date';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarIcon, ChevronDownIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import updateEmployeeEmploymentDates from '@/actions/App/Http/Controllers/UpdateEmployeeEmploymentDatesController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
import { Textarea } from '@/components/ui/textarea';
import { appToast } from '@/lib/app-toast-client';
import { formatCalendarTriggerFromDate } from '@/lib/formatCalendarTriggerDate';
import { cn } from '@/lib/utils';
import type {
    EmploymentHistoryDialogMode,
    EmploymentHistoryRow,
} from '@/pages/Employees/employmentHistoryTypes';
import type {
    EmploymentSeparationStatusApi,
    EmploymentStatusApi,
} from '@/pages/Employees/employmentStatusConstants';
import {
    EMPLOYMENT_SEPARATION_STATUS_VALUES,
    EMPLOYMENT_STATUS_LABEL,
} from '@/pages/Employees/employmentStatusConstants';

/** Matches Leave Team “Add Leave Record” dialog scroll sizing. */
const dialogScrollAreaClass =
    'max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

type PageErrorsBag = Record<string, string>;

const props = defineProps<{
    row: EmploymentHistoryRow | null;
    open: boolean;
    /** `record_separation` only for Active rows in the employment history UI. */
    mode: EmploymentHistoryDialogMode;
    /** Partial Inertia prop keys to reload after a successful PATCH (e.g. Employees index rows). */
    inertiaReloadOnly?: string[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const hireDateIso = ref('');
const separationDateIso = ref('');
const employmentStatus = ref<EmploymentSeparationStatusApi | ''>('');
const separationReason = ref('');
const employmentNotes = ref('');
const currentPassword = ref('');
const currentPasswordConfirmation = ref('');
const fieldErrors = ref<PageErrorsBag>({});
const processing = ref(false);

function dateValueToIsoDate(value: DateValue): string {
    return value.toDate(getLocalTimeZone()).toISOString().slice(0, 10);
}

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
    if (v === undefined) {
        return '';
    }
    if (!('toDate' in v) || typeof v.toDate !== 'function') {
        return '';
    }

    return formatCalendarTriggerFromDate(v.toDate(getLocalTimeZone()));
}

const isRecordSeparation = computed(() => props.mode === 'record_separation');

const todayLocal = computed(() => today(getLocalTimeZone()));

/** Inclusive ceiling for hire when related spans exist (YYYY-MM-DD from server). */
const hireAdjustmentCalendarMax = computed((): DateValue | undefined => {
    const raw = props.row?.hire_adjustment_max_date?.trim().slice(0, 10) ?? '';
    if (raw === '' || raw.length !== 10) {
        return undefined;
    }

    return isoToCalendarValue(raw);
});

const showSeparationDates = computed((): boolean => isRecordSeparation.value);

/** Separation cannot be in the future. */
const separationCalendarMaxValue = computed((): DateValue | undefined => {
    if (!showSeparationDates.value) {
        return undefined;
    }

    return todayLocal.value;
});

const dialogTitle = computed((): string => {
    return isRecordSeparation.value
        ? 'Record separation'
        : 'Adjust employment dates';
});

const dialogDescription = computed((): string => {
    if (isRecordSeparation.value) {
        return 'Capture separation date, employment status (not Active), and optional reason or notes. All position and affiliation spans for this employment must already be ended — otherwise saving will surface validation errors.';
    }

    return 'Update the hire date for this active employment. Separated records cannot be edited here.';
});

const resolvedStatusDisplay = computed((): string => {
    const s = props.row?.employment_status;
    if (s === undefined) {
        return '';
    }

    return EMPLOYMENT_STATUS_LABEL[s as EmploymentStatusApi];
});

function resetFromRow(): void {
    fieldErrors.value = {};
    currentPassword.value = '';
    currentPasswordConfirmation.value = '';
    if (!props.row) {
        hireDateIso.value = '';
        separationDateIso.value = '';
        employmentStatus.value = '';
        separationReason.value = '';
        employmentNotes.value = '';

        return;
    }

    hireDateIso.value = props.row.hire_date.slice(0, 10);
    separationDateIso.value = props.row.separation_date?.slice(0, 10) ?? '';
    separationReason.value = props.row.separation_reason ?? '';
    employmentNotes.value = props.row.notes ?? '';

    if (props.mode === 'record_separation') {
        employmentStatus.value = '';
        separationDateIso.value = '';
        separationReason.value = '';
        employmentNotes.value = '';
    }
}

watch(
    () => [props.open, props.row?.id, props.mode] as const,
    ([isOpen]) => {
        if (isOpen) {
            resetFromRow();
        }
    },
);

function onHireSelect(value: unknown, close: () => void): void {
    if (
        !value ||
        Array.isArray(value) ||
        typeof value !== 'object' ||
        value === null ||
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        hireDateIso.value = '';
        close();

        return;
    }

    hireDateIso.value = dateValueToIsoDate(value as DateValue);
    close();
}

function onEmploymentStatusSelect(value: unknown): void {
    if (value === null || value === undefined) {
        employmentStatus.value = '';

        return;
    }

    employmentStatus.value = String(value) as EmploymentSeparationStatusApi;
}

function onSeparationSelect(value: unknown, close: () => void): void {
    if (
        !value ||
        Array.isArray(value) ||
        typeof value !== 'object' ||
        value === null ||
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        separationDateIso.value = '';
        close();

        return;
    }

    separationDateIso.value = dateValueToIsoDate(value as DateValue);
    close();
}

function closeDialog(): void {
    emit('update:open', false);
}

function buildPayload(): Record<string, string> {
    if (!props.row) {
        return {};
    }

    const authFields: Record<string, string> = {
        current_password: currentPassword.value,
        current_password_confirmation: currentPasswordConfirmation.value,
    };

    if (props.mode === 'record_separation') {
        return {
            hire_date: props.row.hire_date.slice(0, 10),
            separation_date: separationDateIso.value,
            employment_status:
                employmentStatus.value === ''
                    ? ''
                    : String(employmentStatus.value),
            separation_reason: separationReason.value.trim(),
            notes: employmentNotes.value.trim(),
            ...authFields,
        };
    }

    return {
        hire_date: hireDateIso.value,
        ...authFields,
    };
}

function submit(): void {
    if (!props.row) {
        return;
    }

    if (currentPassword.value.trim() === '') {
        fieldErrors.value = {
            current_password: 'Enter your current password.',
        };

        return;
    }

    if (currentPasswordConfirmation.value.trim() === '') {
        fieldErrors.value = {
            current_password_confirmation: 'Confirm your current password.',
        };

        return;
    }

    if (props.mode === 'record_separation') {
        fieldErrors.value = {};
        if (employmentStatus.value === '') {
            fieldErrors.value = {
                employment_status: 'Select an employment status.',
            };

            return;
        }

        if (separationDateIso.value.trim() === '') {
            fieldErrors.value = {
                separation_date: 'Select a separation date.',
            };

            return;
        }
    }

    const employmentId = props.row.id;
    fieldErrors.value = {};

    if (!isRecordSeparation.value && hireDateIso.value.trim() !== '') {
        const hireIso = hireDateIso.value.trim().slice(0, 10);
        const maxRaw =
            props.row.hire_adjustment_max_date?.trim().slice(0, 10) ?? '';
        if (maxRaw.length === 10 && hireIso > maxRaw) {
            fieldErrors.value = {
                hire_date:
                    'Hire date cannot be later than related position, affiliation, or assignment start dates.',
            };

            return;
        }
    }

    const payload = buildPayload();

    processing.value = true;
    router.patch(
        updateEmployeeEmploymentDates.url({ employment: employmentId }),
        payload,
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                appToast.success(
                    isRecordSeparation.value
                        ? 'Separation recorded.'
                        : 'Employment updated.',
                );
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
                fieldErrors.value = pageErrors;
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
        <DialogContent class="gap-4 sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ dialogDescription }}
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="fieldErrors.employment"
                class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                role="alert"
            >
                {{ fieldErrors.employment }}
            </p>

            <ScrollArea v-if="row" :class="dialogScrollAreaClass">
                <div class="grid gap-4 px-1 py-1">
                    <div
                        class="rounded-lg border border-border/80 bg-muted/20 px-3 py-2 text-sm"
                    >
                        <p class="font-medium text-foreground">
                            {{ row.employee.display_name }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ row.employee.id_number }}
                            · Current status:
                            <span class="text-foreground">{{
                                resolvedStatusDisplay
                            }}</span>
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label>Hire date</Label>
                        <template v-if="isRecordSeparation">
                            <div
                                class="rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                            >
                                {{ isoTriggerLabel(hireDateIso) }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Hire date is unchanged when recording
                                separation.
                            </p>
                        </template>
                        <template v-else>
                            <Popover v-slot="{ close }">
                                <PopoverTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        :disabled="row === null"
                                        :class="
                                            cn(
                                                'w-full justify-between gap-2 font-normal',
                                                fieldErrors.hire_date &&
                                                    'border-destructive',
                                            )
                                        "
                                    >
                                        <span
                                            v-if="
                                                isoTriggerLabel(hireDateIso) !==
                                                ''
                                            "
                                            >{{
                                                isoTriggerLabel(hireDateIso)
                                            }}</span
                                        >
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >Select date</span
                                        >
                                        <CalendarIcon
                                            class="size-4 shrink-0 opacity-50"
                                            aria-hidden="true"
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
                                            isoToCalendarValue(hireDateIso)
                                        "
                                        :max-value="hireAdjustmentCalendarMax"
                                        @update:model-value="
                                            onHireSelect($event, close)
                                        "
                                    />
                                </PopoverContent>
                            </Popover>
                        </template>
                        <p
                            v-if="fieldErrors.hire_date"
                            class="text-sm text-destructive"
                        >
                            {{ fieldErrors.hire_date }}
                        </p>
                    </div>

                    <template v-if="showSeparationDates">
                        <div class="border-t border-border/70 pt-2">
                            <p
                                class="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Separation
                            </p>
                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="adj_emp_sep"
                                        >Separation date</Label
                                    >
                                    <Popover v-slot="{ close }">
                                        <PopoverTrigger as-child>
                                            <Button
                                                id="adj_emp_sep"
                                                type="button"
                                                variant="outline"
                                                :disabled="row === null"
                                                :class="
                                                    cn(
                                                        'w-full justify-between gap-2 font-normal',
                                                        fieldErrors.separation_date &&
                                                            'border-destructive',
                                                    )
                                                "
                                            >
                                                <span
                                                    v-if="
                                                        isoTriggerLabel(
                                                            separationDateIso,
                                                        ) !== ''
                                                    "
                                                    >{{
                                                        isoTriggerLabel(
                                                            separationDateIso,
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >Select date</span
                                                >
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
                                            <Calendar
                                                layout="month-and-year"
                                                :model-value="
                                                    isoToCalendarValue(
                                                        separationDateIso,
                                                    )
                                                "
                                                :min-value="
                                                    isoToCalendarValue(
                                                        hireDateIso,
                                                    )
                                                "
                                                :max-value="
                                                    separationCalendarMaxValue
                                                "
                                                @update:model-value="
                                                    onSeparationSelect(
                                                        $event,
                                                        close,
                                                    )
                                                "
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    <p
                                        v-if="fieldErrors.separation_date"
                                        class="text-sm text-destructive"
                                    >
                                        {{ fieldErrors.separation_date }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="adj_emp_status">
                                        Employment status
                                    </Label>
                                    <Select
                                        :model-value="
                                            employmentStatus === ''
                                                ? undefined
                                                : employmentStatus
                                        "
                                        @update:model-value="
                                            onEmploymentStatusSelect
                                        "
                                    >
                                        <SelectTrigger
                                            id="adj_emp_status"
                                            class="h-9 w-full"
                                            :aria-invalid="
                                                Boolean(
                                                    fieldErrors.employment_status,
                                                )
                                            "
                                        >
                                            <SelectValue
                                                placeholder="Select status"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="s in EMPLOYMENT_SEPARATION_STATUS_VALUES"
                                                :key="s"
                                                :value="s"
                                            >
                                                {{
                                                    EMPLOYMENT_STATUS_LABEL[
                                                        s as EmploymentSeparationStatusApi
                                                    ]
                                                }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        v-if="fieldErrors.employment_status"
                                        class="text-sm text-destructive"
                                    >
                                        {{ fieldErrors.employment_status }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        for="adj_emp_sep_reason"
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        Separation reason
                                        <Badge variant="outline"
                                            >Optional</Badge
                                        >
                                    </Label>
                                    <Input
                                        id="adj_emp_sep_reason"
                                        v-model="separationReason"
                                        maxlength="100"
                                        class="font-normal"
                                        placeholder="Brief reason shown on HR records"
                                        :aria-invalid="
                                            Boolean(
                                                fieldErrors.separation_reason,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="fieldErrors.separation_reason"
                                        class="text-sm text-destructive"
                                    >
                                        {{ fieldErrors.separation_reason }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        for="adj_emp_notes"
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        Notes
                                        <Badge variant="outline"
                                            >Optional</Badge
                                        >
                                    </Label>
                                    <Textarea
                                        id="adj_emp_notes"
                                        v-model="employmentNotes"
                                        rows="4"
                                        class="resize-y font-normal"
                                        placeholder="Internal notes about this separation or timeline…"
                                        :aria-invalid="
                                            Boolean(fieldErrors.notes)
                                        "
                                    />
                                    <p
                                        v-if="fieldErrors.notes"
                                        class="text-sm text-destructive"
                                    >
                                        {{ fieldErrors.notes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <p
                        v-else
                        class="rounded-md bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                    >
                        For Active employment, separation is recorded using
                        <span class="font-medium text-foreground"
                            >Record separation</span
                        >
                        once assignments are fully ended under this employment.
                    </p>

                    <div class="border-t border-border/70 pt-4">
                        <p
                            class="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Confirm identity
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2 sm:col-span-1">
                                <Label for="adj_emp_current_pw"
                                    >Current password</Label
                                >
                                <Input
                                    id="adj_emp_current_pw"
                                    v-model="currentPassword"
                                    type="password"
                                    autocomplete="current-password"
                                    class="font-normal"
                                    :aria-invalid="
                                        Boolean(fieldErrors.current_password)
                                    "
                                />
                                <p
                                    v-if="fieldErrors.current_password"
                                    class="text-sm text-destructive"
                                >
                                    {{ fieldErrors.current_password }}
                                </p>
                            </div>
                            <div class="grid gap-2 sm:col-span-1">
                                <Label for="adj_emp_current_pw_conf"
                                    >Confirm current password</Label
                                >
                                <Input
                                    id="adj_emp_current_pw_conf"
                                    v-model="currentPasswordConfirmation"
                                    type="password"
                                    autocomplete="current-password"
                                    class="font-normal"
                                    :aria-invalid="
                                        Boolean(
                                            fieldErrors.current_password_confirmation,
                                        )
                                    "
                                />
                                <p
                                    v-if="
                                        fieldErrors.current_password_confirmation
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{
                                        fieldErrors.current_password_confirmation
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter class="gap-2">
                <Button type="button" variant="outline" @click="closeDialog">
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="row === null || processing"
                    :class="
                        cn(
                            isRecordSeparation &&
                                'bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-500',
                        )
                    "
                    @click="submit"
                >
                    {{
                        isRecordSeparation
                            ? 'Record separation'
                            : 'Save changes'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
