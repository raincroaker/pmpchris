<script setup lang="ts">
import { Ban, MoreHorizontal, Pencil, Trash2, Users } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PositionRow } from '@/pages/Positions/positionIndexTypes';

defineProps<{
    row: PositionRow;
}>();

const emit = defineEmits<{
    edit: [row: PositionRow];
    delete: [row: PositionRow];
    setInactive: [row: PositionRow];
    showEmployees: [row: PositionRow];
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
                    :aria-label="`Actions for position ${row.code}`"
                >
                    <MoreHorizontal class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-52">
                <DropdownMenuItem @click="emit('edit', row)">
                    <Pencil class="size-4" aria-hidden="true" />
                    Edit
                </DropdownMenuItem>
                <DropdownMenuItem @click="emit('showEmployees', row)">
                    <Users class="size-4 shrink-0" aria-hidden="true" />
                    Employees
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    v-if="row.is_active"
                    @click="emit('setInactive', row)"
                >
                    <Ban class="size-4" aria-hidden="true" />
                    Set Inactive
                </DropdownMenuItem>
                <DropdownMenuItem
                    variant="destructive"
                    @click="emit('delete', row)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
