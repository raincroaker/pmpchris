<script setup lang="ts">
import { CheckIcon, ChevronsUpDownIcon } from 'lucide-vue-next';
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

export type EmployeePositionOption = {
    id: number;
    code: string;
    title: string;
};

const modelValue = defineModel<string>({ default: '' });

const props = withDefaults(
    defineProps<{
        positions: EmployeePositionOption[];
        disabled?: boolean;
        triggerId?: string;
        /** When true, combobox trigger reflects validation error (e.g. wizard step 3). */
        ariaInvalid?: boolean;
        searchPlaceholder?: string;
        triggerPlaceholder?: string;
        emptyCatalogLabel?: string;
    }>(),
    {
        disabled: false,
        triggerId: undefined,
        ariaInvalid: false,
        searchPlaceholder: 'Search position…',
        triggerPlaceholder: 'Search or pick a position…',
        emptyCatalogLabel: 'No positions available',
    },
);

const open = ref(false);

const selectedPosition = computed((): EmployeePositionOption | null => {
    if (!modelValue.value) {
        return null;
    }

    return (
        props.positions.find((p) => String(p.id) === modelValue.value) ?? null
    );
});

function onSelectPosition(idStr: string): void {
    modelValue.value = modelValue.value === idStr ? '' : idStr;
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="triggerId"
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :aria-invalid="ariaInvalid"
                :disabled="disabled"
                class="w-full min-w-0 justify-between gap-2 font-normal"
            >
                <span
                    v-if="disabled && positions.length === 0"
                    class="min-w-0 flex-1 truncate text-left text-muted-foreground"
                >
                    {{ emptyCatalogLabel }}
                </span>
                <span
                    v-else-if="selectedPosition"
                    class="min-w-0 flex-1 truncate text-left font-normal"
                >
                    {{ selectedPosition.title }}
                </span>
                <span
                    v-else
                    class="min-w-0 flex-1 truncate text-left text-muted-foreground"
                >
                    {{ triggerPlaceholder }}
                </span>
                <ChevronsUpDownIcon class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent
            class="w-(--radix-popover-trigger-width) p-0"
            align="start"
        >
            <Command>
                <CommandInput :placeholder="searchPlaceholder" />
                <ScrollArea class="h-[280px]">
                    <CommandList
                        class="max-h-none scroll-py-1 overflow-x-hidden overflow-y-visible"
                    >
                        <CommandEmpty>No matching positions.</CommandEmpty>
                        <CommandGroup>
                            <CommandItem
                                v-for="p in positions"
                                :key="p.id"
                                :value="String(p.id)"
                                class="min-w-0 gap-2 pr-2"
                                @select="() => onSelectPosition(String(p.id))"
                            >
                                <span class="min-w-0 flex-1 truncate">
                                    {{ p.title }}
                                </span>
                                <CheckIcon
                                    :class="
                                        cn(
                                            'ml-auto size-4 shrink-0',
                                            modelValue === String(p.id)
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
        </PopoverContent>
    </Popover>
</template>
