<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, UserX } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { EmployeeRow } from '@/pages/Employees/employeeIndexTypes';
import { show } from '@/routes/employees';

defineProps<{
    row: EmployeeRow;
}>();

function openEmployee(row: EmployeeRow): void {
    router.get(show.url(row.id));
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
                    :aria-label="`Actions for ${row.display_name}`"
                >
                    <MoreHorizontal class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-52">
                <DropdownMenuItem @click="openEmployee(row)">
                    <Eye class="size-4" aria-hidden="true" />
                    View information
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem disabled>
                    <Pencil class="size-4" aria-hidden="true" />
                    Edit
                </DropdownMenuItem>
                <DropdownMenuItem disabled>
                    <UserX class="size-4" aria-hidden="true" />
                    End employment
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
