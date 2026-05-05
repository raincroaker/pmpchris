<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown, ListFilter } from 'lucide-vue-next';
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
}>();

const emit = defineEmits<{
    sortBy: [field: 'name' | 'code'];
}>();

const open = ref(false);

const nameSortIcon = computed(() =>
    props.sort !== 'name'
        ? ArrowUpDown
        : props.direction === 'asc'
          ? ArrowUp
          : ArrowDown,
);

const codeSortIcon = computed(() =>
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
</script>

<template>
    <div class="flex min-w-0 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            Role
        </span>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                    aria-label="Open filter: sort by role name or code"
                >
                    <ListFilter class="size-4" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
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
                        Code
                    </span>
                    <component
                        :is="codeSortIcon"
                        :class="[
                            'size-4 shrink-0',
                            sort === 'code'
                                ? 'text-primary'
                                : 'text-muted-foreground',
                        ]"
                        aria-hidden="true"
                    />
                </Button>
            </PopoverContent>
        </Popover>
    </div>
</template>
