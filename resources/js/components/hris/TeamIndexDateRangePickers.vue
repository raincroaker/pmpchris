<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { parseDate } from '@internationalized/date';
import { Calendar, ChevronDown } from 'lucide-vue-next';
import { computed, ref, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar as DatePickerCalendar } from '@/components/ui/calendar';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

const props = withDefaults(
    defineProps<{
        /**
         * One row From/To (no wrap). Narrower triggers so both fit beside column headers/popovers.
         */
        compactRow?: boolean;
        /**
         * Labels above controls, stacked (e.g. column filter popovers).
         */
        stacked?: boolean;
    }>(),
    { compactRow: false, stacked: false },
);

const fromFieldId = `${useId()}-from`;
const toFieldId = `${useId()}-to`;

const dateFrom = defineModel<string>('dateFrom', { required: true });
const dateTo = defineModel<string>('dateTo', { required: true });

const fromOpen = ref(false);
const toOpen = ref(false);

const calendarFrom = computed(() => parseDate(dateFrom.value));
const calendarTo = computed(() => parseDate(dateTo.value));

function formatLabel(iso: string): string {
    return new Date(`${iso}T12:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function onFromSelect(v: DateValue | undefined): void {
    if (!v) {
        return;
    }
    dateFrom.value = v.toString();
    if (dateTo.value < dateFrom.value) {
        dateTo.value = dateFrom.value;
    }
    fromOpen.value = false;
}

function onToSelect(v: DateValue | undefined): void {
    if (!v) {
        return;
    }
    dateTo.value = v.toString();
    if (dateFrom.value > dateTo.value) {
        dateFrom.value = dateTo.value;
    }
    toOpen.value = false;
}
const rowClass = computed(() => {
    if (props.stacked) {
        return 'flex w-full min-w-0 flex-col gap-3';
    }
    if (props.compactRow) {
        return 'flex flex-nowrap items-center gap-3';
    }

    return 'flex flex-wrap items-center gap-x-4 gap-y-2';
});

const triggerClass = computed(() => {
    if (props.stacked) {
        return 'h-9 w-full justify-between gap-2 px-3 font-normal';
    }
    if (props.compactRow) {
        return 'h-9 min-w-[9.75rem] max-w-[11rem] justify-between gap-1.5 px-2.5 font-normal sm:max-w-none sm:min-w-36';
    }

    return 'h-9 min-w-42 justify-between gap-2 font-normal';
});
</script>

<template>
    <div :class="rowClass">
        <div
            :class="
                stacked
                    ? 'grid w-full min-w-0 gap-2'
                    : 'flex min-w-0 shrink-0 items-center gap-2'
            "
        >
            <Label
                v-if="stacked"
                class="text-sm font-medium text-muted-foreground"
                :for="fromFieldId"
                >From</Label
            >
            <span v-else class="shrink-0 text-sm text-muted-foreground"
                >From</span
            >
            <Popover v-model:open="fromOpen">
                <PopoverTrigger as-child>
                    <Button
                        :id="stacked ? fromFieldId : undefined"
                        type="button"
                        variant="outline"
                        :class="triggerClass"
                        aria-label="Filter from date"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Calendar
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="truncate tabular-nums">{{
                                formatLabel(dateFrom)
                            }}</span>
                        </span>
                        <ChevronDown class="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-auto overflow-hidden p-0" align="end">
                    <DatePickerCalendar
                        layout="month-and-year"
                        :model-value="calendarFrom"
                        :max-value="calendarTo"
                        @update:model-value="onFromSelect"
                    />
                </PopoverContent>
            </Popover>
        </div>

        <div
            :class="
                stacked
                    ? 'grid w-full min-w-0 gap-2'
                    : 'flex min-w-0 shrink-0 items-center gap-2'
            "
        >
            <Label
                v-if="stacked"
                class="text-sm font-medium text-muted-foreground"
                :for="toFieldId"
                >To</Label
            >
            <span v-else class="shrink-0 text-sm text-muted-foreground"
                >To</span
            >
            <Popover v-model:open="toOpen">
                <PopoverTrigger as-child>
                    <Button
                        :id="stacked ? toFieldId : undefined"
                        type="button"
                        variant="outline"
                        :class="triggerClass"
                        aria-label="Filter to date"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Calendar
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="truncate tabular-nums">{{
                                formatLabel(dateTo)
                            }}</span>
                        </span>
                        <ChevronDown class="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-auto overflow-hidden p-0" align="end">
                    <DatePickerCalendar
                        layout="month-and-year"
                        :model-value="calendarTo"
                        :min-value="calendarFrom"
                        @update:model-value="onToSelect"
                    />
                </PopoverContent>
            </Popover>
        </div>
    </div>
</template>
