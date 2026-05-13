<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import type { DateValue } from '@internationalized/date';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarIcon, Minus, Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import syncEmployeeEmploymentPositionsAffiliations from '@/actions/App/Http/Controllers/SyncEmployeeEmploymentPositionsAffiliationsController';
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
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { appToast } from '@/lib/app-toast-client';
import { calendarDateValueToIsoYmd } from '@/lib/calendarDateValueToIsoYmd';
import { formatCalendarTriggerFromDate } from '@/lib/formatCalendarTriggerDate';
import { cn } from '@/lib/utils';
import type {
    AboutMeWorkAffiliationRootOption,
    AboutMeWorkPayload,
} from '@/pages/Employees/aboutMeWorkTypes';

const ORG_WIDE_VALUE = '__org_wide__';

const dialogScrollAreaClass =
    'max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

type AffiliationDraftRow = {
    key: string;
    dbId: number | null;
    rootUnitId: string;
    startDate: string;
    endDate: string;
    isPrimary: boolean;
};

type PageErrorsBag = Record<string, string>;

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

const affiliationDrafts = ref<AffiliationDraftRow[]>([]);
const currentPassword = ref('');
const currentPasswordConfirmation = ref('');
const fieldErrors = ref<PageErrorsBag>({});
const processing = ref(false);

const affiliationRootsByGroup = computed(() => {
    const roots = props.work?.affiliation_roots ?? [];
    const buckets = new Map<string, AboutMeWorkAffiliationRootOption[]>();

    for (const r of roots) {
        const prev = buckets.get(r.group_label) ?? [];
        prev.push(r);
        buckets.set(r.group_label, prev);
    }

    return [...buckets.entries()].map(([group_label, items]) => ({
        group_label,
        items,
    }));
});

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

type PositionPersistedRowPayload = {
    id: number;
    position_id: number;
    start_date: string;
    end_date: string | null;
    is_primary: boolean;
};

type AffiliationRowPayload = {
    id?: number;
    root_unit_id: number | null;
    start_date: string;
    end_date: string | null;
    is_primary: boolean;
};

function positionsPayloadFromWork(
    w: AboutMeWorkPayload,
): PositionPersistedRowPayload[] {
    return w.positions.map((row) => ({
        id: row.id,
        position_id: row.position_id,
        start_date: row.start_date,
        end_date: row.end_date,
        is_primary: row.is_primary,
    }));
}

function resetDraftsFromWork(): void {
    if (!props.work) {
        affiliationDrafts.value = [];

        return;
    }

    affiliationDrafts.value = props.work.affiliations.map((row) => ({
        key: newRowKey('aff'),
        dbId: row.id,
        rootUnitId: row.root_unit_id !== null ? String(row.root_unit_id) : '',
        startDate: row.start_date,
        endDate: row.end_date ?? '',
        isPrimary: row.is_primary,
    }));
}

function resetSecrets(): void {
    currentPassword.value = '';
    currentPasswordConfirmation.value = '';
    fieldErrors.value = {};
}

watch(
    () => [props.open, props.work?.employment_id] as const,
    ([isOpen]) => {
        if (isOpen && props.work) {
            resetDraftsFromWork();
            resetSecrets();
        }
    },
);

function closeDialog(): void {
    emit('update:open', false);
}

function addAffiliationRow(): void {
    affiliationDrafts.value.push({
        key: newRowKey('aff'),
        dbId: null,
        rootUnitId: '',
        startDate: '',
        endDate: '',
        isPrimary: false,
    });
}

function removeAffiliationRow(index: number): void {
    if (affiliationDrafts.value.length <= 1) {
        return;
    }
    affiliationDrafts.value.splice(index, 1);
}

function setPrimaryAffiliation(index: number, checked: unknown): void {
    const flag = Boolean(checked);
    affiliationDrafts.value.forEach((row, idx) => {
        row.isPrimary = flag && idx === index;
    });
}

