export type UnitTypeColorOption = {
    key: string;
    label: string;
    hex: string;
};

export const unitTypeColorOptions: UnitTypeColorOption[] = [
    { key: 'violet', label: 'Violet', hex: '#6366f1' },
    { key: 'green', label: 'Green', hex: '#10b981' },
    { key: 'orange', label: 'Orange', hex: '#f59e0b' },
    { key: 'rose', label: 'Rose', hex: '#f43f5e' },
    { key: 'fuchsia', label: 'Fuchsia', hex: '#d946ef' },
    { key: 'lime', label: 'Lime', hex: '#65a30d' },
];

export function resolveUnitTypeColorOption(
    hex?: string | null,
): UnitTypeColorOption | null {
    if (!hex) return null;
    const normalized = hex.trim().toLowerCase();
    return (
        unitTypeColorOptions.find(
            (option) => option.hex.toLowerCase() === normalized,
        ) ?? null
    );
}

export function resolveUnitTypeColorLabel(hex?: string | null): string {
    return resolveUnitTypeColorOption(hex)?.label ?? 'No color';
}
