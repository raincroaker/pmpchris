/**
 * Holiday type definitions for the Holidays Calendar (frontend-only until API exists).
 * Built-in ids are stable; custom rows use generated ids from the settings dialog.
 *
 * Holiday types use a dedicated 6-color palette (distinct from calendar event categories).
 */
import type { HolidayPayPolicy } from '@/pages/Attendance/attendanceRulesTypes';

export type HolidayTypeKind = 'builtin' | 'custom';

/** Six saturated hues for holiday chips and sheet rows (picker + styling). */
export type HolidayTypeColorKey =
    | 'cyan'
    | 'amber'
    | 'lime'
    | 'rose'
    | 'violet'
    | 'sky';

export type HolidayTypeColorOption = {
    key: HolidayTypeColorKey;
    label: string;
    swatchClass: string;
};

/** Picker options only — use with holiday type create/edit, not calendar event categories. */
export const holidayTypeColorOptions: HolidayTypeColorOption[] = [
    { key: 'cyan', label: 'Cyan', swatchClass: 'bg-cyan-500' },
    { key: 'amber', label: 'Amber', swatchClass: 'bg-amber-500' },
    { key: 'lime', label: 'Lime', swatchClass: 'bg-lime-500' },
    { key: 'rose', label: 'Rose', swatchClass: 'bg-rose-500' },
    { key: 'violet', label: 'Violet', swatchClass: 'bg-violet-500' },
    { key: 'sky', label: 'Sky', swatchClass: 'bg-sky-500' },
];

export type HolidayTypeDefinition = {
    id: string;
    name: string;
    kind: HolidayTypeKind;
    colorKey: HolidayTypeColorKey;
    /** Pay behavior for holidays using this type (set in Holiday types settings). */
    pay_policy: HolidayPayPolicy;
    /** Required when `pay_policy` is Custom Multiplier. */
    custom_multiplier: string | null;
    /** Optional note shown in settings (e.g. premium handling). */
    premiumNote?: string | null;
};

export type HolidayTypeChipStyles = {
    chip: string;
    icon: string;
};

const FALLBACK_HOLIDAY_COLOR_KEY: HolidayTypeColorKey = 'sky';

const HOLIDAY_COLOR_KEY_CHIP: Record<
    HolidayTypeColorKey,
    HolidayTypeChipStyles
> = {
    cyan: {
        chip: 'border-cyan-500/30 bg-cyan-500/12 hover:bg-cyan-500/18',
        icon: 'text-cyan-600 dark:text-cyan-400',
    },
    amber: {
        chip: 'border-amber-500/30 bg-amber-500/12 hover:bg-amber-500/18',
        icon: 'text-amber-600 dark:text-amber-400',
    },
    lime: {
        chip: 'border-lime-500/30 bg-lime-500/12 hover:bg-lime-500/18',
        icon: 'text-lime-600 dark:text-lime-400',
    },
    rose: {
        chip: 'border-rose-500/30 bg-rose-500/12 hover:bg-rose-500/18',
        icon: 'text-rose-600 dark:text-rose-400',
    },
    violet: {
        chip: 'border-violet-500/30 bg-violet-500/12 hover:bg-violet-500/18',
        icon: 'text-violet-600 dark:text-violet-400',
    },
    sky: {
        chip: 'border-sky-500/30 bg-sky-500/12 hover:bg-sky-500/18',
        icon: 'text-sky-600 dark:text-sky-400',
    },
};

export const holidayCellChipButtonBase =
    'inline-flex h-5 w-full cursor-pointer items-center gap-1 rounded-sm border px-1 text-[11px] font-medium text-foreground transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1';

export type HolidaySheetListTone = {
    /** List row outer button (tinted like Company Events sheet rows). */
    card: string;
    /** Badge on the row (muted label style). */
    badge: string;
};

