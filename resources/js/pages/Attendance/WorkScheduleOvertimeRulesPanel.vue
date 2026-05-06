<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { ClockPattern } from '@/pages/Attendance/attendanceRulesTypes';
import WorkScheduleFormFieldHint from '@/pages/Attendance/WorkScheduleFormFieldHint.vue';
import type { WorkScheduleOvertimeRulesDraft } from '@/pages/Attendance/workScheduleFormRulesTypes';
import {
    approximateWorkScheduleSpanMinutes,
    formatWorkScheduleDurationLabel,
    formatWorkScheduleNetHours,
} from '@/pages/Attendance/workScheduleUi';

const rules = defineModel<WorkScheduleOvertimeRulesDraft>({ required: true });

const props = withDefaults(
    defineProps<{
        idPrefix?: string;
        clockPattern: ClockPattern;
        /** Only true when Schedule tab uses Single Session + Overnight Shift. */
        regularScheduleOvernight?: boolean;
        scheduledNetHours?: number | null;
    }>(),
    {
        idPrefix: 'ws-ot',
        regularScheduleOvernight: false,
        scheduledNetHours: null,
    },
);

const footerSpacerClass = 'flex min-h-6 shrink-0 items-center';

const fieldLabelClass =
    'flex items-center gap-1.5 text-sm font-medium leading-none';

const otSpanPreviewLabel = computed(() => {
    if (!rules.value.otBlockEnabled) {
        return null;
    }
    const mins = approximateWorkScheduleSpanMinutes(
        rules.value.otTimeIn,
        rules.value.otTimeOut,
        rules.value.otIsOvernight,
    );
    if (mins === null || mins <= 0) {
        return null;
    }

    return formatWorkScheduleDurationLabel(mins);
});

