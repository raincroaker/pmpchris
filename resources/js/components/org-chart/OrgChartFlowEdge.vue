<script setup lang="ts">
import { BaseEdge } from '@vue-flow/core';
import type { EdgeProps } from '@vue-flow/core';
import { computed, useAttrs } from 'vue';

defineOptions({
    inheritAttrs: false,
});

/** Midpoint between parent bottom handle and child top handle (snapped) so sibling edges share one horizontal bus Y. */
const BUS_Y_FRACTION = 0.5;

const props = defineProps<EdgeProps>();

const attrs = useAttrs();

const busY = computed(() =>
    Math.round(
        props.sourceY + (props.targetY - props.sourceY) * BUS_Y_FRACTION,
    ),
);

const edgePath = computed(() => {
    const sx = Math.round(props.sourceX);
    const sy = Math.round(props.sourceY);
    const tx = Math.round(props.targetX);
    const ty = Math.round(props.targetY);
    const y = busY.value;
    return `M ${sx} ${sy} L ${sx} ${y} L ${tx} ${y} L ${tx} ${ty}`;
});

const labelX = computed(() => Math.round((props.sourceX + props.targetX) / 2));

const labelY = computed(() => busY.value);
</script>

<template>
    <BaseEdge
        v-bind="attrs"
        :id="id"
        :path="edgePath"
        :label-x="labelX"
        :label-y="labelY"
        :label="label"
        :label-style="labelStyle"
        :label-show-bg="labelShowBg"
        :label-bg-style="labelBgStyle"
        :label-bg-padding="labelBgPadding"
        :label-bg-border-radius="labelBgBorderRadius"
        :marker-start="markerStart"
        :marker-end="markerEnd"
        :interaction-width="interactionWidth"
    />
</template>
