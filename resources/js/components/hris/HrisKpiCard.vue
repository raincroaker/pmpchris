<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        value: string | number;
        hint?: string;
        tone?: 'emerald' | 'sky' | 'amber' | 'rose' | 'violet' | 'neutral';
        icon?: Component | null;
        clickable?: boolean;
    }>(),
    {
        hint: '',
        tone: 'neutral',
        icon: null,
        clickable: false,
    },
);

const emit = defineEmits<{
    select: [];
}>();

const toneStyles = computed(() => {
    const map = {
        emerald: {
            border: 'border-emerald-500/35',
            iconWrap:
                'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300',
        },
        sky: {
            border: 'border-sky-500/35',
            iconWrap:
                'bg-sky-500/10 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300',
        },
        amber: {
            border: 'border-amber-500/35',
            iconWrap:
                'bg-amber-500/10 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300',
        },
        rose: {
            border: 'border-rose-500/35',
            iconWrap:
                'bg-rose-500/10 text-rose-700 dark:bg-rose-400/15 dark:text-rose-300',
        },
        violet: {
            border: 'border-violet-500/35',
            iconWrap:
                'bg-violet-500/10 text-violet-700 dark:bg-violet-400/15 dark:text-violet-300',
        },
        neutral: {
            border: 'border-border/70',
            iconWrap: 'bg-muted text-foreground',
        },
    } as const;

    return map[props.tone];
});

function onClick(): void {
    if (!props.clickable) {
        return;
    }

    emit('select');
}
</script>

<template>
    <button
        type="button"
        class="group rounded-xl border bg-card text-start shadow-none transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
        :class="[
            toneStyles.border,
            clickable ? 'hover:bg-muted/35' : 'cursor-default',
        ]"
        :aria-disabled="!clickable"
        @click="onClick"
    >
        <div class="flex gap-3 p-4">
            <div
                v-if="icon"
                class="flex size-11 shrink-0 items-center justify-center rounded-lg"
                :class="toneStyles.iconWrap"
            >
                <component
                    :is="icon"
                    class="size-5 shrink-0"
                    aria-hidden="true"
                />
            </div>
            <div class="min-w-0 flex-1">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ title }}
                </p>
                <p
                    class="mt-1 text-2xl font-semibold text-foreground tabular-nums"
                >
                    {{ value }}
                </p>
                <p v-if="hint" class="mt-1 text-xs text-muted-foreground">
                    {{ hint }}
                </p>
            </div>
        </div>
    </button>
</template>
