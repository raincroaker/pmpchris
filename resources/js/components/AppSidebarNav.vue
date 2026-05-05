<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import {
    getSidebarNavOpenState,
    persistSidebarNavOpenState,
} from '@/composables/useSidebarNavOpenState';
import { appNavigationTree } from '@/config/app-navigation';
import { cn } from '@/lib/utils';
import type {
    NavHref,
    NavLeaf,
    NavTreeCollapsible,
    NavTreeEntry,
    NavTreeGroup,
    NavTreeLink,
} from '@/types';

const props = withDefaults(
    defineProps<{
        /** Wider spacing for header mobile sheet */
        density?: 'default' | 'comfortable';
    }>(),
    {
        density: 'default',
    },
);

const page = usePage();
const showHrAdminNav = computed(() =>
    Boolean((page.props as { showHrAdminNav?: boolean }).showHrAdminNav),
);
const navPermissions = computed(() => {
    const can = (
        page.props as {
            can?: {
                canAddEmployee?: boolean;
                canViewWorkSchedules?: boolean;
                canMutateWorkSchedules?: boolean;
                canManageScheduleAssignments?: boolean;
                canEditOrganizationStructure?: boolean;
                canViewEmployeeTeamLeaveOvertime?: boolean;
            };
        }
    ).can;

    return {
        canAddEmployee: Boolean(can?.canAddEmployee),
        canViewWorkSchedules: Boolean(can?.canViewWorkSchedules),
        canMutateWorkSchedules: Boolean(can?.canMutateWorkSchedules),
        canManageScheduleAssignments: Boolean(
            can?.canManageScheduleAssignments,
        ),
        canEditOrganizationStructure: Boolean(
            can?.canEditOrganizationStructure,
        ),
        canViewEmployeeTeamLeaveOvertime: Boolean(
            can?.canViewEmployeeTeamLeaveOvertime,
        ),
    };
});

function filterTree(entries: NavTreeEntry[]): NavTreeEntry[] {
    const show = showHrAdminNav.value;
    const permissions = navPermissions.value;

    const canSeeByPermission = (
        requiredPermission?:
            | 'canAddEmployee'
            | 'canViewWorkSchedules'
            | 'canManageScheduleAssignments'
            | 'canEditOrganizationStructure'
            | 'canViewEmployeeTeamLeaveOvertime',
    ): boolean => {
        if (!requiredPermission) {
            return true;
        }

        return Boolean(permissions[requiredPermission]);
    };

    return entries.flatMap((e): NavTreeEntry[] => {
        if (e.kind === 'link' && !canSeeByPermission(e.requiredPermission)) {
            return [];
        }

        if (e.kind === 'collapsible') {
            if (e.adminOnly && !show) {
                return [];
            }
            const items = e.items.filter(
                (i) =>
                    (!i.adminOnly || show) &&
                    canSeeByPermission(i.requiredPermission),
            );
            if (items.length === 0) {
                return [];
            }
            return [{ ...e, items }];
        }
        return [e];
    });
}

const navEntries = computed(() => filterTree(appNavigationTree));

type ChunkItem = NavTreeLink | NavTreeCollapsible;

type NavChunk = {
    label?: string;
    items: ChunkItem[];
};

function chunkNav(entries: NavTreeEntry[]): NavChunk[] {
    const chunks: NavChunk[] = [];
    let label: string | undefined;
    const items: ChunkItem[] = [];

    const flush = () => {
        if (items.length > 0) {
            chunks.push({ label, items: [...items] });
            items.length = 0;
        }
    };

    for (const e of entries) {
        if (e.kind === 'group') {
            flush();
            label = (e as NavTreeGroup).label;
        } else {
            items.push(e);
        }
    }
    if (items.length > 0) {
        chunks.push({ label, items: [...items] });
    }

    return chunks;
}

const chunks = computed(() => chunkNav(navEntries.value));

const { isCurrentUrl } = useCurrentUrl();

const { isMobile, state } = useSidebar();
const showSubmenuDropdown = computed(
    () => !isMobile.value && state.value === 'collapsed',
);

function resolveHref(
    hrefResolver: () => NavHref,
    label?: string,
): NavHref | '#' {
    try {
        return hrefResolver();
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn(
                `[AppSidebarNav] Failed to resolve href${label ? ` for "${label}"` : ''}.`,
                error,
            );
        }

        return '#';
    }
}

function hrefToComparableUrl(href: NavHref | '#'): string | null {
    if (typeof href === 'string') {
        return href;
    }

    if (href && typeof href === 'object' && 'url' in href) {
        const url = (href as { url?: unknown }).url;
        return typeof url === 'string' ? url : null;
    }

    return null;
}

function isResolvedCurrentUrl(
    hrefResolver: () => NavHref,
    label?: string,
): boolean {
    const href = resolveHref(hrefResolver, label);
    const comparableUrl = hrefToComparableUrl(href);

    return (
        comparableUrl !== null &&
        comparableUrl !== '#' &&
        isCurrentUrl(comparableUrl)
    );
}

function childActive(items: NavLeaf[]): boolean {
    return items.some((i) => isResolvedCurrentUrl(i.href, i.title));
}

/** Pathname only (ignores query/hash); matches {@link useCurrentUrl} resolution. */
function inertiaPagePathname(fullUrl: string): string {
    const base =
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost';
    try {
        return new URL(fullUrl, base).pathname;
    } catch {
        const beforeQuery = fullUrl.split('?')[0] ?? fullUrl;
        return beforeQuery.split('#')[0] ?? beforeQuery;
    }
}

