<script setup lang="ts">
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { calendarCategoryColorOptions } from '@/components/calendar/calendar-categories-seed';
import type {
    CalendarCategoryColorKey,
    CalendarCategoryRow,
} from '@/components/calendar/calendar-categories-seed';
import type { CalendarEventCategory } from '@/components/calendar/calendar-events';
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
import { cn } from '@/lib/utils';

type CategoryFormMode = 'create' | 'edit';

const props = withDefaults(
    defineProps<{
        canManage?: boolean;
        categories?: CalendarEventCategory[];
    }>(),
    {
        canManage: true,
        categories: () => [],
    },
);
const emit = defineEmits<{
    'categories-updated': [categories: CalendarEventCategory[]];
}>();

const open = defineModel<boolean>('open', { default: false });

const localCategories = ref<CalendarCategoryRow[]>([]);

const isCategoryFormOpen = ref(false);
const categoryFormMode = ref<CategoryFormMode>('create');
const categoryFormTargetId = ref<number | null>(null);
const categoryNameInput = ref('');
const categoryColorKeyInput = ref<CalendarCategoryColorKey>('teal');

const pendingDeleteCategory = ref<CalendarCategoryRow | null>(null);
const isDeleteConfirmOpen = ref(false);
const isCreatingCategory = ref(false);
const isUpdatingCategory = ref(false);
const isDeletingCategory = ref(false);

const labelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';
const clearFieldButtonClass =
    'absolute right-0 top-1/2 z-[1] size-6 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground';

const selectedColorOption = computed(
    () =>
        calendarCategoryColorOptions.find(
            (option) => option.key === categoryColorKeyInput.value,
        ) ?? calendarCategoryColorOptions[0],
);

const formDialogTitle = computed(() =>
    categoryFormMode.value === 'create' ? 'Add category' : 'Edit category',
);

const formDialogActionLabel = computed(() =>
    categoryFormMode.value === 'create' ? 'Save' : 'Save changes',
);

const isCategoryNameValid = computed(
    () => categoryNameInput.value.trim().length > 0,
);
const isCategoryFormSubmitting = computed(() =>
    categoryFormMode.value === 'create'
        ? isCreatingCategory.value
        : isUpdatingCategory.value,
);
const isAnyMutationPending = computed(
    () =>
        isCreatingCategory.value ||
        isUpdatingCategory.value ||
        isDeletingCategory.value,
);

watch(open, (isOpen) => {
    if (!isOpen) {
        closeAllSecondaryDialogs();
        return;
    }

    localCategories.value = props.categories.map((row) => ({ ...row }));
});

function closeAllSecondaryDialogs(): void {
    isCategoryFormOpen.value = false;
    isDeleteConfirmOpen.value = false;
    pendingDeleteCategory.value = null;
    resetCategoryForm();
}

function resetCategoryForm(): void {
    categoryFormMode.value = 'create';
    categoryFormTargetId.value = null;
    categoryNameInput.value = '';
    categoryColorKeyInput.value = 'teal';
}

function openCreateCategoryDialog(): void {
    if (!props.canManage || isAnyMutationPending.value) {
        return;
    }

    resetCategoryForm();
    categoryFormMode.value = 'create';
    isCategoryFormOpen.value = true;
}

function openEditCategoryDialog(row: CalendarCategoryRow): void {
    if (!props.canManage || isAnyMutationPending.value) {
        return;
    }

    categoryFormMode.value = 'edit';
    categoryFormTargetId.value = row.id;
    categoryNameInput.value = row.name;
    categoryColorKeyInput.value = row.colorKey;
    isCategoryFormOpen.value = true;
}

function closeCategoryFormDialog(): void {
    isCategoryFormOpen.value = false;
    resetCategoryForm();
}

