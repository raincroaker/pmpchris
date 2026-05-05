<script setup lang="ts">
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';
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
import { Button } from '@/components/ui/button';
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
import { appToast } from '@/lib/app-toast-client';
import type { EditStructureAreaRow } from '@/pages/OrganizationChart/editStructureTypes';

const props = defineProps<{
    areas: EditStructureAreaRow[];
}>();

const emit = defineEmits<{
    'areas-updated': [areas: EditStructureAreaRow[]];
}>();

const open = defineModel<boolean>('open', { default: false });

const localAreas = ref<EditStructureAreaRow[]>([]);
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formTargetId = ref<number | null>(null);
const formCode = ref('');
const formName = ref('');
const formSubmitting = ref(false);
const deleteOpen = ref(false);
const deleteTarget = ref<EditStructureAreaRow | null>(null);
const deleteSubmitting = ref(false);
const DIALOG_CLOSE_RESET_DELAY_MS = 200;
let closeResetTimer: ReturnType<typeof setTimeout> | null = null;
const labelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';
const clearFieldButtonClass =
    'absolute right-0 top-1/2 z-[1] size-6 shrink-0 -translate-y-1/2 rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground';

watch(open, (isOpen) => {
    if (isOpen) {
        if (closeResetTimer !== null) {
            clearTimeout(closeResetTimer);
            closeResetTimer = null;
        }
        localAreas.value = props.areas.map((row) => ({ ...row }));
        return;
    }

    closeResetTimer = setTimeout(() => {
        closeForm();
        deleteOpen.value = false;
        deleteTarget.value = null;
        closeResetTimer = null;
    }, DIALOG_CLOSE_RESET_DELAY_MS);
});

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
        if (typeof value === 'string' && value.trim() !== '') {
            return value;
        }
    }

    return null;
}

function emitAreasUpdated(): void {
    emit(
        'areas-updated',
        localAreas.value.map((row) => ({ ...row })),
    );
}

function openCreate(): void {
    formMode.value = 'create';
    formTargetId.value = null;
    formCode.value = '';
    formName.value = '';
    formOpen.value = true;
}

function openEdit(row: EditStructureAreaRow): void {
    formMode.value = 'edit';
    formTargetId.value = row.id;
    formCode.value = row.code;
    formName.value = row.name;
    formOpen.value = true;
}

function closeForm(): void {
    formOpen.value = false;
    formMode.value = 'create';
    formTargetId.value = null;
    formCode.value = '';
    formName.value = '';
}

