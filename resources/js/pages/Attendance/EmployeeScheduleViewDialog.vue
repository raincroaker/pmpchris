<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
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
import type { EmployeeScheduleRow } from '@/pages/Attendance/employeeScheduleIndexTypes';

defineProps<{
    open: boolean;
    row: EmployeeScheduleRow | null;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const orgWideBadgeClass =
    'rounded-full border-emerald-200 bg-emerald-50 text-[11px] font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300';

const branchScopedBadgeClass =
    'rounded-full border-border/70 bg-muted/40 text-[11px] font-medium text-muted-foreground';

function close(): void {
    emit('update:open', false);
}
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Dialog :open="open" @update:open="emit('update:open', $event)">
            <DialogContent v-if="row" class="gap-4 sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Employee schedule summary</DialogTitle>
                    <DialogDescription>
                        Employee identity, attendance ID, units, assigned work
                        schedule, and job positions (read-only).
                    </DialogDescription>
                </DialogHeader>

                <ScrollArea class="max-h-[70vh] pr-3">
                    <div class="grid gap-3 px-2 py-2 text-sm sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Name
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                            >
                                {{ row.display_name }}
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Employee ID
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                            >
                                {{ row.id_number }}
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Attendance ID
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-sm"
                                :class="
                                    row.attendance_id
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ row.attendance_id ?? '—' }}
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Work Schedule
                            </p>
                            <div
                                v-if="row.work_schedule"
                                class="flex min-h-10 flex-wrap items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-start text-sm text-foreground"
                            >
                                <span class="min-w-0 font-medium">{{
                                    row.work_schedule.name
                                }}</span>
                                <span
                                    v-if="!row.work_schedule.is_active"
                                    class="text-xs text-muted-foreground"
                                    >(inactive)</span
                                >
                            </div>
                            <p
                                v-else
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-xs text-muted-foreground"
                            >
                                Unassigned
                            </p>
                        </div>

                        <Separator class="sm:col-span-2" />

                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Directory visibility
                            </p>
                            <div
                                class="flex flex-wrap items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                            >
                                <Badge
                                    v-if="row.is_org_wide"
                                    variant="outline"
                                    :class="`text-xs font-medium ${orgWideBadgeClass}`"
                                >
                                    Org-wide
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    :class="`text-xs font-medium ${branchScopedBadgeClass}`"
                                >
                                    Not org-wide
                                </Badge>
                            </div>
                        </div>

                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Units
                            </p>
                            <div
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                            >
                                <div
                                    v-if="row.units.length > 0"
                                    class="flex flex-wrap gap-1.5"
                                >
                                    <Badge
                                        v-for="(unit, idx) in row.units"
                                        :key="`unit-${idx}-${unit.name}`"
                                        variant="outline"
                                        :style="
                                            placementBadgeBorderStyle(
                                                unit.unit_type_color,
                                            )
                                        "
                                        class="inline-flex max-w-full items-center gap-0.5 rounded-full border py-0 pr-0.5 pl-2 text-xs font-normal"
                                        :class="
                                            placementBadgeToneClassFromColor(
                                                unit.unit_type_color,
                                            )
                                        "
                                    >
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span
                                                    class="inline-flex max-w-full min-w-0 cursor-default items-center gap-1 py-0.5 pr-0.5"
                                                >
                                                    <span
                                                        class="min-w-0 truncate font-medium"
                                                        >{{ unit.name }}</span
                                                    >
                                                    <span
                                                        v-if="unit.code"
                                                        class="shrink-0 text-[10px] text-muted-foreground tabular-nums"
                                                        >{{ unit.code }}</span
                                                    >
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent side="top">
                                                <p class="text-sm font-medium">
                                                    {{ unit.unit_type }}
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                        <Tooltip v-if="unit.is_primary">
                                            <TooltipTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-5 shrink-0 p-0 text-amber-600 hover:bg-amber-500/15 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-400/15 dark:hover:text-amber-300"
                                                    aria-label="Primary unit"
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
                                <p
                                    v-else
                                    class="py-2 text-xs text-muted-foreground"
                                >
                                    No units on file.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Job positions
                            </p>
                            <div
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                            >
                                <div
                                    v-if="row.positions.length > 0"
                                    class="flex flex-wrap gap-1.5"
                                >
                                    <Badge
                                        v-for="p in row.positions"
                                        :key="p.id"
                                        variant="outline"
                                        class="inline-flex items-center gap-1 rounded-full pr-2 pl-2.5 text-xs font-normal"
                                    >
                                        <span class="truncate font-medium">
                                            {{ p.title }}</span
                                        >
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
                    </div>
                </ScrollArea>

                <DialogFooter class="gap-2 sm:gap-2">
                    <Button type="button" variant="outline" @click="close">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TooltipProvider>
</template>
