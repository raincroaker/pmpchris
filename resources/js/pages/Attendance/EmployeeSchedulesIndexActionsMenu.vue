<script setup lang="ts">
import { Eye, MoreHorizontal, Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { EmployeeScheduleRow } from '@/pages/Attendance/employeeScheduleIndexTypes';

defineProps<{
    row: EmployeeScheduleRow;
}>();

defineEmits<{
    view: [row: EmployeeScheduleRow];
    edit: [row: EmployeeScheduleRow];
}>();
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
                    :aria-label="`Actions for ${row.display_name}`"
                >
                    <MoreHorizontal class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-48">
                <DropdownMenuItem @click="$emit('view', row)">
                    <Eye class="size-4 shrink-0" aria-hidden="true" />
                    View
                </DropdownMenuItem>
                <DropdownMenuItem @click="$emit('edit', row)">
                    <Pencil class="size-4 shrink-0" aria-hidden="true" />
                    Edit
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
