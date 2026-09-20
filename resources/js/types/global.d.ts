import type { Auth } from '@/types/auth';
import type { Organization } from '@/types/organizations';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            impersonating: boolean;
            sidebarOpen: boolean;
            currentOrganization: Organization | null;
            organizations: Organization[];
            pendingInvitationsCount: number;
            [key: string]: unknown;
        };
    }
}
