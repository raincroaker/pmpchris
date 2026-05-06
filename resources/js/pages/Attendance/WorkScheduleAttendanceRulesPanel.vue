<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { ClockPattern } from '@/pages/Attendance/attendanceRulesTypes';
import WorkScheduleFormFieldHint from '@/pages/Attendance/WorkScheduleFormFieldHint.vue';
import type { WorkScheduleAttendanceRulesDraft } from '@/pages/Attendance/workScheduleFormRulesTypes';
import { formatWorkScheduleNetHours } from '@/pages/Attendance/workScheduleUi';

const rules = defineModel<WorkScheduleAttendanceRulesDraft>({ required: true });
const graceMinutes = defineModel<number>('graceMinutes', { required: true });

const props = withDefaults(
    defineProps<{
        idPrefix: string;
        clockPattern: ClockPattern;
        /** When set, net regular cap matches scheduled net from the Schedule tab (read-only). */
        scheduledNetHours?: number | null;
    }>(),
    {
        idPrefix: 'ws',
        scheduledNetHours: null,
    },
);

const graceScopeHint = computed(() =>
    props.clockPattern === 'single_pair'
        ? 'Applies only to the scheduled first clock-in for this template (simple session: time in). Afternoon or OT clock-ins are not graced here.'
        : 'Applies only to Session 1 time in—not Session 2 or 3.',
);

const footerSpacerClass = 'flex min-h-6 shrink-0 items-center';

