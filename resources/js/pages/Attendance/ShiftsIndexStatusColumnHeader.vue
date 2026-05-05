<script setup lang="ts">
import { computed } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import type { ShiftsStatusFilter } from '@/pages/Attendance/shiftsIndexFilters';

const props = defineProps<{
    modelValue: ShiftsStatusFilter;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: ShiftsStatusFilter];
}>();

const labels: Record<ShiftsStatusFilter, string> = {
    all: 'All',
    active: 'Active',
    inactive: 'Inactive',
};

const triggerAriaLabel = computed(
    () =>
        `Status filter: ${labels[props.modelValue]}. Open to choose active, inactive, or all schedules.`,
);

const filterOptions = computed(() =>
    (['all', 'active', 'inactive'] as const).map((value) => ({
        value,
        label: labels[value],
    })),
);
</script>

<template>
    <HrisColumnFilterPopover
        label="Status"
        :trigger-aria-label="triggerAriaLabel"
        :model-value="modelValue"
        :options="filterOptions"
        :is-active="modelValue !== 'all'"
        :searchable="false"
        :show-all-option="false"
        :show-check-icon="false"
        content-class="w-auto min-w-48 p-2"
        @update:model-value="
            (value) =>
                emit(
                    'update:modelValue',
                    String(value ?? 'all') as ShiftsStatusFilter,
                )
        "
    />
</template>
