<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { parseDate } from '@internationalized/date';
import { Calendar as CalendarIcon, ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar as DatePickerCalendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatCalendarTriggerFromIsoYmd } from '@/lib/formatCalendarTriggerDate';

const modelValue = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        ariaLabel: string;
        /** Inclusive lower bound (`YYYY-MM-DD`); dates before this are not selectable. */
        minIso?: string | null;
        /** Inclusive upper bound (`YYYY-MM-DD`); dates after this are not selectable. */
        maxIso?: string | null;
        /** Dense trigger for lists (e.g. per-row leave days). */
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const open = ref(false);

const calendarValue = computed((): DateValue | undefined => {
    const raw = modelValue.value?.trim() ?? '';
    if (raw.length < 10) {
        return undefined;
    }
    try {
        return parseDate(raw.slice(0, 10));
    } catch {
        return undefined;
    }
});

const minCalendarValue = computed<DateValue | undefined>(() =>
    props.minIso ? parseDate(props.minIso) : undefined,
);

const maxCalendarValue = computed<DateValue | undefined>(() =>
    props.maxIso ? parseDate(props.maxIso) : undefined,
);

const triggerDateLabel = computed(() =>
    formatCalendarTriggerFromIsoYmd(modelValue.value),
);

const hasChosenDate = computed(() => triggerDateLabel.value !== '');

function onSelect(v: DateValue | undefined): void {
    if (!v) {
        return;
    }
    modelValue.value = v.toString();
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                :class="
                    compact
                        ? 'h-8 w-auto min-w-36 shrink-0 justify-between gap-1.5 border-input bg-transparent px-2.5 text-sm font-normal dark:bg-input/30'
                        : 'h-9 w-full min-w-0 justify-between gap-2 border-input bg-transparent text-sm font-normal dark:bg-input/30'
                "
                :aria-label="ariaLabel"
            >
                <span class="flex min-w-0 items-center gap-1.5">
                    <CalendarIcon
                        :class="
                            compact
                                ? 'size-3.5 shrink-0 text-muted-foreground'
                                : 'size-4 shrink-0 text-muted-foreground'
                        "
                        aria-hidden="true"
                    />
                    <span
                        class="truncate tabular-nums"
                        :class="
                            hasChosenDate
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ hasChosenDate ? triggerDateLabel : 'Select date' }}
                    </span>
                </span>
                <ChevronDown
                    :class="
                        compact
                            ? 'size-3.5 shrink-0 opacity-50'
                            : 'size-4 shrink-0 opacity-50'
                    "
                />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto overflow-hidden p-0" align="start">
            <DatePickerCalendar
                layout="month-and-year"
                :model-value="calendarValue"
                :min-value="minCalendarValue"
                :max-value="maxCalendarValue"
                @update:model-value="onSelect"
            />
        </PopoverContent>
    </Popover>
</template>
