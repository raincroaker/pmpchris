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
    filter: EmployeeScheduleFilters['attendance_id_filter'];
}>();

const emit = defineEmits<{
    'update:filter': [value: EmployeeScheduleFilters['attendance_id_filter']];
}>();

const open = ref(false);

function select(value: EmployeeScheduleFilters['attendance_id_filter']): void {
    emit('update:filter', value);
}
</script>

<template>
    <div class="flex min-w-0 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            Attendance ID
        </span>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                    aria-label="Filter by attendance ID presence"
                >
                    <ListFilter class="size-4" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <Button
                v-if="filter !== null"
                type="button"
                variant="ghost"
                class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                aria-label="Clear attendance ID filter"
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
                    Presence
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{
                        'bg-muted/60 text-foreground': filter === 'has',
                    }"
                    aria-label="Show employees with an attendance ID"
                    @click="select('has')"
                >
                    <span :class="filter === 'has' ? 'font-medium' : undefined"
                        >Has</span
                    >
                    <Check
                        v-if="filter === 'has'"
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{
                        'bg-muted/60 text-foreground': filter === 'missing',
                    }"
                    aria-label="Show employees without an attendance ID"
                    @click="select('missing')"
                >
                    <span
                        :class="
                            filter === 'missing' ? 'font-medium' : undefined
                        "
                    >
                        Missing
                    </span>
                    <Check
                        v-if="filter === 'missing'"
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </Button>
            </PopoverContent>
        </Popover>
    </div>
</template>
