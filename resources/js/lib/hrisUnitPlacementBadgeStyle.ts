/**
 * Shared placement-style badge visuals (employee schedule, leave/overtime mocks).
 */

export function normalizeHexColor(
    value: string | null | undefined,
): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim().toLowerCase();

    return /^#[0-9a-f]{6}$/.test(normalized) ? normalized : null;
}

export function placementBadgeBorderStyle(
    color: string | null | undefined,
): Record<string, string> | undefined {
    const hex = normalizeHexColor(color);
    if (hex === null) {
        return undefined;
    }

    return {
        borderColor: hex,
    };
}

export function placementBadgeToneClassFromColor(
    unitTypeColor: string | null | undefined,
): string {
    return normalizeHexColor(unitTypeColor) !== null
        ? 'border-2 bg-muted/40 text-foreground'
        : 'border-border/60 bg-muted/40 text-foreground';
}
