<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ref } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const scrollEl = ref<HTMLDivElement | null>(null)

defineExpose({
  scrollEl,
})
</script>

<template>
  <div
    ref="scrollEl"
    data-slot="sidebar-content"
    data-sidebar="content"
    :class="
      cn(
        'flex min-h-0 flex-1 flex-col gap-2 overflow-auto [scrollbar-gutter:stable] group-data-[collapsible=icon]:overflow-hidden group-data-[collapsible=icon]:[scrollbar-gutter:auto] pl-3 group-data-[collapsible=icon]:pl-0',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
