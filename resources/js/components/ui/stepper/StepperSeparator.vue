<script lang="ts" setup>
import type { StepperSeparatorProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { StepperSeparator, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = withDefaults(
  defineProps<
    StepperSeparatorProps & {
      class?: HTMLAttributes["class"]
      /**
       * When true, skip default bar backgrounds (muted / completed accent) so
       * `class` fully controls connector color—avoids group-state overriding page utilities.
       */
      suppressDefaultBarTint?: boolean
    }
  >(),
  { suppressDefaultBarTint: false },
)

const delegatedProps = reactiveOmit(props, "class", "suppressDefaultBarTint")

const forwarded = useForwardProps(delegatedProps)

const separatorClass = computed(() =>
  props.suppressDefaultBarTint
    ? cn("group-data-[disabled]:opacity-50", props.class)
    : cn(
        "bg-muted",
        "group-data-[disabled]:bg-muted group-data-[disabled]:opacity-50",
        "group-data-[state=completed]:bg-accent",
        props.class,
      ),
)
</script>

<template>
  <StepperSeparator
    v-bind="forwarded"
    :class="separatorClass"
  />
</template>
