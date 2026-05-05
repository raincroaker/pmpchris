<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Check,
    Eye,
    MoreVertical,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import {
    formatHolidayTypePaySummary,
    getHolidaySheetListToneClasses,
    holidayTypeColorOptions,
} from '@/components/calendar/holiday-types-seed';
import type {
    HolidayTypeColorKey,
    HolidayTypeDefinition,
} from '@/components/calendar/holiday-types-seed';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { Textarea } from '@/components/ui/textarea';
import { appToast } from '@/lib/app-toast-client';
import { cn } from '@/lib/utils';
import type { HolidayPayPolicy } from '@/pages/Attendance/attendanceRulesTypes';
import { holidayPayPolicyOptions } from '@/pages/Attendance/attendanceRulesTypes';
import calendar from '@/routes/calendar';

type TypeFormMode = 'create' | 'editPreset' | 'editCustom';

/** Lets Dialog leave animations finish before clearing row refs (avoids “pop”). */
const DIALOG_LEAVE_MS = 220;

const open = defineModel<boolean>('open', { default: false });
const types = defineModel<HolidayTypeDefinition[]>('types', { required: true });

const props = withDefaults(
    defineProps<{
        canManage?: boolean;
        calendarDisplayMonth: string;
    }>(),
    {
        canManage: true,
    },
);

const isTypeFormOpen = ref(false);
const typeFormMode = ref<TypeFormMode>('create');
const typeFormTargetId = ref<string | null>(null);

const nameInput = ref('');
const colorKeyInput = ref<HolidayTypeColorKey>('sky');
const premiumNoteInput = ref('');
const payPolicyInput = ref<HolidayPayPolicy>('Double Pay');
const customMultiplierInput = ref('');
/** True when the type name cannot be edited (preset / standard catalog rows). */
const nameLocked = ref(false);

const pendingDeleteType = ref<HolidayTypeDefinition | null>(null);
const isDeleteConfirmOpen = ref(false);
const isSavingType = ref(false);
const isDeletingType = ref(false);

const detailTarget = ref<HolidayTypeDefinition | null>(null);
const isDetailOpen = ref(false);
let detailDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;

const labelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';
const clearFieldButtonClass =
    'absolute right-0 top-1/2 z-[1] size-6 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground';

const selectedColorOption = computed(
    () =>
        holidayTypeColorOptions.find(
            (option) => option.key === colorKeyInput.value,
        ) ?? holidayTypeColorOptions[0],
);

const formDialogTitle = computed(() =>
    typeFormMode.value === 'create' ? 'Add holiday type' : 'Edit holiday type',
);

const formDialogDescription = computed(() => {
    if (typeFormMode.value === 'create') {
        return 'Choose a name, pay behavior, and color.';
    }

    if (nameLocked.value) {
        return "This preset type's name is fixed. You can change pay behavior, color, and notes.";
    }

    return 'Update the name, pay behavior, color, and optional note for this type.';
});

const formDialogActionLabel = computed(() =>
    typeFormMode.value === 'create' ? 'Save' : 'Save changes',
);

const isNameValid = computed(() => nameInput.value.trim().length > 0);

watch(open, (isOpen) => {
    if (!isOpen) {
        closeAllSecondaryDialogs();
    }
});

function closeAllSecondaryDialogs(): void {
    if (detailDialogClearTimeout !== undefined) {
        clearTimeout(detailDialogClearTimeout);
        detailDialogClearTimeout = undefined;
    }
    isTypeFormOpen.value = false;
    isDetailOpen.value = false;
    detailTarget.value = null;
    isDeleteConfirmOpen.value = false;
    pendingDeleteType.value = null;
    resetTypeForm();
}

function resetTypeForm(): void {
    typeFormMode.value = 'create';
    typeFormTargetId.value = null;
    nameInput.value = '';
    colorKeyInput.value = 'sky';
    premiumNoteInput.value = '';
    payPolicyInput.value = 'Double Pay';
    customMultiplierInput.value = '';
    nameLocked.value = false;
}

watch(isTypeFormOpen, (isOpen) => {
    if (!isOpen) {
        resetTypeForm();
    }
});

