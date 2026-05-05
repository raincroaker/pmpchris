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
    label: string;
}>();

const emit = defineEmits<{
    sortBy: [field: 'users_count'];
}>();

const open = ref(false);

const countSortIcon = computed(() =>
    props.sort !== 'users_count'
        ? ArrowUpDown
        : props.direction === 'asc'
          ? ArrowUp
          : ArrowDown,
);

function choose(): void {
    emit('sortBy', 'users_count');
    open.value = false;
}
</script>

<template>
    <div class="flex min-w-0 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            {{ label }}
        </span>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                    :aria-label="`Open filter: sort by ${label.toLowerCase()}`"
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
                    :class="{
                        'bg-muted/60 text-foreground': sort === 'users_count',
                    }"
                    @click="choose"
                >
                    <span
                        :class="
                            sort === 'users_count' ? 'font-medium' : undefined
                        "
                    >
                        {{ label }}
                    </span>
                    <component
                        :is="countSortIcon"
                        :class="[
                            'size-4 shrink-0',
                            sort === 'users_count'
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
