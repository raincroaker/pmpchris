<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    placementBadgeBorderStyle,
    placementBadgeToneClassFromColor,
} from '@/lib/hrisUnitPlacementBadgeStyle';
import type { EmployeeIndexPosition } from '@/pages/Employees/employeeIndexTypes';

withDefaults(
    defineProps<{
        unitLabel?: string;
        unitName: string;
        unitCode?: string | null;
        /** Shown on hover (e.g. unit type / directory label). */
        unitDirectoryType: string;
        unitDirectoryColor?: string | null;
        /** Whether this placement is the employee’s primary unit. */
        placementIsPrimary?: boolean;
        positionsLabel?: string;
        positions: EmployeeIndexPosition[];
    }>(),
    {
        unitLabel: 'Unit',
        placementIsPrimary: false,
        positionsLabel: 'Job positions',
    },
);
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <div class="grid gap-1.5 sm:col-span-2">
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ unitLabel }}
            </p>
            <div
                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
            >
                <div class="flex flex-wrap gap-1.5">
                    <Badge
                        variant="outline"
                        :style="placementBadgeBorderStyle(unitDirectoryColor)"
                        class="inline-flex max-w-full items-center gap-0.5 rounded-full border py-0 pr-0.5 pl-2 text-xs font-normal"
                        :class="
                            placementBadgeToneClassFromColor(unitDirectoryColor)
                        "
                    >
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <span
                                    class="inline-flex max-w-full min-w-0 cursor-default items-center gap-1 py-0.5 pr-0.5"
                                >
                                    <span
                                        class="min-w-0 truncate font-medium"
                                        >{{ unitName }}</span
                                    >
                                    <span
                                        v-if="unitCode"
                                        class="shrink-0 text-[10px] text-muted-foreground tabular-nums"
                                        >{{ unitCode }}</span
                                    >
                                </span>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                <p class="text-sm font-medium">
                                    {{ unitDirectoryType }}
                                </p>
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="placementIsPrimary">
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-5 shrink-0 p-0 text-amber-600 hover:bg-amber-500/15 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-400/15 dark:hover:text-amber-300"
                                    aria-label="Primary placement"
                                >
                                    <Star
                                        class="size-3.5 fill-amber-400/35"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                Primary
                            </TooltipContent>
                        </Tooltip>
                    </Badge>
                </div>
            </div>
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ positionsLabel }}
            </p>
            <div
                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
            >
                <div v-if="positions.length > 0" class="flex flex-wrap gap-1.5">
                    <Badge
                        v-for="p in positions"
                        :key="p.id"
                        variant="outline"
                        class="inline-flex items-center gap-1 rounded-full py-0 pr-0.5 pl-2.5 text-xs font-normal"
                        :class="placementBadgeToneClassFromColor(null)"
                    >
                        <span class="truncate font-medium">{{ p.title }}</span>
                        <span
                            v-if="p.code"
                            class="shrink-0 text-[10px] text-muted-foreground"
                            >{{ p.code }}</span
                        >
                        <Tooltip v-if="p.is_primary">
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-5 shrink-0 p-0 text-amber-600 hover:bg-amber-500/15 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-400/15 dark:hover:text-amber-300"
                                    aria-label="Primary position"
                                >
                                    <Star
                                        class="size-3.5 fill-amber-400/35"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                Primary
                            </TooltipContent>
                        </Tooltip>
                    </Badge>
                </div>
                <p v-else class="text-xs text-muted-foreground">
                    No job positions on file.
                </p>
            </div>
        </div>
    </TooltipProvider>
</template>
