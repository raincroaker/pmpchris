<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import syncEmployeeAboutMeContacts from '@/actions/App/Http/Controllers/SyncEmployeeAboutMeContactsController';
import {
    aboutMeDialogScrollAreaClass,
    aboutMeLabelRowClass,
} from '@/components/hris/aboutMeDialogUi';
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
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { appToast } from '@/lib/app-toast-client';
import type { EmployeeProfileDisplay } from '@/pages/Employees/employeeProfileDisplay';

type PageErrorsBag = Record<string, string>;

const props = defineProps<{
    profile: EmployeeProfileDisplay;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const channelOptions = ['Mobile', 'Home'] as const;

const maxEmergencyRows = 3;

type EmergencyContactDraft = {
    contact_person: string;
    relationship: string;
    channel_label: string;
    contact_number: string;
    email: string;
    is_primary: boolean;
};

const rows = ref<EmergencyContactDraft[]>([]);
const attemptedSave = ref(false);
const formError = ref('');
const processing = ref(false);
const fieldErrors = ref<PageErrorsBag>({});

function cloneFromProfile(): void {
    rows.value = props.profile.emergency_contacts
        .slice(0, maxEmergencyRows)
        .map((c) => ({
            contact_person: c.contact_person ?? '',
            relationship: c.relationship ?? '',
            channel_label: c.channel_label,
            contact_number: c.contact_number,
            email: c.email ?? '',
            is_primary: c.is_primary,
        }));
    ensureOnePrimaryIfNeeded();
}

function ensureOnePrimaryIfNeeded(): void {
    const anyPrimary = rows.value.some((r) => r.is_primary);
    if (!anyPrimary && rows.value.length > 0) {
        rows.value[0].is_primary = true;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            attemptedSave.value = false;
            formError.value = '';
            processing.value = false;
            fieldErrors.value = {};
            cloneFromProfile();
        }
    },
);

function addRow(): void {
    if (rows.value.length >= maxEmergencyRows) {
        return;
    }

    rows.value.push({
        contact_person: '',
        relationship: '',
        channel_label: 'Mobile',
        contact_number: '',
        email: '',
        is_primary: rows.value.length === 0,
    });
}

function removeRow(index: number): void {
    if (rows.value.length <= 1) {
        return;
    }

    const wasPrimary = rows.value[index]?.is_primary;
    rows.value.splice(index, 1);
    if (wasPrimary && rows.value.length > 0) {
        rows.value[0].is_primary = true;
    }
}

function setPrimary(index: number, checked: unknown): void {
    const on = Boolean(checked);
    rows.value.forEach((r, i) => {
        r.is_primary = on && i === index;
    });
    if (rows.value.every((r) => !r.is_primary) && rows.value.length > 0) {
        rows.value[index].is_primary = true;
    }
}

function normalizePrimaries(
    validRows: EmergencyContactDraft[],
): EmergencyContactDraft[] {
    const draft = validRows.map((r) => ({ ...r }));
    const primaryIdx = draft.findIndex((r) => r.is_primary);
    if (primaryIdx >= 0) {
        draft.forEach((r, i) => {
            r.is_primary = i === primaryIdx;
        });
        return draft;
    }
    draft[0].is_primary = true;
    draft.forEach((r, i) => {
        r.is_primary = i === 0;
    });
    return draft;
}

function onSave(): void {
    attemptedSave.value = true;
    formError.value = '';

    const nonempty = rows.value.filter(
        (r) =>
            r.contact_person.trim() !== '' ||
            r.contact_number.trim() !== '' ||
            r.relationship.trim() !== '' ||
            r.email.trim() !== '',
    );

    if (nonempty.length === 0) {
        formError.value =
            'Add at least one emergency contact with contact person and phone number.';
        return;
    }

    if (
        nonempty.some(
            (r) =>
                r.contact_person.trim() === '' ||
                r.contact_number.trim() === '',
        )
    ) {
        formError.value =
            'Each emergency contact needs a contact person and phone number.';
        return;
    }

    const rebuilt = nonempty.map((r) => ({
        ...r,
        channel_label:
            r.channel_label.trim() === '' ? 'Mobile' : r.channel_label.trim(),
    }));

    const primed = normalizePrimaries(rebuilt);

    const personalPayload = props.profile.personal_contacts.map((c) => ({
        channel_label: c.channel_label,
        contact_number: c.contact_number,
        email:
            c.email !== null &&
            typeof c.email === 'string' &&
            c.email.trim() !== ''
                ? c.email.trim()
                : null,
        is_primary: c.is_primary,
    }));

    processing.value = true;
    fieldErrors.value = {};

    router.patch(
        syncEmployeeAboutMeContacts.url(props.profile.employee_id),
        {
            personal: personalPayload,
            emergency: primed.map((r) => ({
                channel_label: r.channel_label,
                contact_person: r.contact_person.trim(),
                relationship:
                    r.relationship.trim() !== '' ? r.relationship.trim() : null,
                contact_number: r.contact_number.trim(),
                email: r.email.trim() !== '' ? r.email.trim() : null,
                is_primary: r.is_primary,
            })),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                appToast.success('Emergency contacts saved.');
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
                        'Could not save contacts. Please try again.',
                    );
                }
            },
        },
    );
}

