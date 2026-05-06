<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import { ChevronDown, X } from 'lucide-vue-next';
import type { DateValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import updateEmployeeAboutMeDemographics from '@/actions/App/Http/Controllers/UpdateEmployeeAboutMeDemographicsController';
import {
    aboutMeClearFieldButtonClass,
    aboutMeDialogScrollAreaClass,
    aboutMeLabelRowClass,
} from '@/components/hris/aboutMeDialogUi';
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
import { appToast } from '@/lib/app-toast-client';
import { formatCalendarTriggerFromDate } from '@/lib/formatCalendarTriggerDate';
import type { EmployeeProfileDisplay } from '@/pages/Employees/employeeProfileDisplay';
import {
    employeeStepOneCivilStatusOptions,
    employeeStepOneNationalityOptions,
    employeeStepOneReligionOptions,
    employeeStepOneSexOptions,
} from '@/pages/Employees/employeeStepOneOptions';

type PageErrorsBag = Record<string, string>;

const props = defineProps<{
    profile: EmployeeProfileDisplay;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const birthdate = ref<DateValue | undefined>(undefined);

/** Bridge Reka `Calendar` model typing and `@internationalized/date` calendar values. */
const birthdateCalendarModel = computed<DateValue | undefined>({
    get: () => birthdate.value as DateValue | undefined,
    set: (v) => {
        birthdate.value = v;
    },
});

const sex = ref('');
const civilStatus = ref('');
const nationality = ref('');
const religion = ref('');
const religionOther = ref('');

const attemptedSave = ref(false);
const processing = ref(false);
const fieldErrors = ref<PageErrorsBag>({});

const birthdateTriggerLabel = computed((): string => {
    const selected = birthdate.value;
    if (!selected) {
        return '';
    }
    if (
        !('toDate' in selected) ||
        typeof selected.toDate !== 'function'
    ) {
        return '';
    }

    return formatCalendarTriggerFromDate(
        selected.toDate(getLocalTimeZone()),
    );
});

const birthInvalid = computed(
    () => attemptedSave.value && birthdate.value === undefined,
);
const sexInvalid = computed(() => attemptedSave.value && sex.value.trim() === '');
const religionOtherInvalid = computed(
    () =>
        attemptedSave.value &&
        religion.value === 'Other' &&
        religionOther.value.trim() === '',
);

watch(
    () => religion.value,
    (value) => {
        if (value !== 'Other') {
            religionOther.value = '';
        }
    },
);

function isoFromBirthdate(value: { toString(): string }): string | null {
    if (typeof value.toString !== 'function') {
        return null;
    }

    const raw = value.toString();
    const ymd = raw.slice(0, 10);

    return /^\d{4}-\d{2}-\d{2}$/.test(ymd) ? ymd : null;
}

function religionDisplayStored(): string {
    const r = props.profile.demographics.religion ?? '';
    if (r !== 'Other') {
        return r;
    }

    return 'Other';
}

function resetFromProfile(): void {
    attemptedSave.value = false;
    processing.value = false;
    fieldErrors.value = {};
    const d = props.profile.demographics;
    birthdate.value =
        d.birthdate_iso !== null &&
        /^\d{4}-\d{2}-\d{2}$/.test(d.birthdate_iso)
            ? (parseDate(d.birthdate_iso) as unknown as DateValue)
            : undefined;
    sex.value = d.sex;
    civilStatus.value = d.civil_status;
    nationality.value = d.nationality;
    religion.value = religionDisplayStored();
    religionOther.value = d.religion_other ?? '';
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetFromProfile();
        }
    },
);

function onBirthdateSelect(value: unknown, close: () => void): void {
    if (!value || Array.isArray(value)) {
        birthdate.value = undefined;

        return;
    }
    if (typeof value !== 'object' || value === null) {
        birthdate.value = undefined;

        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        birthdate.value = undefined;

        return;
    }

    birthdate.value = value as unknown as DateValue;
    close();
}

function clearBirthdate(): void {
    birthdate.value = undefined;
}

function resolveReligionForSave(): {
    religion: string | null;
    religion_other: string | null;
} {
    const rel = religion.value.trim();
    if (rel === '') {
        return { religion: null, religion_other: null };
    }
    if (rel === 'Other') {
        const other = religionOther.value.trim();

        return {
            religion: 'Other',
            religion_other: other === '' ? null : other,
        };
    }

    return { religion: rel, religion_other: null };
}

