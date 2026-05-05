<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Check,
    ListFilter,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { AdminRoleFilters } from '@/pages/Admin/adminUsersTypes';

const props = defineProps<{
    sort: AdminRoleFilters['sort'];
    direction: AdminRoleFilters['direction'];
    orgScope: AdminRoleFilters['org_scope'];
}>();

const emit = defineEmits<{
    sortBy: [field: 'name' | 'code'];
    'update:orgScope': [value: AdminRoleFilters['org_scope']];
}>();

const open = ref(false);

const nameSortIcon = computed(() =>
    props.sort !== 'name'
        ? ArrowUpDown
        : props.direction === 'asc'
          ? ArrowUp
          : ArrowDown,
);

const emailSortIcon = computed(() =>
    props.sort !== 'code'
        ? ArrowUpDown
        : props.direction === 'asc'
          ? ArrowUp
          : ArrowDown,
);

function choose(field: 'name' | 'code'): void {
    emit('sortBy', field);
    open.value = false;
}

function chooseOrgScope(scope: AdminRoleFilters['org_scope']): void {
    emit('update:orgScope', scope);
}

function isOrgScopeActive(scope: AdminRoleFilters['org_scope']): boolean {
    return props.orgScope === scope;
}
</script>

<template>
    <div class="flex min-w-0 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            User
        </span>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                    aria-label="Open filter: sort by user name or email"
                >
                    <ListFilter class="size-4" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <Button
                v-if="orgScope !== null"
                type="button"
                variant="ghost"
                class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                aria-label="Clear scope filter"
                @click="chooseOrgScope(null)"
            >
                <X class="size-4" aria-hidden="true" />
            </Button>
            <PopoverContent
                align="start"
                side="bottom"
                class="w-auto min-w-48 p-2"
            >
                <p class="mb-2 px-1 text-xs font-medium text-muted-foreground">
                    Filter
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{ 'bg-muted/60 text-foreground': sort === 'name' }"
                    @click="choose('name')"
                >
                    <span :class="sort === 'name' ? 'font-medium' : undefined">
                        Name
                    </span>
                    <component
                        :is="nameSortIcon"
                        :class="[
                            'size-4 shrink-0',
                            sort === 'name'
                                ? 'text-primary'
                                : 'text-muted-foreground',
                        ]"
                        aria-hidden="true"
                    />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{ 'bg-muted/60 text-foreground': sort === 'code' }"
                    @click="choose('code')"
                >
                    <span :class="sort === 'code' ? 'font-medium' : undefined">
                        Email
                    </span>
                    <component
                        :is="emailSortIcon"
                        :class="[
                            'size-4 shrink-0',
                            sort === 'code'
                                ? 'text-primary'
                                : 'text-muted-foreground',
                        ]"
                        aria-hidden="true"
                    />
                </Button>
                <p
                    class="mt-3 mb-2 px-1 text-xs font-medium text-muted-foreground"
                >
                    Scope
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{
                        'bg-muted/60 text-foreground': orgScope === 'org_wide',
                    }"
                    aria-label="Filter org-wide users"
                    @click="chooseOrgScope('org_wide')"
                >
                    <span
                        :class="
                            isOrgScopeActive('org_wide')
                                ? 'font-medium'
                                : undefined
                        "
                    >
                        Org-wide
                    </span>
                    <Check
                        v-if="isOrgScopeActive('org_wide')"
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{
                        'bg-muted/60 text-foreground':
                            orgScope === 'not_org_wide',
                    }"
                    aria-label="Filter non org-wide users"
                    @click="chooseOrgScope('not_org_wide')"
                >
                    <span
                        :class="
                            isOrgScopeActive('not_org_wide')
                                ? 'font-medium'
                                : undefined
                        "
                    >
                        Not org-wide
                    </span>
                    <Check
                        v-if="isOrgScopeActive('not_org_wide')"
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </Button>
            </PopoverContent>
        </Popover>
    </div>
</template>