function onOpenChange(value: boolean): void {
    emit('update:open', value);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Edit emergency contacts</DialogTitle>
                <DialogDescription>
                    Matches duty-of-care escalation fields from Add Employee
                    step 2. Changes save to HR records immediately.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="aboutMeDialogScrollAreaClass">
                <div class="grid gap-6 px-1 py-1">
                    <p
                        v-if="
                            fieldErrors.emergency !== undefined &&
                            fieldErrors.emergency !== ''
                        "
                        class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ fieldErrors.emergency }}
                    </p>
                    <div
                        v-for="(row, index) in rows"
                        :key="index"
                        class="space-y-4 rounded-lg border border-border/60 bg-muted/10 p-4"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <Badge variant="outline" class="text-xs">
                                Emergency {{ index + 1 }}
                            </Badge>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                :disabled="rows.length <= 1"
                                aria-label="Remove emergency contact row"
                                @click="removeRow(index)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Checkbox
                                :id="`em-primary-${index}`"
                                class="shrink-0"
                                :model-value="row.is_primary"
                                :aria-labelledby="`em-primary-lbl-${index}`"
                                @update:model-value="setPrimary(index, $event)"
                            />
                            <label
                                :id="`em-primary-lbl-${index}`"
                                class="cursor-pointer text-sm text-foreground"
                                :for="`em-primary-${index}`"
                                >Primary contact</label
                            >
                        </div>
                        <div class="grid gap-3">
                            <Label
                                :for="`em-name-${index}`"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Contact person</span>
                                <Badge
                                    v-if="
                                        attemptedSave &&
                                        row.contact_person.trim() === '' &&
                                        (row.contact_number.trim() !== '' ||
                                            row.email.trim() !== '')
                                    "
                                    variant="destructive"
                                >
                                    Required
                                </Badge>
                            </Label>
                            <Input
                                :id="`em-name-${index}`"
                                v-model="row.contact_person"
                                class="w-full"
                                autocomplete="off"
                                placeholder="Full name"
                            />
                        </div>
                        <div class="grid gap-3">
                            <Label
                                :for="`em-rel-${index}`"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Relationship</span>
                            </Label>
                            <Input
                                :id="`em-rel-${index}`"
                                v-model="row.relationship"
                                class="w-full"
                                autocomplete="off"
                                placeholder="e.g. Spouse, Parent"
                            />
                        </div>
                        <div class="grid gap-3">
                            <Label
                                :for="`em-ch-${index}`"
                                :class="aboutMeLabelRowClass"
                            >
                                Channel
                            </Label>
                            <Select
                                :model-value="row.channel_label"
                                @update:model-value="
                                    (v) => {
                                        row.channel_label =
                                            typeof v === 'string'
                                                ? v
                                                : String(v);
                                    }
                                "
                            >
                                <SelectTrigger
                                    :id="`em-ch-${index}`"
                                    class="w-full"
                                >
                                    <SelectValue placeholder="Channel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="ch in channelOptions"
                                        :key="ch"
                                        :value="ch"
                                    >
                                        {{ ch }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-3">
                            <Label
                                :for="`em-num-${index}`"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Number</span>
                                <Badge
                                    v-if="
                                        attemptedSave &&
                                        row.contact_number.trim() === '' &&
                                        row.contact_person.trim() !== ''
                                    "
                                    variant="destructive"
                                >
                                    Required
                                </Badge>
                            </Label>
                            <Input
                                :id="`em-num-${index}`"
                                v-model="row.contact_number"
                                class="w-full font-mono tabular-nums"
                                autocomplete="tel"
                                placeholder="+63…"
                            />
                        </div>
                        <div class="grid gap-3">
                            <Label
                                :for="`em-email-${index}`"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Email</span>
                                <Badge variant="secondary"> Optional </Badge>
                            </Label>
                            <Input
                                :id="`em-email-${index}`"
                                v-model="row.email"
                                class="w-full"
                                type="email"
                                autocomplete="email"
                                placeholder="name@example.com"
                            />
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        class="w-full rounded-lg"
                        :disabled="rows.length >= maxEmergencyRows"
                        @click="addRow"
                    >
                        <Plus class="mr-2 size-4" aria-hidden="true" />
                        {{
                            rows.length >= maxEmergencyRows
                                ? 'Maximum 3 contacts'
                                : 'Add emergency contact'
                        }}
                    </Button>
                    <p v-if="formError !== ''" class="text-sm text-destructive">
                        {{ formError }}
                    </p>
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
