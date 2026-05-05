<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type CalendarDay = {
    iso: string;
    date: Date;
    dayNumber: number;
    isCurrentMonth: boolean;
    isWeekend: boolean;
    isToday: boolean;
};

const props = withDefaults(
    defineProps<{
        displayDate: Date;
        weekStartsOn?: 0 | 1;
        readonly?: boolean;
    }>(),
    {
        weekStartsOn: 1,
        readonly: false,
    },
);

const emit = defineEmits<{
    'select-date': [value: Date];
}>();

const weekdayLabels = computed(() => {
    const labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    if (props.weekStartsOn === 1) {
        return [...labels.slice(1), labels[0]];
    }

    return labels;
});

const gridDays = computed<CalendarDay[]>(() => {
    const year = props.displayDate.getFullYear();
    const month = props.displayDate.getMonth();

    const firstOfMonth = new Date(year, month, 1);
    const firstWeekday = firstOfMonth.getDay();
    const offset =
        props.weekStartsOn === 1 ? (firstWeekday + 6) % 7 : firstWeekday;

    const startDate = new Date(year, month, 1 - offset);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(
            startDate.getFullYear(),
            startDate.getMonth(),
            startDate.getDate() + index,
        );

        const iso = [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');

        const isToday = isSameDay(date, new Date());

        return {
            iso,
            date,
            dayNumber: date.getDate(),
            isCurrentMonth: date.getMonth() === month,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            isToday,
        };
    });
});

const weeks = computed(() => {
    const chunked: CalendarDay[][] = [];

    for (let row = 0; row < gridDays.value.length; row += 7) {
        chunked.push(gridDays.value.slice(row, row + 7));
    }

    return chunked;
});

function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function activateDayCell(date: Date): void {
    if (props.readonly) {
        return;
    }

    emit('select-date', date);
}

function onDayCellKeydown(event: KeyboardEvent, date: Date): void {
    if (props.readonly) {
        return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        emit('select-date', date);
    }
}
</script>

<template>
    <section
        class="flex h-full min-h-0 flex-col overflow-hidden border border-sidebar-border/70 bg-background"
    >
        <div
            class="grid grid-cols-7 border-b border-sidebar-border/70 bg-accent/45"
        >
            <div
                v-for="label in weekdayLabels"
                :key="label"
                class="border-r border-sidebar-border/70 bg-accent/45 px-3 py-2 text-center font-sans text-sm font-medium text-muted-foreground tabular-nums last:border-r-0"
            >
                {{ label }}
            </div>
        </div>

        <div class="grid h-full min-h-0 flex-1 grid-rows-6">
            <div
                v-for="(week, weekIndex) in weeks"
                :key="`week-${weekIndex}`"
                class="grid h-full min-h-0 grid-cols-7"
            >
                <div
                    v-for="day in week"
                    :key="day.iso"
                    role="button"
                    :tabindex="readonly ? -1 : 0"
                    :aria-disabled="readonly ? true : undefined"
                    :aria-label="day.date.toDateString()"
                    :class="
                        cn(
                            'group relative h-full min-h-0 border-r border-b border-sidebar-border/70 p-2 text-left align-top transition-colors last:border-r-0 focus:outline-none focus-visible:outline-none',
                            readonly
                                ? 'cursor-default'
                                : 'cursor-pointer hover:bg-muted/35 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
                            !day.isCurrentMonth &&
                                !day.isToday &&
                                'text-muted-foreground',
                            !day.isCurrentMonth &&
                                !day.isToday &&
                                !day.isWeekend &&
                                'bg-[linear-gradient(hsl(var(--muted)/0.22),hsl(var(--muted)/0.22)),repeating-linear-gradient(135deg,transparent,transparent_6px,hsl(var(--muted)/0.2)_6px,hsl(var(--muted)/0.2)_8px)]',
                            day.isWeekend &&
                                day.isCurrentMonth &&
                                !day.isToday &&
                                'bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,hsl(var(--muted)/0.35)_6px,hsl(var(--muted)/0.35)_8px)]',
                            day.isWeekend &&
                                !day.isCurrentMonth &&
                                !day.isToday &&
                                'bg-[linear-gradient(hsl(var(--muted)/0.18),hsl(var(--muted)/0.18)),repeating-linear-gradient(135deg,transparent,transparent_6px,hsl(var(--muted)/0.3)_6px,hsl(var(--muted)/0.3)_8px)]',
                            day.isToday &&
                                'z-10 bg-primary/3 ring-1 ring-primary/10 ring-inset hover:bg-primary/6',
                        )
                    "
                    @click="activateDayCell(day.date)"
                    @keydown="onDayCellKeydown($event, day.date)"
                >
                    <span
                        class="absolute top-2 left-2 font-sans text-sm leading-none tabular-nums"
                        :class="
                            cn(
                                day.isCurrentMonth
                                    ? 'text-foreground'
                                    : 'text-muted-foreground',
                            )
                        "
                    >
                        {{ day.dayNumber }}
                    </span>

                    <div class="absolute inset-0 overflow-hidden px-2 pb-2">
                        <slot
                            name="day-cell"
                            :day="day.date"
                            :is-current-month="day.isCurrentMonth"
                            :is-weekend="day.isWeekend"
                            :is-selected="day.isToday"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
