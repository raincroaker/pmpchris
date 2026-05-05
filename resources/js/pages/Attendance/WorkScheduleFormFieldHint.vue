<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    /** Accessible name for the info button (required). */
    ariaLabel: string;
}>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                :aria-label="ariaLabel"
            >
                <Info class="size-3.5" aria-hidden="true" />
            </Button>
        </TooltipTrigger>
        <TooltipContent side="top" class="max-w-xs text-pretty">
            <slot />
        </TooltipContent>
    </Tooltip>
</template>
