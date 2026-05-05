<script setup lang="ts">
import { computed } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import type { EmployeeUnitFilterOption } from '@/pages/Employees/employeeIndexTypes';

const props = defineProps<{
    modelValue: number | 'unassigned' | null;
    options: EmployeeUnitFilterOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | 'unassigned' | null];
}>();

const selectedOption = computed(() =>
    props.modelValue === null || props.modelValue === 'unassigned'
        ? null
        : (props.options.find((u) => u.id === props.modelValue) ?? null),
);

const triggerAriaLabel = computed(() => {
    if (selectedOption.value === null) {
        if (props.modelValue === 'unassigned') {
            return 'Units filter: unassigned employees in this branch.';
        }

        return 'Units filter: all units. Open to select a specific unit.';
    }

    return `Units filter: ${selectedOption.value.name}. Open to change selected unit.`;
});

const filterOptions = computed(() => [
    {
        value: 'unassigned',
        label: 'Unassigned',
        secondary: 'No active unit in this branch',
        searchText: 'unassigned no active unit branch',
    },
    ...props.options.map((unit) => ({
        value: unit.id,
        label: unit.name,
        secondary: `${unit.unit_type} • ${unit.code}`,
        searchText: `${unit.name} ${unit.unit_type} ${unit.code}`,
    })),
]);
</script>

<template>
    <HrisColumnFilterPopover
        label="Units"
        :trigger-aria-label="triggerAriaLabel"
        :model-value="modelValue"
        :options="filterOptions"
        :is-active="modelValue !== null"
        :show-clear="modelValue !== null"
        clear-aria-label="Clear unit filter"
        all-label="All units"
        search-placeholder="Search unit by name or code..."
        empty-text="No matching units."
        @update:model-value="
            (value) =>
                emit('update:modelValue', value as number | 'unassigned' | null)
        "
        @clear="emit('update:modelValue', null)"
    />
</template>