function onDetailDialogOpenChange(nextOpen: boolean): void {
    if (nextOpen) {
        if (detailDialogClearTimeout !== undefined) {
            clearTimeout(detailDialogClearTimeout);
            detailDialogClearTimeout = undefined;
        }
        isDetailOpen.value = true;

        return;
    }

    isDetailOpen.value = false;
    if (detailDialogClearTimeout !== undefined) {
        clearTimeout(detailDialogClearTimeout);
    }

    detailDialogClearTimeout = setTimeout(() => {
        detailTarget.value = null;
        detailDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function openDetail(row: HolidayTypeDefinition): void {
    if (detailDialogClearTimeout !== undefined) {
        clearTimeout(detailDialogClearTimeout);
        detailDialogClearTimeout = undefined;
    }
    detailTarget.value = row;
    isDetailOpen.value = true;
}

function closeDetail(): void {
    onDetailDialogOpenChange(false);
}

function syncDetailTargetFromTypes(typeId: string | null): void {
    if (!typeId || !isDetailOpen.value) {
        return;
    }
    if (detailTarget.value?.id !== typeId) {
        return;
    }

    const updated = types.value.find((t) => t.id === typeId);
    if (updated) {
        detailTarget.value = updated;
    }
}

function handleDetailEdit(): void {
    const row = detailTarget.value;
    if (row) {
        openEdit(row);
    }
}

function handleDetailDelete(): void {
    const row = detailTarget.value;
    if (row) {
        askDeleteType(row);
    }
}

function openCreateTypeDialog(): void {
    if (!props.canManage) {
        return;
    }

    resetTypeForm();
    typeFormMode.value = 'create';
    nameLocked.value = false;
    isTypeFormOpen.value = true;
}

function openEditPresetDialog(row: HolidayTypeDefinition): void {
    resetTypeForm();
    typeFormMode.value = 'editPreset';
    typeFormTargetId.value = row.id;
    nameInput.value = row.name;
    nameLocked.value = true;
    colorKeyInput.value = row.colorKey;
    premiumNoteInput.value = row.premiumNote ?? '';
    payPolicyInput.value = row.pay_policy;
    customMultiplierInput.value = row.custom_multiplier ?? '';
    isTypeFormOpen.value = true;
}

function openEditCustomDialog(row: HolidayTypeDefinition): void {
    resetTypeForm();
    typeFormMode.value = 'editCustom';
    typeFormTargetId.value = row.id;
    nameInput.value = row.name;
    nameLocked.value = false;
    colorKeyInput.value = row.colorKey;
    premiumNoteInput.value = row.premiumNote ?? '';
    payPolicyInput.value = row.pay_policy;
    customMultiplierInput.value = row.custom_multiplier ?? '';
    isTypeFormOpen.value = true;
}

function openEdit(row: HolidayTypeDefinition): void {
    if (row.kind === 'builtin') {
        openEditPresetDialog(row);
        return;
    }

    openEditCustomDialog(row);
}

function closeTypeFormDialog(): void {
    isTypeFormOpen.value = false;
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

async function saveTypeForm(): Promise<void> {
    if (!props.canManage || isSavingType.value) {
        return;
    }

    const premiumNote =
        premiumNoteInput.value.trim() === ''
            ? null
            : premiumNoteInput.value.trim();
    const payPolicy = payPolicyInput.value;
    const customMultiplier =
        payPolicy === 'Custom Multiplier'
            ? customMultiplierInput.value.trim()
            : '';

    if (payPolicy === 'Custom Multiplier' && customMultiplier === '') {
        return;
    }

    const payPayload = {
        pay_policy: payPolicy,
        custom_multiplier:
            payPolicy === 'Custom Multiplier' ? customMultiplier : null,
        premiumNote,
    };

    if (typeFormMode.value === 'create') {
        const name = nameInput.value.trim();
        if (name === '') {
            return;
        }

        isSavingType.value = true;

        const operation = (async (): Promise<void> => {
            const csrf = resolveCsrfToken();
            const response = await fetch(calendar.holidayTypes.store.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({
                    name,
                    colorKey: colorKeyInput.value,
                    ...payPayload,
                }),
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(
                    firstErrorMessage(payload) ??
                        'Unable to create holiday type.',
                );
            }

            reloadHolidayCalendarPartial(() => closeTypeFormDialog());
        })();

        try {
            await appToast.promise(operation, {
                loading: 'Creating holiday type...',
                success: 'Holiday type created.',
                error: (error: unknown) =>
                    error instanceof Error && error.message.trim() !== ''
                        ? error.message
                        : 'Unable to create holiday type.',
            });
        } finally {
            isSavingType.value = false;
        }

        return;
    }

    if (!typeFormTargetId.value) {
        return;
    }

    const slug = typeFormTargetId.value;

    if (typeFormMode.value === 'editPreset') {
        isSavingType.value = true;

        const operation = (async (): Promise<void> => {
            const csrf = resolveCsrfToken();
            const response = await fetch(
                calendar.holidayTypes.update.url({ holiday_type_slug: slug }),
                {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    body: JSON.stringify({
                        colorKey: colorKeyInput.value,
                        ...payPayload,
                    }),
                },
            );

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(
                    firstErrorMessage(payload) ??
                        'Unable to update holiday type.',
                );
            }

            reloadHolidayCalendarPartial(() => {
                syncDetailTargetFromTypes(slug);
                closeTypeFormDialog();
            });
        })();

        try {
            await appToast.promise(operation, {
                loading: 'Saving holiday type...',
                success: 'Holiday type updated.',
                error: (error: unknown) =>
                    error instanceof Error && error.message.trim() !== ''
                        ? error.message
                        : 'Unable to update holiday type.',
            });
        } finally {
            isSavingType.value = false;
        }

        return;
    }

    const name = nameInput.value.trim();
    if (name === '') {
        return;
    }

    isSavingType.value = true;

    const operation = (async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(
            calendar.holidayTypes.update.url({ holiday_type_slug: slug }),
            {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({
                    name,
                    colorKey: colorKeyInput.value,
                    ...payPayload,
                }),
            },
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstErrorMessage(payload) ?? 'Unable to update holiday type.',
            );
        }

        reloadHolidayCalendarPartial(() => {
            syncDetailTargetFromTypes(slug);
            closeTypeFormDialog();
        });
    })();

    try {
        await appToast.promise(operation, {
            loading: 'Saving holiday type...',
            success: 'Holiday type updated.',
            error: (error: unknown) =>
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : 'Unable to update holiday type.',
        });
    } finally {
        isSavingType.value = false;
    }
}

function askDeleteType(row: HolidayTypeDefinition): void {
    if (!props.canManage || row.kind !== 'custom') {
        return;
    }

    pendingDeleteType.value = row;
    isDeleteConfirmOpen.value = true;
}

async function confirmDeleteType(): Promise<void> {
    if (!props.canManage || !pendingDeleteType.value || isDeletingType.value) {
        return;
    }

    const slug = pendingDeleteType.value.id;

    isDeletingType.value = true;

    const operation = (async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(
            calendar.holidayTypes.destroy.url({ holiday_type_slug: slug }),
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
                firstErrorMessage(payload) ?? 'Unable to delete holiday type.',
            );
        }

        reloadHolidayCalendarPartial(() => {
            pendingDeleteType.value = null;
            isDeleteConfirmOpen.value = false;

            if (detailTarget.value?.id === slug) {
                onDetailDialogOpenChange(false);
            }
        });
    })();

    try {
        await appToast.promise(operation, {
            loading: 'Deleting holiday type...',
            success: 'Holiday type deleted.',
            error: (error: unknown) =>
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : 'Unable to delete holiday type.',
        });
    } finally {
        isDeletingType.value = false;
    }
}

