import type { ToasterProps } from 'vue-sonner';

/**
 * Stable id for the main app Sonner instance. Toasts must pass `toasterId: APP_TOASTER_ID`
 * (via `appToast` in `@/lib/app-toast-client`) or they will not render here.
 */
export const APP_TOASTER_ID = 'app' as const;

/** Horizontally centered stack at the top of the viewport. */
export const APP_TOASTER_POSITION = 'top-center' as const;

/**
 * Default props for `<Toaster />`. Tune position, duration, gap, offsets, and
 * `toastOptions` in one place.
 */
export const APP_TOASTER_PROPS = {
    position: APP_TOASTER_POSITION,
    richColors: true,
    /** `false`: full stacked list visibility; avoids collapsed stack hiding sibling content. */
    expand: false,
} as const satisfies Pick<ToasterProps, 'position' | 'richColors' | 'expand'>;
