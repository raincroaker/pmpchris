<script setup lang="ts">
import { computed } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import type { PositionFilters } from '@/pages/Positions/positionIndexTypes';

const props = defineProps<{
    modelValue: PositionFilters['status'];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: PositionFilters['status']];
}>();

const statusOptions: { value: PositionFilters['status']; label: string }[] = [
    { value: 'active', label: 'Active' },
    { value: 'all', label: 'All' },
    { value: 'inactive', label: 'Inactive' },
];

const statusLabels: Record<PositionFilters['status'], string> = {
    active: 'Active',
    all: 'All',
    inactive: 'Inactive',
};

const triggerAriaLabel = computed(
    () =>
        `Status filter: ${statusLabels[props.modelValue]}. Open to choose active, all, or inactive positions.`,
);

const filterOptions = computed(() =>
    statusOptions.map((opt) => ({
        value: opt.value,
        label: opt.label,
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
                emit('update:modelValue', value as PositionFilters['status'])
        "
    />
</template>
