<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const auth = computed(() => page.props.auth);

const dateTime = ref('');
const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'medium',
});

function updateDateTime() {
    dateTime.value = dateTimeFormatter.format(new Date());
}

let intervalId: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    updateDateTime();
    intervalId = setInterval(updateDateTime, 1000);
});
onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});
</script>

<template>
    <header
        class="fixed top-0 right-0 left-0 z-10 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background px-6 transition-[width,height,left] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:left-(--sidebar-width) md:px-4 group-has-data-[state=collapsed]/sidebar-wrapper:md:left-(--sidebar-width-icon)"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1 shrink-0" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto flex shrink-0 items-center gap-2">
            <span
                class="hidden font-sans text-sm text-muted-foreground tabular-nums sm:inline"
            >
                {{ dateTime }}
            </span>
            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <Button
                        variant="ghost"
                        class="flex h-9 cursor-pointer items-center gap-2 rounded-4xl border border-primary/70 px-3 font-bold shadow-sm focus-visible:ring-2 focus-visible:ring-primary data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        aria-label="User menu"
                    >
                        <span class="ml-1.5">{{ auth.user?.name }}</span>
                        <ChevronDown class="size-4 shrink-0 opacity-70" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="auth.user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
