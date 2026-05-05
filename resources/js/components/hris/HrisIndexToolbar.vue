<script setup lang="ts">
/**
 * Index-page toolbar row: primary controls (e.g. search) on the left, actions on the right.
 * Matches responsive layout used on Positions index.
 */
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <slot name="start" />
        <slot name="end" />
    </div>
</template>
