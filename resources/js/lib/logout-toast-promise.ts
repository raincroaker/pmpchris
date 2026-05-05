import { router } from '@inertiajs/vue3';

/**
 * Resolves when Inertia finishes a visit and the browser is on `/login`.
 * Used for logout flows (POST /logout → redirect) with Sonner `toast.promise`.
 * Rejects after `timeoutMs` so loading toasts dismiss when `error` is omitted.
 */
export function createLogoutFinishedPromise(options?: {
    timeoutMs?: number;
}): Promise<void> {
    const timeoutMs = options?.timeoutMs ?? 10_000;

    return new Promise((resolve, reject) => {
        let offFinish: () => void = () => {};
        const timeoutId = window.setTimeout(() => {
            offFinish();
            reject(new Error('Logout timed out'));
        }, timeoutMs);

        offFinish = router.on('finish', () => {
            const path = window.location.pathname.replace(/\/$/, '') || '/';
            if (path === '/login') {
                window.clearTimeout(timeoutId);
                offFinish();
                resolve();
            }
        });
    });
}
