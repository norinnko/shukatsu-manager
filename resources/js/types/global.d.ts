import type { Auth } from '@/types/auth';
import type { Enums } from '@/types/models';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            enums: Enums;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
