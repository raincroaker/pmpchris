import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** When false, hide Administration block and admin-only nav leaves. */
            showHrAdminNav: boolean;
            /** Set when the user must pick a branch and a selection exists in session. */
            branchContext: {
                id: number;
                code: string;
                name: string;
            } | null;
            /** Whether the current user is allowed to switch branch context. */
            canSwitchBranches: boolean;
            /** Branch roots for picker-role users (same source as branch select page). */
            switchableBranches: {
                id: number;
                code: string;
                name: string;
                area_name?: string | null;
                group_label?: string | null;
            }[];
            /** Team documents: assignment-based unit ids from {@see HandleInertiaRequests} (no persistence). */
            documents?: {
                teamAssignmentUnitIds: number[];
                teamHeadUnitIds: number[];
            };
            /** Session flash; consumed in {@link AppShell} with `appToast`. */
            flash?: {
                success?: string | null;
                error?: string | null;
            } | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
