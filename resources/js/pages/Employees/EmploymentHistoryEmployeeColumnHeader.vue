<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown, ListFilter } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { EmploymentHistoryFilters } from '@/pages/Employees/employmentHistoryTypes';

const props = defineProps<{
    sort: EmploymentHistoryFilters['sort'];
    direction: EmploymentHistoryFilters['direction'];
}>();

const emit = defineEmits<{
    sortBy: [field: 'last_name' | 'id_number'];
}>();

const open = ref(false);

const nameSortIcon = computed(() =>
    props.sort !== 'last_name'
        ? ArrowUpDown
        : props.direction === 'asc'
          ? ArrowUp
          : ArrowDown,
);

const idSortIcon = computed(() =>
    props.sort !== 'id_number'
        ? ArrowUpDown
        : props.direction === 'asc'
          ? ArrowUp
          : ArrowDown,
);

function nameSortAriaLabel(): string {
    if (props.sort !== 'last_name') {
        return 'Sort by last name';
    }

    return props.direction === 'asc'
        ? 'Last name sorted ascending; activate to sort descending'
        : 'Last name sorted descending; activate to sort ascending';
}

function idSortAriaLabel(): string {
    if (props.sort !== 'id_number') {
        return 'Sort by employee ID';
    }

    return props.direction === 'asc'
        ? 'Employee ID sorted ascending; activate to sort descending'
        : 'Employee ID sorted descending; activate to sort ascending';
}

function choose(field: 'last_name' | 'id_number'): void {
    emit('sortBy', field);
}
</script>

<template>
    <div class="flex min-w-0 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            Employee
        </span>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                    aria-label="Open employee sort options"
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
                    Sort
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-8 w-full justify-between px-2 font-normal text-muted-foreground hover:text-foreground"
                    :class="{
                        'bg-muted/60 text-foreground': sort === 'last_name',
                    }"
                    :aria-label="nameSortAriaLabel()"
                    @click="choose('last_name')"
                >
                    <span
                        :class="
                            sort === 'last_name' ? 'font-medium' : undefined
                        "
                    >
                        Name
                    </span>
                    <component
                        :is="nameSortIcon"
                        :class="[
                            'size-4 shrink-0',
                            sort === 'last_name'
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
                    :class="{
                        'bg-muted/60 text-foreground': sort === 'id_number',
                    }"
                    :aria-label="idSortAriaLabel()"
                    @click="choose('id_number')"
                >
                    <span
                        :class="
                            sort === 'id_number' ? 'font-medium' : undefined
                        "
                    >
                        ID
                    </span>
                    <component
                        :is="idSortIcon"
                        :class="[
                            'size-4 shrink-0',
                            sort === 'id_number'
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
