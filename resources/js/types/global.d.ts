import type { Auth } from '@/types/auth';
import type { Team } from '@/types/teams';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            localization: {
                current: string;
                supported: Array<{
                    code: string;
                    label: string;
                    nativeLabel: string;
                    flag: string;
                }>;
                copy: {
                    chooseLanguage: string;
                    language: string;
                    currentLanguage: string;
                    help: string;
                    faqs: string;
                    openAccountMenu: string;
                    theme: string;
                    chooseTheme: string;
                    light: string;
                    dark: string;
                    system: string;
                    notifications: string;
                    unread: string;
                    noNotifications: string;
                    viewAllNotifications: string;
                };
            };
            auth: Auth;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            assessmentCycles: Array<{ id: string; name: string }>;
            notificationSummary: {
                unread: number;
                recent: Array<{
                    id: string;
                    title: string;
                    message: string;
                    category: string;
                    url: string | null;
                    readAt: string | null;
                    createdAt: string | null;
                }>;
            };
            [key: string]: unknown;
        };
    }
}
