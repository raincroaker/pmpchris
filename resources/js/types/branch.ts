export type Branch = {
    id: number;
    name: string;
    code?: string;
    /** Geographic area when linked; null for e.g. Head Office. */
    area_name?: string | null;
    /** Section heading in pickers (area name, unit type name, etc.). */
    group_label?: string | null;
};
