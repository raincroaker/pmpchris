import { computed, ref } from 'vue';

export type CalendarMonthOption = {
    value: string;
    label: string;
};

export function useCalendarMonthView(initialDate?: Date) {
    const displayDate = ref(initialDate ?? new Date());
    const pickerYear = ref(displayDate.value.getFullYear());
    const monthOptions: CalendarMonthOption[] = [
        { value: '0', label: 'January' },
        { value: '1', label: 'February' },
        { value: '2', label: 'March' },
        { value: '3', label: 'April' },
        { value: '4', label: 'May' },
        { value: '5', label: 'June' },
        { value: '6', label: 'July' },
        { value: '7', label: 'August' },
        { value: '8', label: 'September' },
        { value: '9', label: 'October' },
        { value: '10', label: 'November' },
        { value: '11', label: 'December' },
    ];

    const monthYearLabel = computed(() =>
        displayDate.value.toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric',
        }),
    );

    function shiftMonth(offset: number): void {
        const base = displayDate.value;

        displayDate.value = new Date(
            base.getFullYear(),
            base.getMonth() + offset,
            1,
        );
    }

    function shiftPickerYear(offset: number): void {
        pickerYear.value += offset;
    }

    function selectMonth(monthValue: string): void {
        const month = Number.parseInt(monthValue, 10);

        displayDate.value = new Date(pickerYear.value, month, 1);
    }

    return {
        displayDate,
        pickerYear,
        monthOptions,
        monthYearLabel,
        shiftMonth,
        shiftPickerYear,
        selectMonth,
    };
}
