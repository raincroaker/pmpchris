<script setup lang="ts">
import { computed } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import type { ShiftsSchedulePatternFilter } from '@/pages/Attendance/shiftsIndexFilters';

const props = defineProps<{
    modelValue: ShiftsSchedulePatternFilter;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: ShiftsSchedulePatternFilter];
}>();

const labels: Record<ShiftsSchedulePatternFilter, string> = {
    all: 'All',
    split_sessions: 'Split sessions',
    single_overnight: 'Overnight',
    single_day: 'Single window',
};

const triggerAriaLabel = computed(
    () =>
        `Schedule filter: ${labels[props.modelValue]}. Open to choose pattern type.`,
);

const filterOptions = computed<
    {
        value: ShiftsSchedulePatternFilter;
        label: string;
        secondary?: string;
    }[]
>(() => [
    { value: 'all', label: 'All schedules' },
    {
        value: 'single_day',
        label: labels.single_day,
        secondary: 'One time in / time out same day',
    },
    {
        value: 'single_overnight',
        label: labels.single_overnight,
        secondary: 'Spans midnight',
    },
    {
        value: 'split_sessions',
        label: labels.split_sessions,
        secondary: 'Two or three same-day sessions',
    },
]);
</script>

<template>
    <HrisColumnFilterPopover
        label="Schedule"
        :trigger-aria-label="triggerAriaLabel"
        :model-value="modelValue"
        :options="filterOptions"
        :is-active="modelValue !== 'all'"
        :searchable="false"
        :show-all-option="false"
        :show-check-icon="false"
        content-class="w-auto min-w-52 p-2"
        @update:model-value="
            (value) =>
                emit(
                    'update:modelValue',
                    String(value ?? 'all') as ShiftsSchedulePatternFilter,
                )
        "
    />
</template>
