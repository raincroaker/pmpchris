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
import type { OvertimePolicy } from '@/pages/Overtime/overtimePolicyTypes';

const props = defineProps<{
    row: OvertimePolicy;
    canMutate: boolean | ComputedRef<boolean>;
}>();

const emit = defineEmits<{
    view: [row: OvertimePolicy];
    edit: [row: OvertimePolicy];
    delete: [row: OvertimePolicy];
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
                    :aria-label="`Actions for Overtime Policy ${row.name}`"
                >
                    <MoreHorizontal class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-44">
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
                        @click="emit('delete', row)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Delete
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