function closeMainDialog(): void {
    open.value = false;
}

onUnmounted(() => {
    if (detailDialogClearTimeout !== undefined) {
        clearTimeout(detailDialogClearTimeout);
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="flex max-h-[min(90vh,620px)] w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-xl"
        >
            <DialogHeader class="shrink-0 px-6 pt-6 pb-4">
                <DialogTitle>Holiday types</DialogTitle>
                <DialogDescription
                    >Manage pay behavior, color, and notes.</DialogDescription
                >
            </DialogHeader>

            <div class="min-h-0 flex-1 px-6 py-4">
                <Label class="mb-3 block text-sm font-medium text-foreground"
                    >Types</Label
                >

                <ScrollArea
                    class="h-[min(46vh,360px)] **:data-[slot=scroll-area-viewport]:focus-visible:ring-0 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none"
                >
                    <div class="pr-3">
                        <ul class="grid gap-2.5">
                            <li
                                v-for="row in types"
                                :key="row.id"
                                class="list-none"
                            >
                                <div
                                    :class="
                                        cn(
                                            'relative flex w-full items-center gap-1 rounded-lg border px-2 py-2 pl-3 shadow-xs transition-colors sm:gap-2',
                                            getHolidaySheetListToneClasses(
                                                row.colorKey,
                                            ).card,
                                        )
                                    "
                                >
                                    <button
                                        type="button"
                                        class="absolute inset-0 z-0 cursor-pointer rounded-lg focus-visible:ring-0 focus-visible:outline-none"
                                        :aria-label="`View ${row.name}`"
                                        @click="openDetail(row)"
                                    />
                                    <div
                                        class="pointer-events-none relative z-1 flex min-w-0 flex-1 items-center gap-3 py-1"
                                    >
                                        <span
                                            class="size-2.5 shrink-0 rounded-full ring-1 ring-border/60"
                                            :class="
                                                holidayTypeColorOptions.find(
                                                    (option) =>
                                                        option.key ===
                                                        row.colorKey,
                                                )?.swatchClass ?? 'bg-primary'
                                            "
                                            aria-hidden="true"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                            >
                                                <p
                                                    class="truncate text-sm font-medium text-foreground"
                                                >
                                                    {{ row.name }}
                                                </p>
                                                <Badge
                                                    variant="outline"
                                                    class="max-w-44 shrink-0 truncate text-xs font-normal text-foreground"
                                                    :title="
                                                        formatHolidayTypePaySummary(
                                                            row,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatHolidayTypePaySummary(
                                                            row,
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="pointer-events-auto relative z-1 shrink-0"
                                    >
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-8 shrink-0 cursor-default hover:bg-muted/60 hover:text-foreground dark:hover:bg-muted/50"
                                                    :aria-label="`Actions for ${row.name}`"
                                                    @click.stop
                                                >
                                                    <MoreVertical
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent
                                                align="end"
                                                class="min-w-52"
                                            >
                                                <DropdownMenuItem
                                                    @click="openDetail(row)"
                                                >
                                                    <Eye
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    View
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="canManage"
                                                    @click="openEdit(row)"
                                                >
                                                    <Pencil
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        canManage &&
                                                        row.kind === 'custom'
                                                    "
                                                    variant="destructive"
                                                    @click="askDeleteType(row)"
                                                >
                                                    <Trash2
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </ScrollArea>
            </div>

            <DialogFooter
                class="shrink-0 gap-2 px-6 py-4 sm:justify-end sm:gap-2"
            >
                <Button
                    type="button"
                    variant="outline"
                    @click="closeMainDialog"
                >
                    Back
                </Button>
                <Button
                    v-if="canManage"
                    type="button"
                    variant="default"
                    @click="openCreateTypeDialog"
                >
                    <Plus class="size-4" />
                    <span>Add</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="isDetailOpen" @update:open="onDetailDialogOpenChange">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader v-if="detailTarget">
                <DialogTitle>Holiday type details</DialogTitle>
                <DialogDescription>
                    Read-only snapshot of pay rules, color, and notes for this
                    type.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea
                v-if="detailTarget"
                class="max-h-[min(70vh,520px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:ring-0 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none"
            >
                <div class="grid gap-3 px-2 py-2 text-sm sm:grid-cols-2">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Name
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                        >
                            {{ detailTarget.name }}
                        </p>
                    </div>

                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Pay behavior
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                        >
                            {{ formatHolidayTypePaySummary(detailTarget) }}
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Color
                        </p>
                        <div
                            class="flex items-center gap-3 rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                        >
                            <span
                                class="size-3 shrink-0 rounded-full ring-1 ring-border/60"
                                :class="
                                    holidayTypeColorOptions.find(
                                        (o) => o.key === detailTarget?.colorKey,
                                    )?.swatchClass ?? 'bg-primary'
                                "
                                aria-hidden="true"
                            />
                            <span class="font-medium">
                                {{
                                    holidayTypeColorOptions.find(
                                        (o) => o.key === detailTarget?.colorKey,
                                    )?.label ?? '—'
                                }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="detailTarget.premiumNote"
                        class="grid gap-1.5 sm:col-span-2"
                    >
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Notes
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm whitespace-pre-wrap text-foreground"
                        >
                            {{ detailTarget.premiumNote }}
                        </p>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button type="button" variant="outline" @click="closeDetail">
                    Close
                </Button>
                <Button
                    v-if="canManage && detailTarget?.kind === 'custom'"
                    type="button"
                    variant="destructive"
                    @click="handleDetailDelete"
                >
                    <Trash2 class="size-4" />
                    Delete
                </Button>
                <Button
                    v-if="canManage"
                    type="button"
                    @click="handleDetailEdit"
                >
                    <Pencil class="size-4" />
                    Edit
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="isTypeFormOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ formDialogTitle }}</DialogTitle>
                <DialogDescription>{{
                    formDialogDescription
                }}</DialogDescription>
            </DialogHeader>

            <ScrollArea
                class="max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:ring-0 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none"
            >
                <div class="grid gap-4 px-2.5 py-3">
                    <div class="grid gap-2">
                        <Label for="holiday-type-name" :class="labelRowClass">
                            <span>Name</span>
                            <Button
                                v-if="
                                    nameInput !== '' &&
                                    typeFormMode === 'editCustom'
                                "
                                type="button"
                                variant="ghost"
                                size="icon"
                                :class="clearFieldButtonClass"
                                aria-label="Clear name"
                                @click="nameInput = ''"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </Label>
                        <Input
                            id="holiday-type-name"
                            v-model="nameInput"
                            placeholder="e.g. Company-declared premium day"
                            autocomplete="off"
                            :disabled="nameLocked"
                            @keydown.enter.prevent="saveTypeForm"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>Pay behavior</Label>
                        <Select v-model="payPolicyInput">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pay behavior" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in holidayPayPolicyOptions"
                                    :key="opt"
                                    :value="opt"
                                >
                                    {{ opt }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div
                        v-if="payPolicyInput === 'Custom Multiplier'"
                        class="grid gap-2"
                    >
                        <Label for="holiday-type-multiplier"
                            >Custom multiplier</Label
                        >
                        <Input
                            id="holiday-type-multiplier"
                            v-model="customMultiplierInput"
                            placeholder="e.g. 1.30x"
                            autocomplete="off"
                            @keydown.enter.prevent="saveTypeForm"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="holiday-premium-note"
                            :class="labelRowClass"
                        >
                            <span>Premium note (optional)</span>
                            <Button
                                v-if="premiumNoteInput !== ''"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :class="clearFieldButtonClass"
                                aria-label="Clear premium note"
                                @click="premiumNoteInput = ''"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </Label>
                        <Textarea
                            id="holiday-premium-note"
                            v-model="premiumNoteInput"
                            rows="3"
                            placeholder="e.g. 1.35x hourly for hours worked..."
                            class="resize-y"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>Color</Label>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <Button
                                v-for="option in holidayTypeColorOptions"
                                :key="option.key"
                                type="button"
                                variant="outline"
                                class="h-10 justify-start gap-2 px-2.5"
                                :title="option.label"
                                :class="
                                    cn(
                                        colorKeyInput === option.key &&
                                            'border-primary bg-primary/8 text-foreground',
                                    )
                                "
                                @click="colorKeyInput = option.key"
                            >
                                <span
                                    class="size-2.5 shrink-0 rounded-full ring-1 ring-border/60"
                                    :class="option.swatchClass"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ option.label }}</span>
                                <Check
                                    v-if="colorKeyInput === option.key"
                                    class="ml-auto size-3.5 text-primary"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Selected:
                            <span class="font-medium text-foreground">{{
                                selectedColorOption.label
                            }}</span>
                        </p>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter class="gap-2 sm:gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="outline"> Cancel </Button>
                </DialogClose>
                <Button
                    type="button"
                    :disabled="
                        (typeFormMode === 'create' ||
                            typeFormMode === 'editCustom') &&
                        !isNameValid
                    "
                    @click="saveTypeForm"
                >
                    {{ formDialogActionLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="isDeleteConfirmOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete holiday type?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will remove
                    <span class="font-semibold text-foreground">{{
                        pendingDeleteType?.name ?? 'this type'
                    }}</span>
                    from the list. Holidays referencing it may show as unknown
                    until reassigned.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="confirmDeleteType"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
