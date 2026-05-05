/**
 * Built-in calendar cell chip colors by event category (no user picking).
 * Uses theme `primary` + `chart-*` tokens so light/dark stay consistent.
 * Title stays `text-foreground` on the chip; only border/background/hover + icon hue vary.
 */
export type CalendarEventCategoryChipStyles = {
    chip: string;
    icon: string;
};

export const calendarEventCellChipButtonBase =
    'flex w-full min-w-0 cursor-pointer items-center gap-1 rounded-md border px-1 py-0.5 text-left text-xs text-foreground transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1';

const DEFAULT_CHIP_STYLES: CalendarEventCategoryChipStyles = {
    chip: 'border-primary/20 bg-primary/10 hover:bg-primary/16',
    icon: 'text-primary',
};

type CalendarEventChipColorKey =
    | 'teal'
    | 'blue'
    | 'indigo'
    | 'violet'
    | 'fuchsia'
    | 'rose'
    | 'orange'
    | 'emerald'
    | 'slate';

const COLOR_KEY_CHIP_STYLES: Record<
    CalendarEventChipColorKey,
    CalendarEventCategoryChipStyles
> = {
    teal: {
        chip: 'border-teal-500/30 bg-teal-500/12 hover:bg-teal-500/18',
        icon: 'text-teal-600 dark:text-teal-400',
    },
    blue: {
        chip: 'border-blue-500/30 bg-blue-500/12 hover:bg-blue-500/18',
        icon: 'text-blue-600 dark:text-blue-400',
    },
    indigo: {
        chip: 'border-indigo-500/30 bg-indigo-500/12 hover:bg-indigo-500/18',
        icon: 'text-indigo-600 dark:text-indigo-400',
    },
    violet: {
        chip: 'border-violet-500/30 bg-violet-500/12 hover:bg-violet-500/18',
        icon: 'text-violet-600 dark:text-violet-400',
    },
    fuchsia: {
        chip: 'border-fuchsia-500/30 bg-fuchsia-500/12 hover:bg-fuchsia-500/18',
        icon: 'text-fuchsia-600 dark:text-fuchsia-400',
    },
    rose: {
        chip: 'border-rose-500/30 bg-rose-500/12 hover:bg-rose-500/18',
        icon: 'text-rose-600 dark:text-rose-400',
    },
    orange: {
        chip: 'border-orange-500/30 bg-orange-500/12 hover:bg-orange-500/18',
        icon: 'text-orange-600 dark:text-orange-400',
    },
    emerald: {
        chip: 'border-emerald-500/30 bg-emerald-500/12 hover:bg-emerald-500/18',
        icon: 'text-emerald-600 dark:text-emerald-400',
    },
    slate: {
        chip: 'border-slate-500/30 bg-slate-500/12 hover:bg-slate-500/18',
        icon: 'text-slate-600 dark:text-slate-400',
    },
};

function normalizeColorKey(
    colorKey: string | null | undefined,
): CalendarEventChipColorKey | null {
    if (!colorKey) {
        return null;
    }

    const normalized = colorKey.trim().toLowerCase();
    const allowed: CalendarEventChipColorKey[] = [
        'teal',
        'blue',
        'indigo',
        'violet',
        'fuchsia',
        'rose',
        'orange',
        'emerald',
        'slate',
    ];

    return allowed.includes(normalized as CalendarEventChipColorKey)
        ? (normalized as CalendarEventChipColorKey)
        : null;
}

/** Preferred: style by persisted category color key. */
export function getCalendarEventCategoryChipStylesByColorKey(
    colorKey: string | null | undefined,
): CalendarEventCategoryChipStyles {
    const normalized = normalizeColorKey(colorKey);
    if (!normalized) {
        return DEFAULT_CHIP_STYLES;
    }

    return COLOR_KEY_CHIP_STYLES[normalized] ?? DEFAULT_CHIP_STYLES;
}

/**
 * Backward-compat fallback for legacy name-based paths.
 * Kept to avoid breaking any un-migrated usage.
 */
export function getCalendarEventCategoryChipStyles(
    category: string,
): CalendarEventCategoryChipStyles {
    void category;

    return DEFAULT_CHIP_STYLES;
}
