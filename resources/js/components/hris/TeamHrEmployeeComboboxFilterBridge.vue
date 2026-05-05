<script setup lang="ts">
import { watchDebounced } from '@vueuse/core';
import { injectComboboxRootContext } from 'reka-ui';

const emit = defineEmits<{
    'update:search': [value: string];
}>();

const root = injectComboboxRootContext();

watchDebounced(
    () => root.filterSearch.value,
    (q) => {
        emit('update:search', q);
    },
    { debounce: 300 },
);
</script>

<template>
    <span class="sr-only" aria-hidden="true" />
</template>
