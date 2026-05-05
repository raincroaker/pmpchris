<script setup lang="ts">
import type { AcceptableValue, SelectValueProps } from "reka-ui"
import { SelectValue } from "reka-ui"

defineSlots<{
  default(props: {
    selectedLabel: string[]
    modelValue: AcceptableValue | AcceptableValue[] | undefined
  }): unknown
}>()

const props = defineProps<SelectValueProps>()
</script>

<template>
  <SelectValue
    data-slot="select-value"
    v-bind="props"
  >
    <template #default="slotProps">
      <slot v-bind="slotProps" />
    </template>
  </SelectValue>
</template>
