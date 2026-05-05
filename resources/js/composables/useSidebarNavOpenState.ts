import { reactive } from 'vue';

/** Bump suffix if nav collapsible titles change shape (stale keys are harmless). */
export const SIDEBAR_NAV_OPEN_STORAGE_KEY = 'hris-sidebar-nav-open-v1';

function loadFromStorage(): Record<string, boolean> {
    if (typeof window === 'undefined') {
        return {};
    }
    try {
        const raw = sessionStorage.getItem(SIDEBAR_NAV_OPEN_STORAGE_KEY);
        if (!raw) {
            return {};
        }
        const parsed = JSON.parse(raw) as unknown;
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            const out: Record<string, boolean> = {};
            for (const [k, v] of Object.entries(parsed)) {
                if (typeof v === 'boolean') {
                    out[k] = v;
                }
            }
            return out;
        }
    } catch {
        // invalid JSON or access denied
    }
    return {};
}

let openStateSingleton: Record<string, boolean> | null = null;

/**
 * Shared reactive open/closed map for sidebar nav collapsibles (persists via sessionStorage).
 * Same instance is used by AppSidebar and AppHeader mobile nav.
 */
export function getSidebarNavOpenState(): Record<string, boolean> {
    if (!openStateSingleton) {
        openStateSingleton = reactive(loadFromStorage()) as Record<
            string,
            boolean
        >;
    }
    return openStateSingleton;
}

export function persistSidebarNavOpenState(): void {
    if (typeof window === 'undefined') {
        return;
    }
    const state = getSidebarNavOpenState();
    try {
        sessionStorage.setItem(
            SIDEBAR_NAV_OPEN_STORAGE_KEY,
            JSON.stringify({ ...state }),
        );
    } catch {
        // quota or private mode
    }
}
