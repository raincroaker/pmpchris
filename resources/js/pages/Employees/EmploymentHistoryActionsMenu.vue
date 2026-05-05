<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, UserX } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { EmploymentHistoryRow } from '@/pages/Employees/employmentHistoryTypes';
import { show } from '@/routes/employees';

defineProps<{
    row: EmploymentHistoryRow;
}>();

function openEmployee(row: EmploymentHistoryRow): void {
    router.get(show.url(row.employee.id));
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
                    Open employee profile
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem disabled>
                    <UserX class="size-4" aria-hidden="true" />
                    Record separation
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
