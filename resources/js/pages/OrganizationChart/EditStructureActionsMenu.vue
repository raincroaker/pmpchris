<script setup lang="ts">
import {
    CircleSlash,
    Eye,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { EditStructureRow } from '@/pages/OrganizationChart/editStructureTypes';

defineProps<{
    row: EditStructureRow;
}>();

const emit = defineEmits<{
    viewUnits: [row: EditStructureRow];
    edit: [row: EditStructureRow];
    deactivate: [row: EditStructureRow];
    delete: [row: EditStructureRow];
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
                    :aria-label="`Actions for unit type ${row.name}`"
                >
                    <MoreHorizontal class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-52">
                <DropdownMenuItem @click="emit('viewUnits', row)">
                    <Eye class="size-4" aria-hidden="true" />
                    View units
                </DropdownMenuItem>
                <DropdownMenuItem @click="emit('edit', row)">
                    <Pencil class="size-4" aria-hidden="true" />
                    Edit type
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    :disabled="!row.is_active"
                    @click="emit('deactivate', row)"
                >
                    <CircleSlash
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    Deactivate
                </DropdownMenuItem>
                <DropdownMenuItem
                    variant="destructive"
                    :disabled="row.units_total_count > 0"
                    :title="
                        row.units_total_count > 0
                            ? 'Remove all organizational units that use this type before deleting.'
                            : undefined
                    "
                    @click="emit('delete', row)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Delete permanently
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
