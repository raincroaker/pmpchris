import type { Component } from 'vue';
import type { ExternalToast } from 'vue-sonner';
import { toast as sonnerToast } from 'vue-sonner';
import { APP_TOASTER_ID } from '@/config/app-toast';

type SonnerPromiseFn = typeof sonnerToast.promise;
type PromiseArg = Parameters<SonnerPromiseFn>[0];
type PromiseOptions = Parameters<SonnerPromiseFn>[1];

function withToasterId(data?: ExternalToast): ExternalToast {
    return { ...data, toasterId: APP_TOASTER_ID };
}

function withPromiseToasterId(
    data?: PromiseOptions,
): PromiseOptions | undefined {
    if (data === undefined) {
        return { toasterId: APP_TOASTER_ID } as PromiseOptions;
    }
    return { ...data, toasterId: APP_TOASTER_ID };
}

/** Use for app-shell toasts so they route to `AppToaster` (`APP_TOASTER_ID`). */
export const appToast = {
    message: (
        message: Parameters<typeof sonnerToast>[0],
        data?: ExternalToast,
    ) => sonnerToast(message, withToasterId(data)),

    success: (
        message: Parameters<typeof sonnerToast.success>[0],
        data?: ExternalToast,
    ) => sonnerToast.success(message, withToasterId(data)),

    info: (
        message: Parameters<typeof sonnerToast.info>[0],
        data?: ExternalToast,
    ) => sonnerToast.info(message, withToasterId(data)),

    warning: (
        message: Parameters<typeof sonnerToast.warning>[0],
        data?: ExternalToast,
    ) => sonnerToast.warning(message, withToasterId(data)),

    error: (
        message: Parameters<typeof sonnerToast.error>[0],
        data?: ExternalToast,
    ) => sonnerToast.error(message, withToasterId(data)),

    loading: (
        message: Parameters<typeof sonnerToast.loading>[0],
        data?: ExternalToast,
    ) => sonnerToast.loading(message, withToasterId(data)),

    promise: (promise: PromiseArg, data?: PromiseOptions) =>
        sonnerToast.promise(promise, withPromiseToasterId(data)),

    custom: (component: Component, data?: ExternalToast) =>
        sonnerToast.custom(component, withToasterId(data)),

    /** Dismiss by toast id, or all toasts when id omitted (sonner behavior). */
    dismiss: (id?: number | string) => sonnerToast.dismiss(id),
};
