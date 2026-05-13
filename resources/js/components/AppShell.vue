<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { appToast } from '@/lib/app-toast-client';
import type { AppShellVariant } from '@/types';

type Props = {
    variant?: AppShellVariant;
};

defineProps<Props>();

const page = usePage();

const isOpen = page.props.sidebarOpen;

watch(
    () => page.props.flash,
    (flash) => {
        if (flash === null || flash === undefined) {
            return;
        }
        if (typeof flash !== 'object') {
            return;
        }
        const record = flash as Record<string, unknown>;
        const success = record.success;
        if (typeof success === 'string' && success.trim() !== '') {
            appToast.success(success);
        }
        const error = record.error;
        if (typeof error === 'string' && error.trim() !== '') {
            appToast.error(error);
        }
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <slot />
    </SidebarProvider>
</template>
