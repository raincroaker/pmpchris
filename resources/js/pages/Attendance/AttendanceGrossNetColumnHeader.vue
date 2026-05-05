<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    ATTENDANCE_VIEW_GROSS_PUNCH_SUM_TOOLTIP,
    ATTENDANCE_VIEW_NET_PUNCH_SUM_TOOLTIP,
} from '@/pages/Attendance/teamAttendanceUi';

const props = defineProps<{
    metric: 'gross' | 'net';
}>();

const tooltipText = computed(() =>
    props.metric === 'gross'
        ? ATTENDANCE_VIEW_GROSS_PUNCH_SUM_TOOLTIP
        : ATTENDANCE_VIEW_NET_PUNCH_SUM_TOOLTIP,
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
        <div class="flex min-h-5 min-w-0 items-center gap-1">
            <span class="font-medium text-muted-foreground">
                {{ labelText }}
            </span>
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
