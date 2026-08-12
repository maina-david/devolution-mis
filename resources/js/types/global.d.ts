import type { Auth } from '@/types/auth';

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
                    settings: string;
                    unread: string;
                    noNotifications: string;
                    viewAllNotifications: string;
                    skipToMainContent: string;
                    home: string;
                    publicNavigation: string;
                    citizenEngagement: string;
                    departmentName: string;
                    systemName: string;
                    departmentWebsite: string;
                    accessibilitySupport: string;
                    republic: string;
                    primaryNavigation: string;
                    verifyCertificate: string;
                    dashboard: string;
                    signIn: string;
                    systemLinks: string;
                    helpSupport: string;
                    verifyLearningCertificate: string;
                    authorizedAccessDescription: string;
                    postalAddress: string;
                    copyright: string;
                    complaints: string;
                    privacyNotice: string;
                    dataRights: string;
                    authorizedAccessOnly: string;
                    secureGovernmentAccess: string;
                    secureGovernmentAccessDescription: string;
                    accountProvisioning: string;
                    accountProvisioningDescription: string;
                    protectCredentials: string;
                    protectCredentialsDescription: string;
                    authenticationHelp: string;
                    toggleNavigation: string;
                };
                citizen: Record<string, string>;
                dataRights: Record<string, string>;
                dataGovernance: Record<string, string>;
                welcome: Record<string, string>;
            };
            auth: Auth;
            sidebarOpen: boolean;
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
