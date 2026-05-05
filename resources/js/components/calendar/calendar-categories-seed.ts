/**
 * Default rows for the calendar categories settings dialog (frontend-only until API exists).
 * `id` is stable for seeded rows; new rows use generated ids.
 */
export type CalendarCategoryRow = {
    id: number;
    name: string;
    colorKey: CalendarCategoryColorKey;
};

export type CalendarCategoryColorKey =
    | 'teal'
    | 'blue'
    | 'indigo'
    | 'violet'
    | 'fuchsia'
    | 'rose'
    | 'orange'
    | 'emerald'
    | 'slate';

export type CalendarCategoryColorOption = {
    key: CalendarCategoryColorKey;
    label: string;
    swatchClass: string;
};

export const calendarCategoryColorOptions: CalendarCategoryColorOption[] = [
    { key: 'teal', label: 'Teal', swatchClass: 'bg-teal-500' },
    { key: 'blue', label: 'Blue', swatchClass: 'bg-blue-500' },
    { key: 'indigo', label: 'Indigo', swatchClass: 'bg-indigo-500' },
    { key: 'violet', label: 'Violet', swatchClass: 'bg-violet-500' },
    { key: 'fuchsia', label: 'Fuchsia', swatchClass: 'bg-fuchsia-500' },
    { key: 'rose', label: 'Rose', swatchClass: 'bg-rose-500' },
    { key: 'orange', label: 'Orange', swatchClass: 'bg-orange-500' },
    { key: 'emerald', label: 'Emerald', swatchClass: 'bg-emerald-500' },
    { key: 'slate', label: 'Slate', swatchClass: 'bg-slate-500' },
];

export const defaultCalendarCategoriesSeed: CalendarCategoryRow[] = [
    { id: 1, name: 'Planning', colorKey: 'blue' },
    { id: 2, name: 'Meeting', colorKey: 'indigo' },
    { id: 3, name: 'Review', colorKey: 'violet' },
    { id: 4, name: 'Training', colorKey: 'fuchsia' },
    { id: 5, name: 'Deadline', colorKey: 'rose' },
    { id: 6, name: 'Announcement', colorKey: 'teal' },
    { id: 7, name: 'Operations', colorKey: 'emerald' },
    { id: 8, name: 'Compliance', colorKey: 'slate' },
    { id: 9, name: 'Incident', colorKey: 'orange' },
    { id: 10, name: 'Leave', colorKey: 'emerald' },
    { id: 11, name: 'Team Building', colorKey: 'teal' },
    { id: 12, name: 'Audit', colorKey: 'slate' },
];
