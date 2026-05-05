<script setup lang="ts">
import { CheckIcon, ListFilter, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';

type FilterValue = string | number | null;

export type HrisColumnFilterOption = {
    value: string | number;
    label: string;
    secondary?: string;
    searchText?: string;
};

/**
 * Inertia / query-string flows often omit keys; parents may pass `undefined`.
 * We normalize `undefined` to `null` internally for comparisons.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        triggerAriaLabel: string;
        modelValue?: string | number | null;
        options: HrisColumnFilterOption[];
        searchable?: boolean;
        searchPlaceholder?: string;
        emptyText?: string;
        allLabel?: string;
        showAllOption?: boolean;
        showCheckIcon?: boolean;
        isActive?: boolean;
        showClear?: boolean;
        clearAriaLabel?: string;
        contentClass?: string;
        scrollHeightClass?: string;
    }>(),
    {
        modelValue: null,
        searchable: true,
        searchPlaceholder: 'Search...',
        emptyText: 'No matching options.',
        allLabel: 'All',
        showAllOption: true,
        showCheckIcon: true,
        isActive: false,
        showClear: false,
        clearAriaLabel: 'Clear filter',
        contentClass: 'w-auto min-w-64 p-0',
        scrollHeightClass: 'h-[280px]',
    },
);

const filterModelValue = computed<FilterValue>(() => props.modelValue ?? null);

const emit = defineEmits<{
    'update:modelValue': [value: FilterValue];
    clear: [];
}>();

const open = ref(false);

const rows = computed(() => {
    const mapped = props.options.map((opt) => ({
        key: `value-${String(opt.value)}`,
        value: opt.value as FilterValue,
        label: opt.label,
        secondary: opt.secondary,
        searchText: opt.searchText ?? `${opt.label} ${opt.secondary ?? ''}`,
    }));

    if (!props.showAllOption) {
        return mapped;
    }

    return [
        {
            key: 'value-all',
            value: null as FilterValue,
            label: props.allLabel,
            secondary: undefined,
            searchText: props.allLabel,
        },
        ...mapped,
    ];
});

function isSelected(value: FilterValue): boolean {
    return filterModelValue.value === value;
}

function choose(value: FilterValue): void {
    emit('update:modelValue', value);
    open.value = false;
}

function clearFilter(event: MouseEvent): void {
    event.stopPropagation();
    emit('clear');
}
</script>

<template>
    <div class="flex min-w-40 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            {{ label }}
        </span>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    :class="[
                        'size-8 shrink-0 p-0',
                        isActive
                            ? 'text-primary hover:text-primary'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :aria-label="triggerAriaLabel"
                >
                    <ListFilter class="size-4" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <Button
                v-if="showClear"
                type="button"
                variant="ghost"
                class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                :aria-label="clearAriaLabel"
                @click="clearFilter"
            >
                <X class="size-4" aria-hidden="true" />
            </Button>
            <PopoverContent align="start" side="bottom" :class="contentClass">
                <template v-if="searchable">
                    <Command>
                        <div class="border-b border-border/60 px-3 py-2">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Filter
                            </p>
                        </div>
                        <CommandInput :placeholder="searchPlaceholder" />
                        <ScrollArea :class="scrollHeightClass">
                            <CommandList
                                class="max-h-none scroll-py-1 overflow-x-hidden overflow-y-visible"
                            >
                                <CommandEmpty>{{ emptyText }}</CommandEmpty>
                                <CommandGroup>
                                    <CommandItem
                                        v-for="row in rows"
                                        :key="row.key"
                                        :value="row.searchText"
                                        :class="
                                            isSelected(row.value)
                                                ? 'bg-muted/60 text-foreground data-highlighted:bg-muted/60 data-highlighted:text-foreground'
                                                : 'data-highlighted:bg-transparent data-highlighted:text-foreground'
                                        "
                                        @select="() => choose(row.value)"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="truncate text-sm text-foreground"
                                            >
                                                {{ row.label }}
                                            </div>
                                            <div
                                                v-if="
                                                    row.secondary !== undefined
                                                "
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ row.secondary }}
                                            </div>
                                        </div>
                                        <CheckIcon
                                            v-if="showCheckIcon"
                                            :class="
                                                cn(
                                                    'ml-auto size-4 shrink-0',
                                                    isSelected(row.value)
                                                        ? 'opacity-100'
                                                        : 'opacity-0',
                                                )
                                            "
                                        />
                                    </CommandItem>
                                </CommandGroup>
                            </CommandList>
                        </ScrollArea>
                    </Command>
                </template>
                <template v-else>
                    <p
                        class="mb-2 px-1 text-xs font-medium text-muted-foreground"
                    >
                        Filter
                    </p>
                    <div class="flex flex-col gap-0.5">
                        <Button
                            v-for="row in rows"
                            :key="row.key"
                            type="button"
                            variant="ghost"
                            class="h-8 w-full justify-start px-2 font-normal text-muted-foreground hover:text-foreground"
                            :class="{
                                'bg-muted/60 text-foreground': isSelected(
                                    row.value,
                                ),
                            }"
                            :aria-pressed="isSelected(row.value)"
                            @click="choose(row.value)"
                        >
                            {{ row.label }}
                        </Button>
                    </div>
                </template>
            </PopoverContent>
        </Popover>
    </div>
</template>