const openState = getSidebarNavOpenState();

/** When choosing a leaf from the collapsed dropdown, mark the section open so re-expanding the sidebar shows the same submenu (not only relying on URL watcher timing). */
function markCollapsibleOpenFromDropdown(title: string) {
    openState[title] = true;
    persistSidebarNavOpenState();
}

function ensureCollapsibleStates() {
    for (const chunk of chunks.value) {
        for (const entry of chunk.items) {
            if (entry.kind === 'collapsible') {
                const title = entry.title;
                if (!(title in openState)) {
                    openState[title] = childActive(entry.items);
                }
            }
        }
    }
}

watch(
    chunks,
    () => {
        ensureCollapsibleStates();
        persistSidebarNavOpenState();
    },
    { immediate: true },
);

watch(
    () => page.url,
    (nextUrl, prevUrl) => {
        if (prevUrl !== undefined) {
            if (inertiaPagePathname(nextUrl) === inertiaPagePathname(prevUrl)) {
                return;
            }
        }
        for (const chunk of chunks.value) {
            for (const entry of chunk.items) {
                if (entry.kind === 'collapsible' && childActive(entry.items)) {
                    openState[entry.title] = true;
                }
            }
        }
        persistSidebarNavOpenState();
    },
);

const groupClass = computed(() =>
    props.density === 'comfortable' ? 'px-2 py-0' : 'px-2 py-0',
);
</script>

<template>
    <template v-for="(chunk, ci) in chunks" :key="ci">
        <SidebarGroup :class="groupClass">
            <SidebarGroupLabel v-if="chunk.label">
                {{ chunk.label }}
            </SidebarGroupLabel>
            <SidebarMenu>
                <template
                    v-for="entry in chunk.items"
                    :key="
                        entry.kind === 'link'
                            ? `link-${entry.title}`
                            : entry.title
                    "
                >
                    <SidebarMenuItem v-if="entry.kind === 'link'">
                        <SidebarMenuButton
                            as-child
                            :is-active="
                                isResolvedCurrentUrl(entry.href, entry.title)
                            "
                            :tooltip="entry.title"
                        >
                            <Link :href="resolveHref(entry.href, entry.title)">
                                <component :is="entry.icon" />
                                <span>{{ entry.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem v-else>
                        <DropdownMenu v-if="showSubmenuDropdown">
                            <DropdownMenuTrigger as-child>
                                <SidebarMenuButton
                                    class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                    :tooltip="entry.title"
                                    :is-active="childActive(entry.items)"
                                >
                                    <component :is="entry.icon" />
                                    <span class="truncate">{{
                                        entry.title
                                    }}</span>
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                class="min-w-56 rounded-lg p-2"
                                side="left"
                                align="start"
                                :side-offset="4"
                            >
                                <DropdownMenuLabel
                                    class="flex h-7 items-center px-2 py-1 text-xs font-medium text-sidebar-foreground/70"
                                >
                                    {{ entry.title }}
                                </DropdownMenuLabel>
                                <DropdownMenuItem
                                    v-for="leaf in entry.items"
                                    :key="`${entry.title}-${leaf.title}`"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            resolveHref(leaf.href, leaf.title)
                                        "
                                        @click="
                                            markCollapsibleOpenFromDropdown(
                                                entry.title,
                                            )
                                        "
                                        :class="
                                            cn(
                                                'flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-sm outline-none',
                                                isResolvedCurrentUrl(
                                                    leaf.href,
                                                    leaf.title,
                                                ) &&
                                                    'bg-accent font-medium text-accent-foreground [&>span]:translate-x-0.5',
                                            )
                                        "
                                    >
                                        <span>{{ leaf.title }}</span>
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Collapsible
                            v-else
                            class="w-full min-w-0"
                            :open="openState[entry.title] ?? false"
                            @update:open="
                                (v: boolean) => {
                                    openState[entry.title] = v;
                                    persistSidebarNavOpenState();
                                }
                            "
                        >
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton
                                    class="group/cnav"
                                    :tooltip="entry.title"
                                    :is-active="false"
                                >
                                    <component :is="entry.icon" />
                                    <span class="truncate">{{
                                        entry.title
                                    }}</span>
                                    <ChevronRight
                                        class="ml-auto size-4 shrink-0 transition-transform duration-200 group-data-[state=open]/cnav:rotate-90"
                                    />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent
                                class="overflow-hidden duration-200 ease-out [--radix-collapsible-content-height:var(--reka-collapsible-content-height)] data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down"
                            >
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem
                                        v-for="leaf in entry.items"
                                        :key="`${entry.title}-${leaf.title}`"
                                    >
                                        <SidebarMenuSubButton
                                            as-child
                                            size="md"
                                            :is-active="
                                                isResolvedCurrentUrl(
                                                    leaf.href,
                                                    leaf.title,
                                                )
                                            "
                                        >
                                            <Link
                                                :href="
                                                    resolveHref(
                                                        leaf.href,
                                                        leaf.title,
                                                    )
                                                "
                                            >
                                                <span>{{ leaf.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </Collapsible>
                    </SidebarMenuItem>
                </template>
            </SidebarMenu>
        </SidebarGroup>
    </template>
</template>
