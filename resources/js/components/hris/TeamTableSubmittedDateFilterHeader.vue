<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ListFilter,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import TeamIndexDateRangePickers from '@/components/hris/TeamIndexDateRangePickers.vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    isoFirstDayOfMonth,
    isoLastDayOfMonth,
} from '@/lib/calendarMonthRange';

export type SubmittedDateRange = { from: string; to: string };

const props = withDefaults(
    defineProps<{
        /** Null means no date filter. */
        modelValue: SubmittedDateRange | null;
        /** Column title next to the filter control. */
        columnTitle?: string;
        /** Show sortable trigger next to filter icon. */
        enableSort?: boolean;
        /** Optional current sort direction for this column. */
        sortDirection?: 'asc' | 'desc' | null;
    }>(),
    {
        columnTitle: 'Approve date',
        enableSort: false,
        sortDirection: null,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: SubmittedDateRange | null];
    toggleSort: [];
}>();

const open = ref(false);
const draftFrom = ref(isoFirstDayOfMonth(new Date()));
const draftTo = ref(isoLastDayOfMonth(new Date()));

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }
    if (props.modelValue) {
        draftFrom.value = props.modelValue.from;
        draftTo.value = props.modelValue.to;
    } else {
        const t = new Date();
        draftFrom.value = isoFirstDayOfMonth(t);
        draftTo.value = isoLastDayOfMonth(t);
    }
});

const isActive = computed(() => props.modelValue !== null);

function apply(): void {
    emit('update:modelValue', { from: draftFrom.value, to: draftTo.value });
    open.value = false;
}

function removeAppliedFilter(event?: MouseEvent): void {
    event?.stopPropagation();
    emit('update:modelValue', null);
    open.value = false;
}

const triggerAriaLabel = computed(() => {
    if (!props.modelValue) {
        return `${props.columnTitle} filter: all. Open to restrict by date.`;
    }

    return `${props.columnTitle} filter: ${props.modelValue.from} to ${props.modelValue.to}. Open to change.`;
});

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
    <div class="flex min-w-40 items-center gap-1">
        <span class="min-w-0 truncate font-medium text-muted-foreground">
            {{ columnTitle }}
        </span>
        <Button
            v-if="enableSort"
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
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    :class="[
                        'size-8 shrink-0 p-0',
                        isActive
                            ? 'text-primary hover:text-primary'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :aria-label="triggerAriaLabel"
                >
                    <ListFilter class="size-4" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <Button
                v-if="isActive"
                type="button"
                variant="ghost"
                class="size-8 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                :aria-label="`Clear ${columnTitle} filter`"
                @click="removeAppliedFilter"
            >
                <X class="size-4" aria-hidden="true" />
            </Button>
            <PopoverContent
                align="start"
                class="w-auto min-w-56 space-y-3 p-3 sm:min-w-64"
            >
                <TeamIndexDateRangePickers
                    v-model:date-from="draftFrom"
                    v-model:date-to="draftTo"
                    stacked
                />
                <div class="flex flex-wrap justify-end gap-2 pt-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="removeAppliedFilter"
                    >
                        All dates
                    </Button>
                    <Button type="button" size="sm" @click="apply">
                        Apply
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>
