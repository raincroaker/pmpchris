<script setup lang="ts">
import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next';
import type { ComputedRef } from 'vue';
import { toValue } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { TeamLeaveRow } from '@/pages/Leave/teamLeaveTypes';

const props = defineProps<{
    row: TeamLeaveRow;
    canMutate: boolean | ComputedRef<boolean>;
}>();

const emit = defineEmits<{
    view: [row: TeamLeaveRow];
    edit: [row: TeamLeaveRow];
    remove: [row: TeamLeaveRow];
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
                    :aria-label="`Actions for leave record ${row.employee.display_name}`"
                >
                    <MoreHorizontal class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-48">
                <DropdownMenuItem @click="emit('view', row)">
                    <Eye class="size-4" aria-hidden="true" />
                    View
                </DropdownMenuItem>
                <template v-if="toValue(props.canMutate)">
                    <DropdownMenuItem @click="emit('edit', row)">
                        <Pencil class="size-4" aria-hidden="true" />
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        @click="emit('remove', row)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Delete
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
