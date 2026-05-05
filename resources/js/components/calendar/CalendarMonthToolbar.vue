<script setup lang="ts">
import {
    Calendar,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Plus,
    Settings,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { CalendarMonthOption } from '@/composables/useCalendarMonthView';

withDefaults(
    defineProps<{
        title: string;
        displayDate: Date;
        pickerYear: number;
        monthYearLabel: string;
        monthOptions: CalendarMonthOption[];
        /** Primary side-panel button label (e.g. "Events" or "Holidays"). */
        listButtonLabel?: string;
        newButtonLabel?: string;
        newAriaLabel?: string;
        showSettings?: boolean;
        showAdd?: boolean;
    }>(),
    {
        listButtonLabel: 'Events',
        newButtonLabel: 'New',
        newAriaLabel: 'New event',
        showSettings: false,
        showAdd: true,
    },
);

const emit = defineEmits<{
    'prev-month': [];
    'next-month': [];
    'shift-picker-year': [offset: number];
    'select-month': [monthValue: string];
    add: [];
    events: [];
    settings: [];
}>();
</script>

<template>
    <div
        class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 py-1.5 pr-2 pl-0"
    >
        <div class="flex items-center gap-1">
            <Popover>
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-9 cursor-pointer rounded-md px-3 text-xl leading-none font-semibold text-foreground hover:bg-muted/40"
                        aria-label="Select month and year"
                    >
                        <Calendar class="size-6 text-muted-foreground" />
                        <span>{{ monthYearLabel }}</span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-72 p-3" align="start">
                    <div class="mb-2 flex items-center justify-between">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="cursor-pointer rounded-md"
                            aria-label="Previous year"
                            @click="emit('shift-picker-year', -1)"
                        >
                            <ChevronLeft class="size-4" />
                        </Button>
                        <p class="text-sm font-semibold text-foreground">
                            {{ pickerYear }}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="cursor-pointer rounded-md"
                            aria-label="Next year"
                            @click="emit('shift-picker-year', 1)"
                        >
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                    <div class="grid grid-cols-3 gap-1">
                        <Button
                            v-for="month in monthOptions"
                            :key="month.value"
                            type="button"
                            variant="ghost"
                            class="h-9 min-h-9 cursor-pointer justify-center rounded-md px-2 text-center text-sm font-medium"
                            :class="[
                                Number.parseInt(month.value, 10) ===
                                    displayDate.getMonth() &&
                                pickerYear === displayDate.getFullYear()
                                    ? 'bg-muted'
                                    : '',
                            ]"
                            @click="emit('select-month', month.value)"
                        >
                            {{ month.label.slice(0, 3) }}
                        </Button>
                    </div>
                </PopoverContent>
            </Popover>

            <Button
                type="button"
                variant="outline"
                size="icon"
                class="cursor-pointer rounded-md"
                aria-label="Previous month"
                @click="emit('prev-month')"
            >
                <ChevronLeft class="size-4" />
            </Button>

            <Button
                type="button"
                variant="outline"
                size="icon"
                class="cursor-pointer rounded-md"
                aria-label="Next month"
                @click="emit('next-month')"
            >
                <ChevronRight class="size-4" />
            </Button>
        </div>

        <p
            class="text-center text-base font-semibold tracking-wide text-muted-foreground"
        >
            {{ title }}
        </p>

        <div class="ml-auto flex items-center gap-2">
            <slot name="pre-actions" />

            <Button type="button" variant="default" @click="emit('events')">
                <CalendarDays class="size-4" />
                <span class="mr-1">{{ listButtonLabel }}</span>
            </Button>

            <Button
                v-if="showAdd"
                type="button"
                variant="outline"
                class="rounded-md"
                :aria-label="newAriaLabel"
                @click="emit('add')"
            >
                <Plus class="size-4" />
                <span class="mr-1">{{ newButtonLabel }}</span>
            </Button>

            <Button
                v-if="showSettings"
                type="button"
                variant="outline"
                size="icon"
                class="rounded-md"
                aria-label="Calendar settings"
                @click="emit('settings')"
            >
                <Settings class="size-4" />
            </Button>
        </div>
    </div>
</template>
