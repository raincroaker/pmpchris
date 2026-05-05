<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    WORK_SCHEDULE_GROSS_TIME_TOOLTIP,
    WORK_SCHEDULE_NET_TIME_TOOLTIP,
} from '@/pages/Attendance/workScheduleMetricTooltips';

const props = withDefaults(
    defineProps<{
        metric: 'gross' | 'net';
        variant?: 'form' | 'view' | 'table';
    }>(),
    { variant: 'form' },
);

const tooltipText = computed(() =>
    props.metric === 'gross'
        ? WORK_SCHEDULE_GROSS_TIME_TOOLTIP
        : WORK_SCHEDULE_NET_TIME_TOOLTIP,
);

const labelText = computed(() =>
    props.metric === 'gross' ? 'Gross Time' : 'Net Time',
);

const ariaLabel = computed(() =>
    props.metric === 'gross' ? 'Explain Gross Time' : 'Explain Net Time',
);
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <div class="flex min-h-5 items-center gap-1">
            <Label v-if="variant === 'form'" class="normal-case">
                {{ labelText }}
            </Label>
            <span
                v-else-if="variant === 'table'"
                class="font-medium text-muted-foreground"
            >
                {{ labelText }}
            </span>
            <p
                v-else
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ labelText }}
            </p>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                        :aria-label="ariaLabel"
                    >
                        <Info class="size-3.5" aria-hidden="true" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="top" class="max-w-xs text-pretty">
                    {{ tooltipText }}
                </TooltipContent>
            </Tooltip>
        </div>
    </TooltipProvider>
</template>
