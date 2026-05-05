<script setup lang="ts">
import { computed } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import type { EmployeePositionFilterOption } from '@/pages/Employees/employeeIndexTypes';

const props = defineProps<{
    modelValue: number | null;
    options: EmployeePositionFilterOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const selectedOption = computed(() =>
    props.modelValue === null
        ? null
        : (props.options.find((p) => p.id === props.modelValue) ?? null),
);

const triggerAriaLabel = computed(() => {
    if (selectedOption.value === null) {
        return 'Position filter: all positions. Open to select a specific position.';
    }

    return `Position filter: ${selectedOption.value.title}. Open to change selected position.`;
});

const filterOptions = computed(() =>
    props.options.map((position) => ({
        value: position.id,
        label: position.title,
        secondary: position.code,
        searchText: `${position.title} ${position.code}`,
    })),
);
</script>

<template>
    <HrisColumnFilterPopover
        label="Position"
        :trigger-aria-label="triggerAriaLabel"
        :model-value="modelValue"
        :options="filterOptions"
        :is-active="modelValue !== null"
        :show-clear="modelValue !== null"
        clear-aria-label="Clear position filter"
        all-label="All positions"
        search-placeholder="Search position by title or code..."
        empty-text="No matching positions."
        @update:model-value="
            (value) => emit('update:modelValue', value as number | null)
        "
        @clear="emit('update:modelValue', null)"
    />
</template>
