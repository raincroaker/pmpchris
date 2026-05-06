<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = withDefaults(
    defineProps<{
        icon: Component;
        iconClass?: string;
        title: string;
        description: string;
        isSelf: boolean;
        editIconSelf: Component;
        editIconHr: Component;
        editAriaLabelSelf: string;
        editAriaLabelHr: string;
        tooltipSelf: string;
        tooltipHr: string;
        disabled?: boolean;
        cardContentClass?: string;
        /** When false, hide the header edit/lock control (e.g. external-link-only cards). */
        showEditButton?: boolean;
    }>(),
    {
        iconClass: 'size-6',
        disabled: false,
        cardContentClass: 'pt-6',
        showEditButton: true,
    },
);

const emit = defineEmits<{
    edit: [];
}>();

const isSelf = computed(() => props.isSelf);

function onEditClick(): void {
    if (props.disabled) {
        return;
    }

    emit('edit');
}
</script>

<template>
    <Card
        class="relative overflow-hidden border-border/70 bg-card/85 shadow-sm"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/8 via-primary/25 to-primary/8"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -right-14 top-1/2 size-28 -translate-y-1/2 rounded-full bg-primary/6 blur-2xl"
            aria-hidden="true"
        />
        <div
            class="mx-4 mt-0 mb-0 rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25 sm:mx-6"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 flex-row items-start gap-4">
                    <span
                        class="inline-flex shrink-0 rounded-full bg-primary/10 p-3 text-primary"
                    >
                        <component
                            :is="icon"
                            :class="iconClass"
                            aria-hidden="true"
                        />
                    </span>
                    <div class="min-w-0 flex-1 space-y-0.5">
                        <h3 class="text-base font-semibold text-foreground">
                            {{ title }}
                        </h3>
                        <p class="text-sm leading-relaxed text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <slot name="header-actions" />

                    <Tooltip v-if="showEditButton">
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="shrink-0 cursor-pointer border-border bg-background/80 hover:border-foreground/40 hover:bg-muted/60"
                                :disabled="disabled"
                                @click="onEditClick"
                                :aria-label="
                                    isSelf
                                        ? editAriaLabelSelf
                                        : editAriaLabelHr
                                "
                            >
                                <component
                                    :is="isSelf ? editIconSelf : editIconHr"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            {{ isSelf ? tooltipSelf : tooltipHr }}
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>
        </div>

        <CardContent :class="cardContentClass">
            <slot />
        </CardContent>
    </Card>
</template>

