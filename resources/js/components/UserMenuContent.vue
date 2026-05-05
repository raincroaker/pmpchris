<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { GitBranch, LogOut, Settings } from 'lucide-vue-next';
import { computed } from 'vue';
import BranchContextController from '@/actions/App/Http/Controllers/BranchContextController';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { appToast } from '@/lib/app-toast-client';
import { createLogoutFinishedPromise } from '@/lib/logout-toast-promise';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

let loggingOut = false;

const handleLogout = () => {
    if (loggingOut) {
        return;
    }
    loggingOut = true;
    router.flushAll();
    const p = createLogoutFinishedPromise().finally(() => {
        loggingOut = false;
    });
    appToast.promise(p, {
        loading: 'Logging out...',
        success: 'Logged out',
        error: 'Logout failed',
    });
};

defineProps<Props>();

const page = usePage();
const branchContext = computed(
    () =>
        page.props.branchContext as
            | { id: number; code: string; name: string }
            | null
            | undefined,
);
const showSwitchBranch = computed(() => branchContext.value != null);
const canSwitchBranches = computed(() => Boolean(page.props.canSwitchBranches));
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem
            v-if="showSwitchBranch && canSwitchBranches"
            :as-child="true"
        >
            <Link
                class="block w-full cursor-pointer"
                :href="BranchContextController.create.url()"
                prefetch
            >
                <GitBranch class="mr-2 h-4 w-4" />
                Switch branch
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true" variant="destructive">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