async function saveArea(): Promise<void> {
    if (formSubmitting.value) {
        return;
    }

    const code = formCode.value.trim().toUpperCase();
    const name = formName.value.trim();
    if (code === '' || name === '') {
        return;
    }

    formSubmitting.value = true;
    try {
        const csrf = resolveCsrfToken();
        const isEdit = formMode.value === 'edit' && formTargetId.value !== null;
        const endpoint = isEdit
            ? `/organization-chart/edit/areas/${formTargetId.value}`
            : '/organization-chart/edit/areas';
        const response = await fetch(endpoint, {
            method: isEdit ? 'PATCH' : 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify({ code, name }),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            appToast.error(
                firstErrorMessage(payload) ?? 'Unable to save area.',
            );
            return;
        }

        const data = (payload as { data?: EditStructureAreaRow }).data;
        if (!data) {
            appToast.error('Unable to save area.');
            return;
        }

        if (isEdit) {
            localAreas.value = localAreas.value.map((row) =>
                row.id === data.id ? data : row,
            );
            appToast.success('Area updated.');
        } else {
            localAreas.value = [...localAreas.value, data].sort((a, b) => {
                const codeSort = a.code.localeCompare(b.code);
                if (codeSort !== 0) {
                    return codeSort;
                }

                return a.name.localeCompare(b.name);
            });
            appToast.success('Area created.');
        }

        emitAreasUpdated();
        closeForm();
    } finally {
        formSubmitting.value = false;
    }
}

function askDelete(row: EditStructureAreaRow): void {
    deleteTarget.value = row;
    deleteOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    if (deleteSubmitting.value || !deleteTarget.value) {
        return;
    }

    deleteSubmitting.value = true;
    try {
        const csrf = resolveCsrfToken();
        const response = await fetch(
            `/organization-chart/edit/areas/${deleteTarget.value.id}`,
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
            appToast.error(
                firstErrorMessage(payload) ?? 'Unable to delete area.',
            );
            return;
        }

        const targetId = deleteTarget.value.id;
        localAreas.value = localAreas.value.filter(
            (row) => row.id !== targetId,
        );
        emitAreasUpdated();
        deleteOpen.value = false;
        deleteTarget.value = null;
        appToast.success('Area deleted.');
    } finally {
        deleteSubmitting.value = false;
    }
}

onUnmounted(() => {
    if (closeResetTimer !== null) {
        clearTimeout(closeResetTimer);
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Areas</DialogTitle>
                <DialogDescription>
                    Manage area code and name.
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 px-1 py-1">
                <ScrollArea
                    class="h-[min(46vh,360px)] **:data-[slot=scroll-area-viewport]:focus-visible:ring-0 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none"
                >
                    <div class="pr-3">
                        <ul class="divide-y divide-border/60">
                            <li
                                v-for="row in localAreas"
                                :key="row.id"
                                class="flex items-center gap-3 px-1 py-2 transition-colors hover:bg-accent/30"
                            >
                                <span
                                    class="min-w-0 flex-1 truncate text-sm text-foreground"
                                >
                                    <span class="font-medium">{{
                                        row.name
                                    }}</span>
                                    <span
                                        class="pl-2 font-mono text-xs tracking-[0.08em] text-muted-foreground uppercase"
                                    >
                                        {{ row.code }}
                                    </span>
                                </span>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8"
                                    aria-label="Edit area"
                                    @click="openEdit(row)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 text-muted-foreground hover:text-destructive"
                                    aria-label="Delete area"
                                    @click="askDelete(row)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </li>
                            <li
                                v-if="localAreas.length === 0"
                                class="px-3 py-6 text-center text-sm text-muted-foreground"
                            >
                                No areas found.
                            </li>
                        </ul>
                    </div>
                </ScrollArea>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button type="button" variant="outline" @click="open = false">
                    Back
                </Button>
                <Button type="button" @click="openCreate">
                    <Plus class="size-4" />
                    Add
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="formOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    formMode === 'create' ? 'Add area' : 'Edit area'
                }}</DialogTitle>
                <DialogDescription>
                    Use short code and clear area name.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-3 py-1">
                <div class="grid gap-1.5">
                    <Label :class="labelRowClass" for="area-code-input">
                        <span>Code</span>
                        <Button
                            v-if="formCode.trim() !== ''"
                            type="button"
                            size="icon"
                            variant="ghost"
                            :class="clearFieldButtonClass"
                            aria-label="Clear area code"
                            @click="formCode = ''"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </Label>
                    <Input
                        id="area-code-input"
                        v-model="formCode"
                        placeholder="e.g. PAN"
                        autocomplete="off"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label :class="labelRowClass" for="area-name-input">
                        <span>Name</span>
                        <Button
                            v-if="formName.trim() !== ''"
                            type="button"
                            size="icon"
                            variant="ghost"
                            :class="clearFieldButtonClass"
                            aria-label="Clear area name"
                            @click="formName = ''"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </Label>
                    <Input
                        id="area-name-input"
                        v-model="formName"
                        placeholder="e.g. Panabo Area"
                        autocomplete="off"
                    />
                </div>
            </div>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button type="button" variant="outline" @click="closeForm">
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="
                        formSubmitting ||
                        formCode.trim() === '' ||
                        formName.trim() === ''
                    "
                    @click="saveArea"
                >
                    {{ formMode === 'create' ? 'Save' : 'Save changes' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete area?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will remove
                    <span class="font-medium text-foreground">
                        {{ deleteTarget?.name }} {{ deleteTarget?.code }}
                    </span>
                    from this organization.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="deleteSubmitting"
                    @click="confirmDelete"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
