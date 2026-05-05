<script setup lang="ts">
import { Check, ListFilter, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { EmployeeScheduleFilters } from '@/pages/Attendance/employeeScheduleIndexTypes';

defineProps<{
    filter: EmployeeScheduleFilters['work_schedule_filter'];
}>();

const emit = defineEmits<{
    'update:filter': [value: EmployeeScheduleFilters['work_schedule_filter']];
}>();

const open = ref(false);

function select(value: EmployeeScheduleFilters['work_schedule_filter']): void {
    emit('update:filter', value);
}
</script>

<template>
    <div class="flex min-w-0 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            Work Schedule
        </span>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                    aria-label="Filter by work schedule status"
                >
                    <ListFilter class="size-4" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <Button
                v-if="filter !== null"
                type="button"
                variant="ghost"
                class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                aria-label="Clear work schedule filter"
                @click="select(null)"
            >
                <X class="size-4" aria-hidden="true" />
            </Button>
            <PopoverContent
                align="start"
                side="bottom"
                class="w-auto min-w-48 p-2"
            >
                <p class="mb-2 px-1 text-xs font-medium text-muted-foreground">
                    Schedule status
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{
                        'bg-muted/60 text-foreground': filter === 'assigned',
                    }"
                    aria-label="Show employees with a work schedule template"
                    @click="select('assigned')"
                >
                    <span
                        :class="
                            filter === 'assigned' ? 'font-medium' : undefined
                        "
                    >
                        Assigned
                    </span>
                    <Check
                        v-if="filter === 'assigned'"
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{
                        'bg-muted/60 text-foreground': filter === 'unassigned',
                    }"
                    aria-label="Show employees without a work schedule template"
                    @click="select('unassigned')"
                >
                    <span
                        :class="
                            filter === 'unassigned' ? 'font-medium' : undefined
                        "
                    >
                        Unassigned
                    </span>
                    <Check
                        v-if="filter === 'unassigned'"
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </Button>
            </PopoverContent>
        </Popover>
    </div>
</template>