const HOLIDAY_LIST_TONES: Record<HolidayTypeColorKey, HolidaySheetListTone> = {
    cyan: {
        card: 'border-cyan-500/30 bg-cyan-500/10 hover:bg-cyan-500/16',
        badge: 'border-cyan-500/40 bg-cyan-500/16 text-cyan-700 dark:text-cyan-300',
    },
    amber: {
        card: 'border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/16',
        badge: 'border-amber-500/40 bg-amber-500/16 text-amber-700 dark:text-amber-300',
    },
    lime: {
        card: 'border-lime-500/30 bg-lime-500/10 hover:bg-lime-500/16',
        badge: 'border-lime-500/40 bg-lime-500/16 text-lime-700 dark:text-lime-300',
    },
    rose: {
        card: 'border-rose-500/30 bg-rose-500/10 hover:bg-rose-500/16',
        badge: 'border-rose-500/40 bg-rose-500/16 text-rose-700 dark:text-rose-300',
    },
    violet: {
        card: 'border-violet-500/30 bg-violet-500/10 hover:bg-violet-500/16',
        badge: 'border-violet-500/40 bg-violet-500/16 text-violet-700 dark:text-violet-300',
    },
    sky: {
        card: 'border-sky-500/30 bg-sky-500/10 hover:bg-sky-500/16',
        badge: 'border-sky-500/40 bg-sky-500/16 text-sky-700 dark:text-sky-300',
    },
};

const FALLBACK_LIST_TONE: HolidaySheetListTone = {
    card: 'border-primary/30 bg-primary/10 hover:bg-primary/16',
    badge: 'border-primary/40 bg-primary/16 text-primary',
};

export function getHolidayTypeChipStyles(
    colorKey: HolidayTypeColorKey | undefined,
): HolidayTypeChipStyles {
    if (!colorKey || !HOLIDAY_COLOR_KEY_CHIP[colorKey]) {
        return HOLIDAY_COLOR_KEY_CHIP[FALLBACK_HOLIDAY_COLOR_KEY];
    }

    return HOLIDAY_COLOR_KEY_CHIP[colorKey];
}

export function getHolidaySheetListToneClasses(
    colorKey: HolidayTypeColorKey | undefined,
): HolidaySheetListTone {
    if (!colorKey || !HOLIDAY_LIST_TONES[colorKey]) {
        return FALLBACK_LIST_TONE;
    }

    return HOLIDAY_LIST_TONES[colorKey];
}

export function resolveHolidayTypeName(
    types: HolidayTypeDefinition[],
    typeId: string,
): string {
    const found = types.find((t) => t.id === typeId);

    return found?.name ?? 'Unknown type';
}

export function resolveHolidayType(
    types: HolidayTypeDefinition[],
    typeId: string,
): HolidayTypeDefinition | undefined {
    return types.find((t) => t.id === typeId);
}

/** Display line for pay behavior as configured on the holiday type. */
export function formatHolidayTypePaySummary(
    type: HolidayTypeDefinition | undefined,
): string {
    if (!type) {
        return '';
    }

    if (type.pay_policy === 'Custom Multiplier') {
        const m = type.custom_multiplier?.trim();

        return m !== undefined && m !== ''
            ? `Custom Multiplier (${m})`
            : 'Custom Multiplier';
    }

    return type.pay_policy;
}

/**
 * Built-in ids stay stable for holidays referencing `type_id`.
 * Pay defaults align with typical PH Labor Code / DOLE summaries (verify against policy).
 */
export const defaultHolidayTypesSeed: HolidayTypeDefinition[] = [
    {
        id: 'builtin-regular',
        name: 'Regular Holiday',
        kind: 'builtin',
        colorKey: 'lime',
        pay_policy: 'Double Pay',
        custom_multiplier: null,
        premiumNote:
            'Typical PH rule: 200% of daily wage when the regular holiday is worked (first 8 hours). Check DOLE / CBA for exact stacks.',
    },
    {
        id: 'builtin-special-non-working',
        name: 'Special Non-Working Holiday',
        kind: 'builtin',
        colorKey: 'amber',
        pay_policy: 'Custom Multiplier',
        custom_multiplier: '1.30x',
        premiumNote:
            'Typical PH rule when worked: 130% of daily wage (100% + 30%). Unworked is often “no work, no pay” unless company policy or CBA says otherwise.',
    },
    {
        id: 'builtin-special-working',
        name: 'Special Working Holiday',
        kind: 'builtin',
        colorKey: 'sky',
        pay_policy: 'No Premium',
        custom_multiplier: null,
        premiumNote:
            'Typical PH rule: no statutory holiday premium—pay as an ordinary workday unless policy adds more.',
    },
];
