<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useEventListener, useThrottleFn } from '@vueuse/core';
import type { Ref } from 'vue';
import { computed, nextTick, onUnmounted, ref, unref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppSidebarNav from '@/components/AppSidebarNav.vue';
import BranchSwitcher from '@/components/BranchSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    clampSidebarScrollTop,
    loadSidebarScrollTop,
    saveSidebarScrollTop,
} from '@/composables/useSidebarScrollState';
import { dashboard } from '@/routes';

type SidebarContentExpose = {
    scrollEl: Ref<HTMLDivElement | null>;
};

const sidebarContentRef = ref<SidebarContentExpose | null>(null);
const { isMobile } = useSidebar();

const page = usePage();
const branchContext = computed(
    () =>
        page.props.branchContext as
            | { id: number; code: string; name: string }
            | null
            | undefined,
);
const showBranchSwitcher = computed(() => branchContext.value != null);

/** Hides nav scroll area until saved scroll is applied (avoids top-then-jump flash). */
const hideSidebarContentUntilScrollRestored = ref(false);

const scrollContainerEl = computed(() => {
    const inst = sidebarContentRef.value;
    if (!inst?.scrollEl) {
        return null;
    }
    return unref(inst.scrollEl);
});

function restoreSidebarScroll(): void {
    const el = scrollContainerEl.value;
    if (!el) {
        return;
    }
    const saved = loadSidebarScrollTop(isMobile.value);
    if (saved === null) {
        return;
    }
    el.scrollTop = clampSidebarScrollTop(el, saved);
}

function scheduleRestoreSidebarScroll(): void {
    void nextTick(() => {
        requestAnimationFrame(() => {
            restoreSidebarScroll();
            hideSidebarContentUntilScrollRestored.value = false;
            requestAnimationFrame(() => {
                restoreSidebarScroll();
            });
        });
    });
}

const persistSidebarScrollThrottled = useThrottleFn(() => {
    const el = scrollContainerEl.value;
    if (!el) {
        return;
    }
    saveSidebarScrollTop(isMobile.value, el.scrollTop);
}, 100);

let stopScrollListener: (() => void) | undefined;

watch(
    scrollContainerEl,
    (el) => {
        stopScrollListener?.();
        stopScrollListener = undefined;
        if (!el) {
            hideSidebarContentUntilScrollRestored.value = false;
            return;
        }
        hideSidebarContentUntilScrollRestored.value =
            loadSidebarScrollTop(isMobile.value) !== null;
        stopScrollListener = useEventListener(
            el,
            'scroll',
            persistSidebarScrollThrottled,
            {
                passive: true,
            },
        );
        scheduleRestoreSidebarScroll();
    },
    { flush: 'post' },
);

watch(isMobile, () => {
    const el = scrollContainerEl.value;
    if (el && loadSidebarScrollTop(isMobile.value) !== null) {
        hideSidebarContentUntilScrollRestored.value = true;
    }
    scheduleRestoreSidebarScroll();
});

onUnmounted(() => {
    stopScrollListener?.();
});
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent
            ref="sidebarContentRef"
            :class="{
                'pointer-events-none opacity-0':
                    hideSidebarContentUntilScrollRestored,
            }"
        >
            <AppSidebarNav />
        </SidebarContent>

        <SidebarFooter
            v-if="showBranchSwitcher"
            class="border-t border-sidebar-border/70 shadow-[0_-2px_6px_-1px_rgba(0,0,0,0.06)] dark:shadow-[0_-2px_8px_-2px_rgba(0,0,0,0.25)]"
        >
            <BranchSwitcher />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
