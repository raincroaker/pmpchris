<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        columnTitle: string;
        sortDirection: 'asc' | 'desc' | null;
    }>(),
    {
        sortDirection: null,
    },
);

const emit = defineEmits<{
    toggleSort: [];
}>();

const sortAriaLabel = computed(() => {
    const directionText =
        props.sortDirection === null
            ? 'no sort'
            : props.sortDirection === 'asc'
              ? 'ascending'
              : 'descending';

    return `${props.columnTitle} sort: ${directionText}. Toggle sort direction.`;
});
</script>

<template>
    <div class="flex min-w-0 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            {{ columnTitle }}
        </span>
        <Button
            type="button"
            variant="ghost"
            class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
            :aria-label="sortAriaLabel"
            @click="emit('toggleSort')"
        >
            <ArrowUpDown
                v-if="sortDirection === null"
                class="size-4"
                aria-hidden="true"
            />
            <ArrowUp
                v-else-if="sortDirection === 'asc'"
                class="size-4"
                aria-hidden="true"
            />
            <ArrowDown v-else class="size-4" aria-hidden="true" />
        </Button>
    </div>
</template>