function onSave(): void {
    attemptedSave.value = true;
    if (
        birthInvalid.value ||
        sexInvalid.value ||
        religionOtherInvalid.value
    ) {
        return;
    }

    const selected = birthdate.value;
    if (!selected || isoFromBirthdate(selected) === null) {
        return;
    }

    const iso = isoFromBirthdate(selected)!;

    const { religion: religionOut, religion_other: religionOtherOut } =
        resolveReligionForSave();

    fieldErrors.value = {};

    processing.value = true;

    router.patch(
        updateEmployeeAboutMeDemographics.url(props.profile.employee_id),
        {
            birthdate: iso,
            sex: sex.value,
            civil_status:
                civilStatus.value.trim() === ''
                    ? null
                    : civilStatus.value.trim(),
            nationality:
                nationality.value.trim() === ''
                    ? null
                    : nationality.value.trim(),
            religion: religionOut,
            religion_other: religionOtherOut,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                appToast.success('Identity updated.');
                emit('update:open', false);
                router.reload({
                    only: ['profile'],
                });
            },
            onError: (pageErrors: PageErrorsBag) => {
                fieldErrors.value = pageErrors ?? {};
                if (
                    pageErrors !== null &&
                    typeof pageErrors === 'object' &&
                    Object.keys(pageErrors).length === 0
                ) {
                    appToast.error(
                        'Could not save identity. Please try again.',
                    );
                }
            },
        },
    );
}

function onOpenChange(value: boolean): void {
    emit('update:open', value);
}