function saveCategoryForm(): void {
    if (!props.canManage || isCategoryFormSubmitting.value) {
        return;
    }

    const name = categoryNameInput.value.trim();
    if (name === '') {
        return;
    }

    if (categoryFormMode.value === 'create') {
        void createCategory(name, categoryColorKeyInput.value);
        return;
    }

    if (!categoryFormTargetId.value) {
        return;
    }

    void updateCategory(
        categoryFormTargetId.value,
        name,
        categoryColorKeyInput.value,
    );
}

function askDeleteCategory(row: CalendarCategoryRow): void {
    if (!props.canManage || isAnyMutationPending.value) {
        return;
    }

    pendingDeleteCategory.value = row;
    isDeleteConfirmOpen.value = true;
}

function confirmDeleteCategory(): void {
    if (!props.canManage || isDeletingCategory.value) {
        return;
    }

    if (!pendingDeleteCategory.value) {
        return;
    }

    void deleteCategory(pendingDeleteCategory.value.id);
}

function closeMainDialog(): void {
    open.value = false;
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

function emitCategoriesUpdated(): void {
    emit(
        'categories-updated',
        localCategories.value.map((row) => ({
            id: row.id,
            name: row.name,
            colorKey: row.colorKey,
        })),
    );
}

async function createCategory(
    name: string,
    colorKey: CalendarCategoryColorKey,
): Promise<void> {
    if (isCreatingCategory.value) {
        return;
    }

    isCreatingCategory.value = true;

    const operation = async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch('/calendar/event-categories', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify({
                name,
                colorKey,
            }),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstErrorMessage(payload) ?? 'Unable to create category.',
            );
        }

        const data = (payload as { data?: CalendarEventCategory }).data;
        if (!data) {
            throw new Error('Unable to create category.');
        }

        localCategories.value = [...localCategories.value, data];
        emitCategoriesUpdated();
        closeCategoryFormDialog();
    };

    try {
        await appToast.promise(operation, {
            loading: 'Creating category...',
            success: 'Category created.',
            error: (error: unknown) =>
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : 'Unable to create category.',
        });
    } finally {
        isCreatingCategory.value = false;
    }
}

async function updateCategory(
    categoryId: number,
    name: string,
    colorKey: CalendarCategoryColorKey,
): Promise<void> {
    if (isUpdatingCategory.value) {
        return;
    }

    isUpdatingCategory.value = true;

    const operation = async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(
            `/calendar/event-categories/${categoryId}`,
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
                    colorKey,
                }),
            },
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(
                firstErrorMessage(payload) ?? 'Unable to update category.',
            );
        }

        const data = (payload as { data?: CalendarEventCategory }).data;
        if (!data) {
            throw new Error('Unable to update category.');
        }

        localCategories.value = localCategories.value.map((row) =>
            row.id === categoryId ? data : row,
        );
        emitCategoriesUpdated();
        closeCategoryFormDialog();
    };

    try {
        await appToast.promise(operation, {
            loading: 'Saving category...',
            success: 'Category updated.',
            error: (error: unknown) =>
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : 'Unable to update category.',
        });
    } finally {
        isUpdatingCategory.value = false;
    }
}