const patternHint = computed(() =>
    props.clockPattern === 'single_pair'
        ? 'Single Session schedule uses one regular window on the Schedule tab; overtime is configured here only.'
        : 'Split Sessions use Session 1–2 for regular work on the Schedule tab; overtime is a separate block below.',
);
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <div class="grid w-full gap-4">
            <div class="rounded-lg border border-border/60 bg-muted/10 p-3">
                <div class="flex items-start gap-2">
                    <p class="text-sm font-medium text-foreground">
                        Overtime (preview)
                    </p>
                    <WorkScheduleFormFieldHint
                        ariaLabel="About overtime on work schedules"
                    >
                        Model overtime as its own scheduled time in and time
                        out. Gross OT minutes come from that span—no separate OT
                        caps here. Premium rates and approvals stay on Overtime
                        Policies.
                        {{ patternHint }}
                    </WorkScheduleFormFieldHint>
                </div>
            </div>

            <div
                class="flex flex-col gap-3 rounded-lg border border-border/60 bg-muted/15 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <p class="text-sm font-medium text-foreground">
                        Schedule overtime block
                    </p>
                    <WorkScheduleFormFieldHint
                        ariaLabel="About scheduling an overtime block"
                    >
                        Turn off if this template has no fixed OT window (engine
                        may treat OT-only elsewhere later).
                    </WorkScheduleFormFieldHint>
                </div>
                <Switch v-model="rules.otBlockEnabled" class="shrink-0" />
            </div>

            <div
                v-if="rules.otBlockEnabled"
                class="grid w-full grid-cols-12 gap-3 rounded-lg border border-border/50 bg-background/40 p-3"
            >
                <div
                    class="col-span-12 flex min-w-0 flex-col gap-1.5 sm:col-span-6"
                >
                    <Label :class="fieldLabelClass" :for="`${idPrefix}-ot-in`">
                        <span>OT time in</span>
                        <WorkScheduleFormFieldHint ariaLabel="About OT time in">
                            Start of the scheduled overtime block (wall clock).
                        </WorkScheduleFormFieldHint>
                    </Label>
                    <Input
                        :id="`${idPrefix}-ot-in`"
                        v-model="rules.otTimeIn"
                        type="time"
                        class="w-full font-mono tabular-nums"
                    />
                    <div :class="footerSpacerClass" aria-hidden="true" />
                </div>
                <div
                    class="col-span-12 flex min-w-0 flex-col gap-1.5 sm:col-span-6"
                >
                    <Label :class="fieldLabelClass" :for="`${idPrefix}-ot-out`">
                        <span>OT time out</span>
                        <WorkScheduleFormFieldHint
                            ariaLabel="About OT time out"
                        >
                            End of the scheduled overtime block. Duration is
                            derived from in/out. Use Overnight OT span only when
                            the regular schedule is an overnight shift and this
                            OT block crosses midnight.
                        </WorkScheduleFormFieldHint>
                    </Label>
                    <Input
                        :id="`${idPrefix}-ot-out`"
                        v-model="rules.otTimeOut"
                        type="time"
                        class="w-full font-mono tabular-nums"
                    />
                    <div :class="footerSpacerClass" aria-hidden="true" />
                </div>
                <div
                    v-if="regularScheduleOvernight"
                    class="col-span-12 flex w-full min-w-0 flex-col gap-2 rounded-md border border-border/40 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Label
                        :for="`${idPrefix}-ot-overnight`"
                        class="cursor-pointer text-sm font-medium"
                    >
                        Overnight OT span
                    </Label>
                    <Switch
                        :id="`${idPrefix}-ot-overnight`"
                        v-model="rules.otIsOvernight"
                        class="shrink-0"
                    />
                </div>
                <p
                    v-if="regularScheduleOvernight"
                    class="col-span-12 w-full text-xs text-muted-foreground"
                >
                    Enable when OT time out is on the next calendar day after OT
                    time in. Shown only when the Schedule tab uses an overnight
                    regular shift.
                </p>
                <div
                    v-if="otSpanPreviewLabel"
                    class="col-span-12 rounded-md border border-border/50 bg-muted/20 px-3 py-2 text-sm text-foreground"
                >
                    <span class="text-muted-foreground"
                        >Scheduled OT span:</span
                    >
                    <span class="ms-1 tabular-nums">{{
                        otSpanPreviewLabel
                    }}</span>
                </div>
            </div>

            <div
                class="flex flex-col gap-3 rounded-lg border border-border/60 bg-muted/15 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <p class="text-sm font-medium text-foreground">
                        Continuous after regular net
                    </p>
                    <WorkScheduleFormFieldHint
                        ariaLabel="About continuous regular and OT"
                    >
                        When on, regular working time and this OT block are
                        treated as one continuous story for policy (e.g. no
                        artificial gap required between Session 2 end and OT
                        start). Turn off if OT must be explicitly separated from
                        regular hours.
                    </WorkScheduleFormFieldHint>
                </div>
                <Switch
                    v-model="rules.continuousAfterRegularNet"
                    class="shrink-0"
                />
            </div>

            <p
                v-if="
                    rules.continuousAfterRegularNet &&
                    scheduledNetHours !== null
                "
                class="text-xs text-muted-foreground"
            >
                Regular net from Schedule tab:
                <span class="text-foreground tabular-nums">{{
                    formatWorkScheduleNetHours(scheduledNetHours)
                }}</span>
                hours
            </p>

            <div class="grid w-full grid-cols-12 gap-3">
                <div
                    class="col-span-12 flex min-w-0 flex-col gap-1.5 sm:col-span-6"
                >
                    <Label
                        :class="fieldLabelClass"
                        :for="`${idPrefix}-ot-grace`"
                    >
                        <span>OT boundary grace (minutes)</span>
                        <WorkScheduleFormFieldHint ariaLabel="About OT grace">
                            Minutes ignored at the OT start boundary before
                            credited OT time begins (placeholder for rules
                            engine).
                        </WorkScheduleFormFieldHint>
                    </Label>
                    <Input
                        :id="`${idPrefix}-ot-grace`"
                        v-model.number="rules.otGraceMinutes"
                        type="number"
                        min="0"
                        step="1"
                        class="w-full max-w-xs tabular-nums"
                    />
                    <div :class="footerSpacerClass" aria-hidden="true" />
                </div>
            </div>
        </div>
    </TooltipProvider>
</template>
