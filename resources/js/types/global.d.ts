import type { Directive } from 'vue';
import type { Auth, CurrentGym } from '@/types/auth';

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
            currentGym: CurrentGym | null;
            currentBranch: { id: number; name: string } | null;
            navigationBranches: { id: number; name: string }[];
            selectedBranchId: number | null;
            planReview: {
                staff: boolean;
                branches: boolean;
                staffLimit: number;
                branchLimit: number;
                perBranch: boolean;
            } | null;
            features: string[];
            isPlatformAdmin: boolean;
            demo: { expiresAt: string } | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
