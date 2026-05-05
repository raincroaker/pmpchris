<script setup lang="ts">
import { computed } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import type { TeamHrRequestStatusFilter } from '@/lib/teamHrRequestStatusFilter';
import { TEAM_HR_REQUEST_STATUS_LABELS } from '@/lib/teamHrRequestStatusFilter';

const props = defineProps<{
    modelValue: TeamHrRequestStatusFilter;
    /** Screen reader context, e.g. "Leave requests" or "Overtime requests". */
    scopeLabel: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: TeamHrRequestStatusFilter];
}>();

const labels = TEAM_HR_REQUEST_STATUS_LABELS;

const triggerAriaLabel = computed(
    () =>
        `Status filter for ${props.scopeLabel}: ${labels[props.modelValue]}. Open to choose approved, rejected, or all.`,
);

const filterOptions = computed(() =>
    (['all', 'approved', 'rejected'] as TeamHrRequestStatusFilter[]).map(
        (value) => ({
            value,
            label: labels[value],
        }),
    ),
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
                    String(value ?? 'all') as TeamHrRequestStatusFilter,
                )
        "
    />
</template>
