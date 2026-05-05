/** Bump suffix if storage shape changes. */
export const SIDEBAR_SCROLL_DESKTOP_STORAGE_KEY =
    'hris-sidebar-scroll-desktop-v1';

export const SIDEBAR_SCROLL_MOBILE_STORAGE_KEY =
    'hris-sidebar-scroll-mobile-v1';

function storageKey(isMobile: boolean): string {
    return isMobile
        ? SIDEBAR_SCROLL_MOBILE_STORAGE_KEY
        : SIDEBAR_SCROLL_DESKTOP_STORAGE_KEY;
}

/**
 * Read persisted vertical scroll offset for the app sidebar (session tab only).
 */
export function loadSidebarScrollTop(isMobile: boolean): number | null {
    if (typeof window === 'undefined') {
        return null;
    }
    try {
        const raw = sessionStorage.getItem(storageKey(isMobile));
        if (raw === null || raw === '') {
            return null;
        }
        const n = Number.parseFloat(raw);
        if (!Number.isFinite(n) || n < 0) {
            return null;
        }
        return n;
    } catch {
        return null;
    }
}

/**
 * Persist sidebar `scrollTop` for the current layout mode (desktop vs mobile sheet).
 */
export function saveSidebarScrollTop(
    isMobile: boolean,
    scrollTop: number,
): void {
    if (typeof window === 'undefined') {
        return;
    }
    if (!Number.isFinite(scrollTop) || scrollTop < 0) {
        return;
    }
    try {
        sessionStorage.setItem(
            storageKey(isMobile),
            String(Math.round(scrollTop)),
        );
    } catch {
        // quota or private mode
    }
}

/**
 * Clamp `scrollTop` so it is valid for the element’s current scroll range.
 */
export function clampSidebarScrollTop(
    element: HTMLElement,
    scrollTop: number,
): number {
    const max = Math.max(0, element.scrollHeight - element.clientHeight);
    return Math.min(Math.max(0, scrollTop), max);
}
