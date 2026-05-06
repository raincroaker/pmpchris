<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, UserX } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import AdjustEmploymentDatesDialog from '@/pages/Employees/AdjustEmploymentDatesDialog.vue';
import type {
    EmploymentHistoryDialogMode,
    EmploymentHistoryRow,
} from '@/pages/Employees/employmentHistoryTypes';
import { show } from '@/routes/employees';

const props = defineProps<{
    row: EmploymentHistoryRow;
}>();

const employmentActionsEnabled = computed(
    (): boolean => props.row.employment_status === 'active',
);

const page = usePage();
const canRecordEmploymentSeparation = computed((): boolean =>
    Boolean(
        (
            page.props as {
                can?: { canRecordEmploymentSeparation?: boolean };
            }
        ).can?.canRecordEmploymentSeparation,
    ),
);

const employmentDialogOpen = ref(false);
const employmentDialogMode = ref<EmploymentHistoryDialogMode>('adjust_dates');

function openEmployee(row: EmploymentHistoryRow): void {
    router.get(show.url(row.employee.id));
}

function openAdjustDialog(): void {
    employmentDialogMode.value = 'adjust_dates';
    employmentDialogOpen.value = true;
}

function openRecordSeparationDialog(): void {
    employmentDialogMode.value = 'record_separation';
    employmentDialogOpen.value = true;
}
</script>

<template>
    <div class="flex justify-center">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :aria-label="`Actions for ${row.employee.display_name}`"
                >
                    <MoreHorizontal class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-52">
                <DropdownMenuItem @click="openEmployee(row)">
                    <Eye class="size-4" aria-hidden="true" />
                    View employee profile
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    :disabled="!employmentActionsEnabled"
                    :class="
                        cn(
                            employmentActionsEnabled && 'text-foreground',
                            !employmentActionsEnabled &&
                                'text-muted-foreground',
                        )
                    "
                    @click="openAdjustDialog"
                >
                    <Pencil class="size-4" aria-hidden="true" />
                    Adjust employment dates
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="canRecordEmploymentSeparation"
                    :disabled="!employmentActionsEnabled"
                    :class="
                        cn(
                            employmentActionsEnabled &&
                                'text-amber-900 focus:bg-amber-100 focus:text-amber-950 dark:text-amber-200 dark:focus:bg-amber-950/50 dark:focus:text-amber-50',
                            !employmentActionsEnabled &&
                                'text-muted-foreground',
                        )
                    "
                    @click="openRecordSeparationDialog"
                >
                    <UserX class="size-4" aria-hidden="true" />
                    Record separation
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <AdjustEmploymentDatesDialog
            v-model:open="employmentDialogOpen"
            :mode="employmentDialogMode"
            :row="row"
        />
    </div>
</template>
