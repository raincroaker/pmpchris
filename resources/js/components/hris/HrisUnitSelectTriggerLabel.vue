<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';

export type HrisUnitSelectTriggerOption = {
    value: string;
    label: string;
    code?: string | null;
};

const props = withDefaults(
    defineProps<{
        /** Value from {@link SelectValue} default slot (`modelValue`). */
        selectModelValue?: AcceptableValue | AcceptableValue[] | null;
        options: HrisUnitSelectTriggerOption[];
        /** Value representing “every unit” in {@link options} (default `all`). */
        allValue?: string;
        /** Shown when there is no matching option. */
        fallbackLabel?: string;
    }>(),
    {
        selectModelValue: undefined,
        allValue: 'all',
        fallbackLabel: 'All units',
    },
);

function modelValueToKey(
    value: AcceptableValue | AcceptableValue[] | null | undefined,
    allValue: string,
): string {
    if (value === undefined || value === null || value === '') {
        return allValue;
    }

    const single = Array.isArray(value) ? value[0] : value;

    if (single === undefined || single === null || single === '') {
        return allValue;
    }

    if (typeof single === 'object') {
        return JSON.stringify(single);
    }

    return String(single);
}

const selected = computed((): HrisUnitSelectTriggerOption | null => {
    const raw = modelValueToKey(props.selectModelValue, props.allValue);
    const match = props.options.find((o) => {
        const key =
            typeof o.value === 'object' && o.value !== null
                ? JSON.stringify(o.value)
                : String(o.value);

        return key === raw;
    });

    return match ?? null;
});

const hasCode = computed((): boolean => {
    const c = selected.value?.code;

    return typeof c === 'string' && c.trim() !== '';
});
</script>

<template>
    <div v-if="selected" class="flex min-w-0 items-baseline gap-1">
        <span class="truncate text-sm">{{ selected.label }}</span>
        <template v-if="hasCode">
            <span
                class="shrink-0 font-mono text-xs text-muted-foreground tabular-nums"
            >
                {{ selected.code }}
            </span>
        </template>
    </div>
    <span v-else class="truncate text-sm text-muted-foreground">{{
        fallbackLabel
    }}</span>
</template>
