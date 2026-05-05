<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Building2, ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import BranchContextController from '@/actions/App/Http/Controllers/BranchContextController';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { appToast } from '@/lib/app-toast-client';
import { branchPickerGroups } from '@/lib/branch-picker-groups';
import { cn } from '@/lib/utils';
import type { Branch } from '@/types';

const page = usePage();
const { isMobile, state } = useSidebar();

const branchContext = computed(
    () =>
        page.props.branchContext as
            | { id: number; code: string; name: string }
            | null
            | undefined,
);

const branches = computed(
    () => (page.props.switchableBranches ?? []) as Branch[],
);
const canSwitchBranches = computed(() => Boolean(page.props.canSwitchBranches));
const displayBranches = computed<Branch[]>(() => {
    if (branches.value.length > 0) {
        return branches.value;
    }

    const ctx = branchContext.value;
    if (ctx === null || ctx === undefined) {
        return [];
    }

    return [
        {
            id: ctx.id,
            code: ctx.code,
            name: ctx.name,
        },
    ];
});

const branchGroups = computed(() => branchPickerGroups(displayBranches.value));

const form = useForm({
    branch_id: null as number | null,
    return_to: '',
});

function currentReturnTo(): string {
    const url = page.url;
    if (url === '/select-branch' || url.startsWith('/select-branch?')) {
        return '';
    }
    return url.startsWith('/') ? url : `/${url}`;
}

function branchLabel(branch: Branch): string {
    return branch.code ? `${branch.name} (${branch.code})` : branch.name;
}

function branchMenuItemClass(branch: Branch): string {
    const ctx = branchContext.value;
    const isActive = ctx !== null && ctx !== undefined && branch.id === ctx.id;

    return cn(
        'flex w-full items-center gap-2',
        isActive &&
            'bg-sidebar-accent font-medium text-sidebar-accent-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus:bg-sidebar-accent focus:text-sidebar-accent-foreground',
    );
}

function selectBranch(branch: Branch): void {
    if (!canSwitchBranches.value) {
        return;
    }

    const ctx = branchContext.value;
    if (ctx === null || ctx === undefined || branch.id === ctx.id) {
        return;
    }
    if (form.processing) {
        return;
    }

    const description = branchLabel(branch);
    const loadingToastId = appToast.loading('Switching branch…', {
        description,
    });

    let switchSucceeded = false;
    let genericErrorAfterFinish = false;

    form.branch_id = branch.id;
    form.return_to = currentReturnTo();
    form.post(BranchContextController.store.url(), {
        onSuccess: () => {
            switchSucceeded = true;
        },
        onError: (errors) => {
            if (!errors.branch_id) {
                genericErrorAfterFinish = true;
            }
        },
        onFinish: () => {
            appToast.dismiss(loadingToastId);
            if (switchSucceeded) {
                switchSucceeded = false;
                appToast.success('Branch selected', {
                    description,
                });
                return;
            }
            if (genericErrorAfterFinish) {
                genericErrorAfterFinish = false;
                appToast.error('Could not switch branch');
            }
        },
    });
}
</script>

<template>
    <SidebarMenu v-if="branchContext">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        :tooltip="branchContext.name"
                        data-test="sidebar-branch-button"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-sidebar-accent"
                        >
                            <Building2
                                class="size-5 text-sidebar-accent-foreground"
                            />
                        </span>
                        <div
                            class="flex min-w-0 flex-1 items-center justify-start gap-2 text-left"
                        >
                            <span class="min-w-0 shrink truncate font-bold">
                                {{ branchContext.name }}
                            </span>
                            <Badge
                                v-if="branchContext.code"
                                variant="outline"
                                class="h-5 shrink-0 border-foreground/50 px-1.5 text-[10px] dark:border-foreground/60"
                            >
                                {{ branchContext.code }}
                            </Badge>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4 shrink-0" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="max-h-[min(28rem,var(--reka-dropdown-menu-content-available-height))] w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg p-2"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <template v-for="group in branchGroups" :key="group.label">
                        <DropdownMenuLabel
                            class="flex h-7 items-center px-2 py-1 text-xs font-medium text-sidebar-foreground/70"
                        >
                            {{ group.label }}
                        </DropdownMenuLabel>
                        <DropdownMenuItem
                            v-for="branch in group.branches"
                            :key="branch.id"
                            :class="branchMenuItemClass(branch)"
                            :disabled="form.processing"
                            :aria-current="
                                branch.id === branchContext.id
                                    ? 'location'
                                    : undefined
                            "
                            @select="() => selectBranch(branch)"
                        >
                            <div
                                class="flex min-w-0 flex-1 items-center justify-start gap-2"
                            >
                                <span class="min-w-0 shrink truncate">
                                    {{ branch.name }}
                                </span>
                                <Badge
                                    v-if="branch.code"
                                    variant="outline"
                                    class="h-5 shrink-0 border-foreground/50 px-1.5 text-[10px] dark:border-foreground/60"
                                >
                                    {{ branch.code }}
                                </Badge>
                            </div>
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
