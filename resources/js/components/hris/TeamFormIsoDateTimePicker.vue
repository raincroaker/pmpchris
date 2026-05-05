<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { parseDate } from '@internationalized/date';
import { Calendar as CalendarIcon, ChevronDown } from 'lucide-vue-next';
import { computed, ref, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar as DatePickerCalendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { datetimeLocalValueToIso } from '@/lib/teamHrFormApi';

/** Local datetime string for `<input type="datetime-local" />` (`YYYY-MM-DDTHH:mm`). */
const modelValue = defineModel<string>({ required: true });

const props = defineProps<{
    ariaLabel: string;
    /** For `<label for="…">` pairing with the trigger button. */
    id?: string;
}>();

const open = ref(false);
const timeInputId = useId();

const calendarValue = computed(() => {
    const d = modelValue.value.split('T')[0];
    if (d && /^\d{4}-\d{2}-\d{2}$/.test(d)) {
        return parseDate(d);
    }

    return parseDate(new Date().toISOString().slice(0, 10));
});

const timeModel = computed({
    get(): string {
        if (!modelValue.value.includes('T')) {
            return '09:00';
        }

        const part = modelValue.value.split('T')[1];

        return part ? part.slice(0, 5) : '09:00';
    },
    set(v: string) {
        const datePart =
            modelValue.value.split('T')[0] ||
            new Date().toISOString().slice(0, 10);
        modelValue.value = `${datePart}T${v}`;
    },
});

const triggerLabel = computed(() => {
    const raw = modelValue.value.trim();
    if (!raw) {
        return 'Select date & time';
    }

    const iso = datetimeLocalValueToIso(raw);
    if (!iso) {
        return 'Select date & time';
    }

    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
});

function onSelectDate(v: DateValue | undefined): void {
    if (!v) {
        return;
    }

    const dateStr = v.toString();
    const timePart = modelValue.value.includes('T')
        ? modelValue.value.split('T')[1].slice(0, 5)
        : '09:00';
    modelValue.value = `${dateStr}T${timePart}`;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="props.id"
                type="button"
                variant="outline"
                class="h-9 w-full min-w-0 justify-between gap-2 font-normal"
                :aria-label="props.ariaLabel"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <CalendarIcon
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="truncate text-start tabular-nums">{{
                        triggerLabel
                    }}</span>
                </span>
                <ChevronDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto overflow-hidden p-0" align="start">
            <div class="flex flex-col">
                <DatePickerCalendar
                    layout="month-and-year"
                    :model-value="calendarValue"
                    @update:model-value="onSelectDate"
                />
                <div
                    class="flex items-center gap-2 border-t border-border px-3 py-2"
                >
                    <Label
                        class="shrink-0 text-xs text-muted-foreground"
                        :for="timeInputId"
                        >Time</Label
                    >
                    <Input
                        :id="timeInputId"
                        v-model="timeModel"
                        type="time"
                        step="60"
                        class="h-9 flex-1 font-mono tabular-nums"
                    />
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