const genericFormError = computed((): string | null => {
    const errs = fieldErrors.value;
    const generic = errs.error ?? errs.message;
    return typeof generic === 'string' && generic !== '' ? generic : null;
});
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Edit identity &amp; demographics</DialogTitle>
                <DialogDescription>
                    Same controls as Add Employee step 1. Changes save to HR
                    records immediately.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="aboutMeDialogScrollAreaClass">
                <div class="grid gap-6 px-1 py-1">
                    <p
                        v-if="genericFormError !== null"
                        class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ genericFormError }}
                    </p>
                    <div class="grid gap-3">
                        <Label
                            for="about_me_id_birthdate"
                            :class="aboutMeLabelRowClass"
                        >
                            <span>Birthdate</span>
                            <Badge v-if="birthInvalid" variant="destructive">
                                Required
                            </Badge>
                            <Button
                                v-if="birthdate !== undefined"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :class="aboutMeClearFieldButtonClass"
                                aria-label="Clear birthdate"
                                @click="clearBirthdate"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </Label>
                        <Popover v-slot="{ close }">
                            <PopoverTrigger as-child>
                                <Button
                                    id="about_me_id_birthdate"
                                    type="button"
                                    variant="outline"
                                    class="w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30"
                                    :aria-invalid="birthInvalid"
                                >
                                    <span
                                        v-if="birthdateTriggerLabel"
                                        class="min-w-0 flex-1 truncate text-left text-foreground"
                                    >
                                        {{ birthdateTriggerLabel }}
                                    </span>
                                    <span
                                        v-else
                                        class="flex-1 text-left text-muted-foreground"
                                    >
                                        Select birthdate
                                    </span>
                                    <ChevronDown
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
                                    :model-value="birthdateCalendarModel"
                                    @update:model-value="
                                        (value) =>
                                            onBirthdateSelect(value, close)
                                    "
                                />
                            </PopoverContent>
                        </Popover>
                        <p
                            v-if="fieldErrors.birthdate"
                            class="text-xs text-destructive"
                        >
                            {{ fieldErrors.birthdate }}
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2"
                    >
                        <div class="grid gap-3">
                            <Label for="about_me_id_sex" :class="aboutMeLabelRowClass">
                                <span>Sex</span>
                                <Badge v-if="sexInvalid" variant="destructive">
                                    Required
                                </Badge>
                            </Label>
                            <Select
                                :model-value="sex"
                                @update:model-value="
                                    (v) => {
                                        sex =
                                            typeof v === 'string' ? v : String(v);
                                    }
                                "
                            >
                                <SelectTrigger
                                    id="about_me_id_sex"
                                    class="w-full"
                                    :aria-invalid="sexInvalid"
                                >
                                    <SelectValue placeholder="Select sex" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="opt in employeeStepOneSexOptions"
                                        :key="opt"
                                        :value="opt"
                                    >
                                        {{ opt }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="fieldErrors.sex"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.sex }}
                            </p>
                        </div>
                        <div class="grid gap-3">
                            <Label
                                for="about_me_id_civil"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Civil status</span>
                                <Badge variant="secondary"> Optional </Badge>
                            </Label>
                            <Select
                                :model-value="civilStatus"
                                @update:model-value="
                                    (v) => {
                                        civilStatus =
                                            typeof v === 'string'
                                                ? v
                                                : String(v);
                                    }
                                "
                            >
                                <SelectTrigger
                                    id="about_me_id_civil"
                                    class="w-full"
                                >
                                    <SelectValue
                                        placeholder="Select civil status"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="opt in employeeStepOneCivilStatusOptions"
                                        :key="opt"
                                        :value="opt"
                                    >
                                        {{ opt }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="fieldErrors.civil_status"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.civil_status }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <Label
                            for="about_me_id_nationality"
                            :class="aboutMeLabelRowClass"
                        >
                            <span>Nationality</span>
                            <Badge variant="secondary"> Optional </Badge>
                            <Button
                                v-if="nationality !== ''"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :class="aboutMeClearFieldButtonClass"
                                aria-label="Clear nationality"
                                @click="nationality = ''"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </Label>
                        <Select
                            :model-value="nationality"
                            @update:model-value="
                                (v) => {
                                    nationality =
                                        typeof v === 'string' ? v : String(v);
                                }
                            "
                        >
                            <SelectTrigger
                                id="about_me_id_nationality"
                                class="w-full"
                            >
                                <SelectValue placeholder="Select nationality" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in employeeStepOneNationalityOptions"
                                    :key="opt"
                                    :value="opt"
                                >
                                    {{ opt }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="fieldErrors.nationality"
                            class="text-xs text-destructive"
                        >
                            {{ fieldErrors.nationality }}
                        </p>
                    </div>

                    <div class="grid gap-3">
                        <Label
                            for="about_me_id_religion"
                            :class="aboutMeLabelRowClass"
                        >
                            <span>Religion</span>
                            <Badge variant="secondary"> Optional </Badge>
                            <Button
                                v-if="religion !== ''"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :class="aboutMeClearFieldButtonClass"
                                aria-label="Clear religion"
                                @click="
                                    religion = '';
                                    religionOther = '';
                                "
                            >
                                <X class="size-3.5" />
                            </Button>
                        </Label>
                        <Select
                            :model-value="religion"
                            @update:model-value="
                                (v) => {
                                    religion =
                                        typeof v === 'string' ? v : String(v);
                                }
                            "
                        >
                            <SelectTrigger
                                id="about_me_id_religion"
                                class="w-full"
                                :aria-invalid="religionOtherInvalid"
                            >
                                <SelectValue placeholder="Select religion" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in employeeStepOneReligionOptions"
                                    :key="opt"
                                    :value="opt"
                                >
                                    {{ opt }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="fieldErrors.religion"
                            class="text-xs text-destructive"
                        >
                            {{ fieldErrors.religion }}
                        </p>
                    </div>

                    <div
                        v-if="religion === 'Other'"
                        class="grid gap-3"
                    >
                        <Label
                            for="about_me_id_religion_other"
                            :class="aboutMeLabelRowClass"
                        >
                            <span>Specify religion</span>
                            <Badge
                                v-if="religionOtherInvalid"
                                variant="destructive"
                            >
                                Required
                            </Badge>
                            <Button
                                v-if="religionOther !== ''"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :class="aboutMeClearFieldButtonClass"
                                aria-label="Clear specified religion"
                                @click="religionOther = ''"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </Label>
                        <Input
                            id="about_me_id_religion_other"
                            v-model="religionOther"
                            class="w-full"
                            autocomplete="off"
                            placeholder="Enter religion"
                            :aria-invalid="religionOtherInvalid"
                        />
                        <p
                            v-if="fieldErrors.religion_other"
                            class="text-xs text-destructive"
                        >
                            {{ fieldErrors.religion_other }}
                        </p>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-lg"
                    :disabled="processing"
                    @click="onOpenChange(false)"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    class="rounded-lg"
                    :disabled="processing"
                    @click="onSave"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
