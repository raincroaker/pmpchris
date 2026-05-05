<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-9 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-2 min-w-0 flex-1 text-left">
        <span
            class="truncate text-base leading-tight font-semibold text-muted-foreground"
            >HRNexus</span
        >
    </div>
</template>