async function deleteCategory(categoryId: number): Promise<void> {
    if (isDeletingCategory.value) {
        return;
    }

    isDeletingCategory.value = true;

    const operation = async (): Promise<void> => {
        const csrf = resolveCsrfToken();
        const response = await fetch(
            `/calendar/event-categories/${categoryId}`,
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
                firstErrorMessage(payload) ?? 'Unable to delete category.',
            );
        }

        localCategories.value = localCategories.value.filter(
            (row) => row.id !== categoryId,
        );
        emitCategoriesUpdated();
        pendingDeleteCategory.value = null;
        isDeleteConfirmOpen.value = false;
    };

    try {
        await appToast.promise(operation, {
            loading: 'Deleting category...',
            success: 'Category deleted.',
            error: (error: unknown) =>
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : 'Unable to delete category.',
        });
    } finally {
        isDeletingCategory.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="flex max-h-[min(90vh,620px)] w-full flex-col gap-0 p-0 sm:max-w-lg"
        >
            <DialogHeader class="shrink-0 px-6 pt-6 pb-4">
                <DialogTitle>Event categories</DialogTitle>
                <DialogDescription>
                    Add, edit, or remove categories used for calendar events.
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 px-6 py-4">
                <Label class="mb-3 block text-sm font-medium text-foreground"
                    >Categories</Label
                >

                <ScrollArea
                    class="h-[min(46vh,360px)] **:data-[slot=scroll-area-viewport]:focus-visible:ring-0 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none"
                >
                    <div class="pr-3">
                        <ul class="divide-y divide-border/60">
                            <li
                                v-for="row in localCategories"
                                :key="row.id"
                                class="flex items-center gap-3 px-1 py-2 transition-colors hover:bg-accent/30"
                            >
                                <span
                                    class="size-2.5 shrink-0 rounded-full ring-1 ring-border/60"
                                    :class="
                                        calendarCategoryColorOptions.find(
                                            (option) =>
                                                option.key === row.colorKey,
                                        )?.swatchClass ?? 'bg-primary'
                                    "
                                    aria-hidden="true"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-medium text-foreground"
                                >
                                    {{ row.name }}
                                </span>
                                <div class="flex shrink-0 items-center gap-1">
                                    <Button
                                        v-if="props.canManage"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        aria-label="Edit category"
                                        :disabled="isAnyMutationPending"
                                        @click="openEditCategoryDialog(row)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        v-if="props.canManage"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-muted-foreground hover:text-destructive"
                                        aria-label="Delete category"
                                        :disabled="isAnyMutationPending"
                                        @click="askDeleteCategory(row)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
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
                    v-if="props.canManage"
                    type="button"
                    variant="default"
                    :disabled="isAnyMutationPending"
                    @click="openCreateCategoryDialog"
                >
                    <Plus class="size-4" />
                    <span>Add</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="isCategoryFormOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ formDialogTitle }}</DialogTitle>
                <DialogDescription>
                    Choose a predefined color and name your category.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-1">
                <div class="grid gap-2">
                    <Label for="calendar-category-name" :class="labelRowClass">
                        <span>Name</span>
                        <Button
                            v-if="categoryNameInput !== ''"
                            type="button"
                            variant="ghost"
                            size="icon"
                            :class="clearFieldButtonClass"
                            aria-label="Clear category name"
                            @click="categoryNameInput = ''"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </Label>
                    <Input
                        id="calendar-category-name"
                        v-model="categoryNameInput"
                        placeholder="e.g. Team Sync"
                        autocomplete="off"
                        @keydown.enter.prevent="saveCategoryForm"
                    />
                </div>

                <div class="grid gap-2">
                    <Label>Color</Label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <Button
                            v-for="option in calendarCategoryColorOptions"
                            :key="option.key"
                            type="button"
                            variant="outline"
                            class="h-10 justify-start gap-2 px-2.5"
                            :title="option.label"
                            :class="
                                cn(
                                    categoryColorKeyInput === option.key &&
                                        'border-primary bg-primary/8 text-foreground',
                                )
                            "
                            @click="categoryColorKeyInput = option.key"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full ring-1 ring-border/60"
                                :class="option.swatchClass"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ option.label }}</span>
                            <Check
                                v-if="categoryColorKeyInput === option.key"
                                class="ml-auto size-3.5 text-primary"
                                aria-hidden="true"
                            />
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Selected:
                        <span class="font-medium text-foreground">
                            {{ selectedColorOption.label }}
                        </span>
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="closeCategoryFormDialog"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="!isCategoryNameValid || isCategoryFormSubmitting"
                    @click="saveCategoryForm"
                >
                    {{ formDialogActionLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="isDeleteConfirmOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete category?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will remove
                    <span class="font-semibold text-foreground">
                        {{ pendingDeleteCategory?.name ?? 'this category' }}
                    </span>
                    from the current local list.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="isDeletingCategory"
                    @click="confirmDeleteCategory"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
