<script setup lang="ts">
import { ChevronDown, Star } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { EmployeeIndexPosition } from '@/pages/Employees/employeeIndexTypes';

const props = defineProps<{
    positions: EmployeeIndexPosition[];
}>();

const summary = computed(() =>
    props.positions.length === 0 ? '—' : props.positions[0].title,
);

const summaryCode = computed(() =>
    props.positions.length === 0 ? null : props.positions[0].code,
);

const hasPopover = computed(() => props.positions.length > 1);

const triggerLabel = computed(() => {
    const n = props.positions.length;

    return n <= 1 ? 'View position details' : `View all ${n} positions`;
});
</script>

<template>
    <div class="flex min-w-0 items-start gap-1">
        <div class="min-w-0">
            <span
                class="block min-w-0 truncate text-sm text-foreground"
                :title="summary === '—' ? undefined : summary"
            >
                {{ summary }}
            </span>
            <span
                v-if="summaryCode !== null"
                class="block min-w-0 truncate text-xs text-muted-foreground"
                :title="summaryCode"
            >
                {{ summaryCode }}
            </span>
        </div>
        <Popover v-if="hasPopover">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0 text-muted-foreground hover:text-foreground"
                    :aria-label="triggerLabel"
                >
                    <ChevronDown class="size-4" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                side="bottom"
                class="w-auto max-w-[min(100vw-2rem,20rem)] min-w-64 overflow-hidden p-0"
            >
                <TooltipProvider :delay-duration="200">
                    <div class="px-3 pt-2 pb-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            Positions
                        </p>
                    </div>
                    <ScrollArea class="max-h-64 min-h-0">
                        <ul class="px-2 pt-0 pb-2">
                            <li
                                v-for="p in positions"
                                :key="p.id"
                                class="flex min-h-8 items-center gap-2 rounded-sm px-1.5 py-1 text-sm"
                            >
                                <div
                                    class="flex min-w-0 flex-1 items-baseline gap-2 leading-snug"
                                >
                                    <span
                                        class="min-w-0 truncate text-sm font-medium text-foreground"
                                        >{{ p.title }}</span
                                    >
                                    <span
                                        class="shrink-0 font-mono text-xs leading-snug text-muted-foreground"
                                        >{{ p.code }}</span
                                    >
                                </div>
                                <Tooltip v-if="p.is_primary">
                                    <TooltipTrigger as-child>
                                        <span
                                            class="inline-flex size-4 shrink-0 cursor-default items-center justify-center rounded-sm text-amber-600 outline-none focus-visible:ring-2 focus-visible:ring-ring dark:text-amber-400"
                                            tabindex="0"
                                            aria-label="Primary"
                                        >
                                            <Star
                                                class="size-4 fill-amber-400 text-amber-600 dark:fill-amber-500/40 dark:text-amber-400"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">
                                        Primary
                                    </TooltipContent>
                                </Tooltip>
                            </li>
                        </ul>
                    </ScrollArea>
                </TooltipProvider>
            </PopoverContent>
        </Popover>
    </div>
</template>
