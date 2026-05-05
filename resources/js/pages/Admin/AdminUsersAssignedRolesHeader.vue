<script setup lang="ts">
import { computed } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import type { AdminRoleFilterOption } from '@/pages/Admin/adminUsersTypes';

const props = defineProps<{
    modelValue: number | null;
    options: AdminRoleFilterOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const selectedOption = computed(() =>
    props.modelValue === null
        ? null
        : (props.options.find((role) => role.id === props.modelValue) ?? null),
);

const triggerAriaLabel = computed(() => {
    if (selectedOption.value === null) {
        return 'Role filter: all roles. Open to select a specific role.';
    }

    return `Role filter: ${selectedOption.value.name}. Open to change selected role.`;
});

const filterOptions = computed(() =>
    props.options.map((role) => ({
        value: role.id,
        label: role.name,
        secondary: role.code,
        searchText: `${role.name} ${role.code}`,
    })),
);
</script>

<template>
    <HrisColumnFilterPopover
        label="Assigned roles"
        :trigger-aria-label="triggerAriaLabel"
        :model-value="modelValue"
        :options="filterOptions"
        :is-active="modelValue !== null"
        :show-clear="modelValue !== null"
        clear-aria-label="Clear assigned role filter"
        all-label="All roles"
        search-placeholder="Search role by name or code..."
        empty-text="No matching roles."
        @update:model-value="
            (value) => emit('update:modelValue', value as number | null)
        "
        @clear="emit('update:modelValue', null)"
    />
</template>