function onAffiliationStart(
    index: number,
    value: unknown,
    close: () => void,
): void {
    const row = affiliationDrafts.value[index];
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

function onAffiliationEnd(
    index: number,
    value: unknown,
    close: () => void,
): void {
    const row = affiliationDrafts.value[index];
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

function affiliationEndCalendarMin(index: number): DateValue | undefined {
    const row = affiliationDrafts.value[index];
    if (!row?.startDate) {
        return hireCalendarMin.value;
    }
    return isoToCalendarValue(row.startDate) ?? hireCalendarMin.value;
}

function spanStartCalendarMaxAt(index: number): DateValue | undefined {
    const row = affiliationDrafts.value[index];
    if (row === undefined) {
        return undefined;
    }
    if (row.endDate.trim() !== '') {
        return isoToCalendarValue(row.endDate) ?? undefined;
    }

    return todayLocal.value;
}

function affiliationSelectModel(row: AffiliationDraftRow): string {
    if (
        row.rootUnitId === '' &&
        (props.work?.allow_org_wide_affiliation ?? false)
    ) {
        return ORG_WIDE_VALUE;
    }

    return row.rootUnitId;
}

function affiliationSelectCommit(row: AffiliationDraftRow, v: unknown): void {
    row.rootUnitId =
        String(v) === ORG_WIDE_VALUE
            ? ''
            : v === undefined || v === null
              ? ''
              : String(v);
}

function buildPayload(): RequestPayload {
    const w = props.work;
    if (!w) {
        return {};
    }

    const affiliations: AffiliationRowPayload[] = affiliationDrafts.value.map(
        (row) => {
            const o: AffiliationRowPayload = {
                root_unit_id:
                    row.rootUnitId === '' ? null : Number(row.rootUnitId),
                start_date: row.startDate,
                end_date: row.endDate === '' ? null : row.endDate,
                is_primary: row.isPrimary,
            };
            if (row.dbId !== null) {
                o.id = row.dbId;
            }

            return o;
        },
    );

    return {
        positions: positionsPayloadFromWork(w),
        affiliations,
        current_password: currentPassword.value,
        current_password_confirmation: currentPasswordConfirmation.value,
    };
}

function clientValidate(): PageErrorsBag {
    const err: PageErrorsBag = {};

    const hireIso = props.work?.hire_date.trim().slice(0, 10) ?? '';

    affiliationDrafts.value.forEach((row, index) => {
        const allowOw = props.work?.allow_org_wide_affiliation ?? false;
        if (!allowOw && row.rootUnitId === '') {
            err[`affiliations.${index}.root_unit_id`] = 'Pick a branch.';
        }
        if (!row.startDate.trim()) {
            err[`affiliations.${index}.start_date`] = 'Start date is required.';

            return;
        }

        const startIso = row.startDate.trim().slice(0, 10);
        if (hireIso.length === 10 && startIso < hireIso) {
            err[`affiliations.${index}.start_date`] =
                'Start date must be on or after hire date.';
        }

        if (row.endDate.trim() === '' && startIso > todayIsoDate.value) {
            err[`affiliations.${index}.start_date`] =
                'Open-ended rows cannot start in the future.';
        }

        const endIso = row.endDate.trim().slice(0, 10);
        if (row.endDate.trim() !== '' && endIso.length === 10) {
            if (hireIso.length === 10 && endIso < hireIso) {
                err[`affiliations.${index}.end_date`] =
                    'End date must be on or after hire date.';
            } else if (startIso > endIso) {
                err[`affiliations.${index}.end_date`] =
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

    const client = clientValidate();
    if (Object.keys(client).length > 0) {
        fieldErrors.value = client;

        return;
    }

    fieldErrors.value = {};
    processing.value = true;

    router.patch(
        syncEmployeeEmploymentPositionsAffiliations.url({
            employment: w.employment_id,
        }),
        buildPayload(),
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                appToast.success('Affiliations updated.');
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
                fieldErrors.value = pageErrors ?? {};
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
                <DialogTitle>Edit affiliations</DialogTitle>
                <DialogDescription>
                    Branch or org-wide affiliations for
                    {{
                        work?.affiliation_organization?.name ??
                        'your organization'
                    }}
                    on your current employment. Positions stay as-is unless you
                    edit them in the positions dialog. Unit placements on the
                    org chart are managed separately.
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
                                    Branch or org-wide roots for this employment.
                                    Exactly one row must be primary; end dates are
                                    optional and must be on or after each row’s
                                    start (and on or after hire).
                                </p>
                                <p
                                    v-if="
                                        typeof fieldErrors.affiliations ===
                                        'string'
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{ fieldErrors.affiliations }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-1 rounded-md"
                                @click="addAffiliationRow"
                            >
                                <Plus class="size-3.5" aria-hidden="true" />
                                Add row
                            </Button>
                        </div>

                        <div
                            v-for="(row, index) in affiliationDrafts"
                            :key="row.key"
                            class="rounded-lg border border-border/70 bg-muted/10 p-4"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3 pb-4"
                            >
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        :id="`amaw_aff_primary_${row.key}`"
                                        :model-value="row.isPrimary"
                                        @update:model-value="
                                            setPrimaryAffiliation(index, $event)
                                        "
                                    />
                                    <Label
                                        :for="`amaw_aff_primary_${row.key}`"
                                        class="inline-flex cursor-pointer flex-wrap items-center gap-2"
                                    >
                                        <Badge>Primary</Badge>
                                    </Label>
                                </div>
                                <Button
                                    v-if="affiliationDrafts.length > 1"
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground hover:text-destructive"
                                    aria-label="Remove affiliation row"
                                    @click="removeAffiliationRow(index)"
                                >
                                    <Minus class="size-4" />
                                </Button>
                            </div>
                            <div
                                class="grid gap-4 md:grid-cols-12 md:items-end"
                            >
                                <div class="grid gap-2 md:col-span-5">
                                    <Label>Assigned branch</Label>
                                    <Select
                                        :model-value="
                                            affiliationSelectModel(row)
                                        "
                                        @update:model-value="
                                            affiliationSelectCommit(row, $event)
                                        "
                                    >
                                        <SelectTrigger
                                            class="w-full min-w-0"
                                            :aria-invalid="
                                                Boolean(
                                                    fieldErrors[
                                                        `affiliations.${index}.root_unit_id`
                                                    ],
                                                )
                                            "
                                        >
                                            <SelectValue
                                                placeholder="Select branch"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-if="
                                                    work.allow_org_wide_affiliation
                                                "
                                                :value="ORG_WIDE_VALUE"
                                            >
                                                Org-wide (no branch)
                                            </SelectItem>
                                            <SelectGroup
                                                v-for="group in affiliationRootsByGroup"
                                                :key="group.group_label"
                                            >
                                                <SelectLabel>{{
                                                    group.group_label
                                                }}</SelectLabel>
                                                <SelectItem
                                                    v-for="u in group.items"
                                                    :key="u.id"
                                                    :value="String(u.id)"
                                                >
                                                    {{
                                                        u.area_name
                                                            ? `${u.name} — ${u.area_name}`
                                                            : u.name
                                                    }}
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        v-if="
                                            fieldErrors[
                                                `affiliations.${index}.root_unit_id`
                                            ]
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            fieldErrors[
                                                `affiliations.${index}.root_unit_id`
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
                                                            `affiliations.${index}.start_date`
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
                                                    onAffiliationStart(
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
                                                `affiliations.${index}.start_date`
                                            ]
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            fieldErrors[
                                                `affiliations.${index}.start_date`
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
                                                    affiliationEndCalendarMin(
                                                        index,
                                                    )
                                                "
                                                @update:model-value="
                                                    onAffiliationEnd(
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
                                                `affiliations.${index}.end_date`
                                            ]
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            fieldErrors[
                                                `affiliations.${index}.end_date`
                                            ]
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4 border-t border-border/70 pt-4">
                        <p
                            class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Confirm identity
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="amaw_pw">Current password</Label>
                                <Input
                                    id="amaw_pw"
                                    v-model="currentPassword"
                                    type="password"
                                    autocomplete="current-password"
                                    :aria-invalid="
                                        Boolean(fieldErrors.current_password)
                                    "
                                />
                                <p
                                    v-if="fieldErrors.current_password"
                                    class="text-xs text-destructive"
                                >
                                    {{ fieldErrors.current_password }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="amaw_pw_c"
                                    >Confirm current password</Label
                                >
                                <Input
                                    id="amaw_pw_c"
                                    v-model="currentPasswordConfirmation"
                                    type="password"
                                    autocomplete="current-password"
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
                                    class="text-xs text-destructive"
                                >
                                    {{
                                        fieldErrors.current_password_confirmation
                                    }}
                                </p>
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
                    Save affiliations
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