/** Label + info icon: vertically center icon with single-line title text. */
const fieldLabelClass =
    'flex items-center gap-1.5 text-sm font-medium leading-none';
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <div class="grid w-full gap-4">
            <div class="rounded-lg border border-border/60 bg-muted/10 p-3">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-medium text-foreground">
                        Regular attendance (preview)
                    </p>
                    <WorkScheduleFormFieldHint
                        ariaLabel="About regular attendance preview"
                    >
                        Configures how clock punches become gross/net regular
                        time. These fields are stored as JSON on the template
                        (attendance_rules) for the upcoming rules engine.
                    </WorkScheduleFormFieldHint>
                </div>
            </div>

            <!-- Grace + clock-in rounding (one row on sm+) -->
            <div class="grid w-full grid-cols-12 gap-3">
                <div
                    class="col-span-12 flex min-w-0 flex-col gap-1.5 sm:col-span-6"
                >
                    <Label
                        :class="fieldLabelClass"
                        :for="`${idPrefix}-grace-min`"
                    >
                        <span class="min-w-0 shrink"
                            >Grace period (minutes)</span
                        >
                        <WorkScheduleFormFieldHint
                            ariaLabel="About grace period"
                        >
                            Minutes after the scheduled first start of the day
                            that still count as on-time (first clock-in only).
                            {{ graceScopeHint }}
                        </WorkScheduleFormFieldHint>
                    </Label>
                    <Input
                        :id="`${idPrefix}-grace-min`"
                        v-model.number="graceMinutes"
                        type="number"
                        min="0"
                        step="1"
                        class="w-full tabular-nums"
                    />
                    <div :class="footerSpacerClass" aria-hidden="true" />
                </div>
                <div
                    class="col-span-12 flex min-w-0 flex-col gap-1.5 sm:col-span-6"
                >
                    <Label
                        :class="fieldLabelClass"
                        :for="`${idPrefix}-clock-round`"
                    >
                        <span class="min-w-0 shrink">Round clock-in to</span>
                        <WorkScheduleFormFieldHint
                            ariaLabel="About clock-in rounding"
                        >
                            Snap the recorded first clock-in to the nearest
                            interval before lateness and net-time math
                            (placeholder).
                        </WorkScheduleFormFieldHint>
                    </Label>
                    <Select v-model="rules.clockInRounding">
                        <SelectTrigger
                            :id="`${idPrefix}-clock-round`"
                            class="w-full"
                        >
                            <SelectValue placeholder="Rounding" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">None (exact)</SelectItem>
                            <SelectItem value="5">Nearest 5 minutes</SelectItem>
                            <SelectItem value="15"
                                >Nearest 15 minutes</SelectItem
                            >
                            <SelectItem value="custom">Custom…</SelectItem>
                        </SelectContent>
                    </Select>
                    <div :class="footerSpacerClass" aria-hidden="true" />
                </div>
                <div
                    v-if="rules.clockInRounding === 'custom'"
                    class="col-span-12 flex min-w-0 flex-col gap-1.5 sm:col-span-6 sm:col-start-7"
                >
                    <Label
                        :class="fieldLabelClass"
                        :for="`${idPrefix}-clock-round-custom`"
                    >
                        <span class="min-w-0 shrink"
                            >Custom interval (minutes)</span
                        >
                        <WorkScheduleFormFieldHint
                            ariaLabel="About custom rounding interval"
                        >
                            Used when “Custom…” is selected for clock-in
                            rounding.
                        </WorkScheduleFormFieldHint>
                    </Label>
                    <Input
                        :id="`${idPrefix}-clock-round-custom`"
                        v-model.number="rules.clockInRoundingCustomMinutes"
                        type="number"
                        min="1"
                        max="60"
                        step="1"
                        class="w-full tabular-nums"
                    />
                    <div :class="footerSpacerClass" aria-hidden="true" />
                </div>
            </div>

            <section class="space-y-3">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Regular-hour cap
                </h3>
                <div
                    class="flex flex-col gap-2 rounded-lg border border-border/60 bg-muted/15 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <p class="text-sm font-medium text-foreground">
                            Cap net regular hours
                        </p>
                        <WorkScheduleFormFieldHint
                            ariaLabel="About net regular hours cap"
                        >
                            Net credited time for regular pay does not exceed
                            this daily standard when enabled. When the Schedule
                            tab yields a net duration, this value follows it
                            automatically (same as Gross − breaks / split gaps).
                            If times are still incomplete, enter hours manually
                            here, or finish the Schedule tab so net time can be
                            derived and this cap matches it.
                        </WorkScheduleFormFieldHint>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-3">
                        <Switch v-model="rules.netRegularHoursCapEnabled" />
                        <div
                            v-if="
                                rules.netRegularHoursCapEnabled &&
                                scheduledNetHours !== null
                            "
                            class="flex flex-wrap items-center gap-2"
                        >
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground tabular-nums"
                                :aria-label="`Net regular cap ${formatWorkScheduleNetHours(scheduledNetHours)} hours from schedule`"
                            >
                                {{
                                    formatWorkScheduleNetHours(
                                        scheduledNetHours,
                                    )
                                }}
                                <span class="text-muted-foreground">hours</span>
                            </p>
                            <span class="text-xs text-muted-foreground">
                                From scheduled net
                            </span>
                        </div>
                        <div
                            v-else-if="rules.netRegularHoursCapEnabled"
                            class="flex items-center gap-2"
                        >
                            <Input
                                :id="`${idPrefix}-net-cap`"
                                v-model.number="rules.netRegularHoursCap"
                                type="number"
                                min="1"
                                max="24"
                                step="0.5"
                                class="w-24 tabular-nums"
                            />
                            <Label
                                :for="`${idPrefix}-net-cap`"
                                class="text-muted-foreground"
                            >
                                hours
                            </Label>
                        </div>
                    </div>
                </div>
            </section>

            <div
                class="overflow-hidden rounded-lg border border-border/60 bg-muted/10"
            >
                <Collapsible v-model:open="rules.advancedOpen">
                    <CollapsibleTrigger as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            class="h-auto min-h-10 w-full justify-between gap-2 rounded-none px-3 py-2.5 text-left text-sm font-medium text-foreground hover:bg-muted/40"
                        >
                            <span>Advanced grace limits</span>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform duration-200"
                                :class="{ 'rotate-180': rules.advancedOpen }"
                            />
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <div
                            class="space-y-3 border-t border-border/50 bg-muted/5 px-3 py-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Optional guardrails when you need to limit how
                                often late arrivals still qualify as “within
                                grace” in a month.
                            </p>
                            <div
                                class="flex flex-col gap-2 rounded-md border border-border/50 bg-background/40 p-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div
                                    class="flex min-w-0 flex-1 items-center gap-2"
                                >
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        Limit grace uses per employee / month
                                    </p>
                                    <WorkScheduleFormFieldHint
                                        ariaLabel="About grace uses limit"
                                    >
                                        After the limit, additional late
                                        arrivals follow policy (placeholder —
                                        not persisted yet).
                                    </WorkScheduleFormFieldHint>
                                </div>
                                <Switch
                                    v-model="rules.graceUsesLimitEnabled"
                                    class="shrink-0"
                                />
                            </div>
                            <div
                                v-if="rules.graceUsesLimitEnabled"
                                class="grid w-full grid-cols-12 gap-3"
                            >
                                <div
                                    class="col-span-12 flex min-w-0 flex-col gap-1.5 sm:col-span-6"
                                >
                                    <Label
                                        :class="fieldLabelClass"
                                        :for="`${idPrefix}-grace-limit`"
                                    >
                                        <span class="min-w-0 shrink"
                                            >Uses per month</span
                                        >
                                        <WorkScheduleFormFieldHint
                                            ariaLabel="About grace uses per month"
                                        >
                                            Maximum times grace can apply to
                                            first clock-in per employee per
                                            calendar month (placeholder).
                                        </WorkScheduleFormFieldHint>
                                    </Label>
                                    <Input
                                        :id="`${idPrefix}-grace-limit`"
                                        v-model.number="rules.graceUsesPerMonth"
                                        type="number"
                                        min="1"
                                        max="31"
                                        step="1"
                                        class="w-full max-w-xs tabular-nums"
                                    />
                                    <div
                                        :class="footerSpacerClass"
                                        aria-hidden="true"
                                    />
                                </div>
                            </div>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </div>
        </div>
    </TooltipProvider>
</template>
