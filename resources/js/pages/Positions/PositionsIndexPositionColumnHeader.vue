<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown, ListFilter } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { PositionFilters } from '@/pages/Positions/positionIndexTypes';

const props = defineProps<{
    sort: PositionFilters['sort'];
    direction: PositionFilters['direction'];
}>();

const emit = defineEmits<{
    sortBy: [field: 'code' | 'title'];
}>();

const open = ref(false);

const titleSortIcon = computed(() =>
    props.sort !== 'title'
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

function titleSortAriaLabel(): string {
    if (props.sort !== 'title') {
        return 'Sort by title';
    }

    return props.direction === 'asc'
        ? 'Title sorted ascending; activate to sort descending'
        : 'Title sorted descending; activate to sort ascending';
}

function codeSortAriaLabel(): string {
    if (props.sort !== 'code') {
        return 'Sort by code';
    }

    return props.direction === 'asc'
        ? 'Code sorted ascending; activate to sort descending'
        : 'Code sorted descending; activate to sort ascending';
}

function choose(field: 'code' | 'title'): void {
    emit('sortBy', field);
    open.value = false;
}
</script>

<template>
    <div class="flex min-w-[10rem] items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            Position
        </span>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                    aria-label="Open filter: sort by title or code"
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
                    :class="{ 'bg-muted/60 text-foreground': sort === 'title' }"
                    :aria-label="titleSortAriaLabel()"
                    @click="choose('title')"
                >
                    <span :class="sort === 'title' ? 'font-medium' : undefined">
                        Title
                    </span>
                    <component
                        :is="titleSortIcon"
                        :class="[
                            'size-4 shrink-0',
                            sort === 'title'
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
                    :aria-label="codeSortAriaLabel()"
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
