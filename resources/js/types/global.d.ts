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
                common: Record<string, string>;
                navigation: Record<string, string>;
                citizen: Record<string, string>;
                dataRights: Record<string, string>;
                dataGovernance: Record<string, string>;
                evidence: Record<string, string>;
                learning: Record<string, string>;
                knowledge: {
                    outcomes: Record<string, string>;
                    ui: Record<string, string>;
                };
                supportDesk: Record<string, string>;
                assessmentRecord: Record<string, string>;
                igr: {
                    outcomes: Record<string, string>;
                    ui: Record<string, string>;
                };
                operations: {
                    readiness: Record<string, string>;
                    ui: Record<string, string>;
                };
                evaluationFindings: Record<string, string>;
                departmentalPerformance: Record<string, string>;
                dswg: Record<string, string>;
                integrationManagement: Record<string, string>;
                workflowManagement: Record<string, string>;
                workflowSimulator: Record<string, string>;
                bulkActions: Record<string, string>;
                partnerCoordination: Record<string, string>;
                dashboard: Record<string, string>;
                migration: Record<string, string>;
                travelClearance: Record<string, string>;
                innovationReplications: Record<string, string>;
                assessmentConfiguration: Record<string, string>;
                assessmentAnalytics: Record<string, string>;
                exchequer: Record<string, string>;
                correctivePlans: Record<string, string>;
                evaluationPanel: Record<string, string>;
                help: Record<string, string>;
                accessControl: Record<string, string>;
                settingsProfile: Record<string, string>;
                monitoringResults: Record<string, string>;
                analytics: Record<string, string>;
                projects: Record<string, string>;
                security: {
                    workspace: {
                        head_title: string;
                        eyebrow: string;
                        title: string;
                        description: string;
                        filters: { status: string; county: string };
                        metrics: Record<string, string>;
                        ui: Record<string, string>;
                        identity: {
                            title: string;
                            description: string;
                            columns: string[];
                            empty_title: string;
                            empty_description: string;
                        };
                        incidents: {
                            columns: string[];
                            empty_title: string;
                            empty_description: string;
                        };
                        supply_chain: {
                            title: string;
                            description: string;
                            columns: string[];
                            empty_title: string;
                            empty_description: string;
                        };
                        delegations: {
                            columns: string[];
                            empty_title: string;
                            empty_description: string;
                        };
                        threats: {
                            title: string;
                            description: string;
                            columns: string[];
                            empty_title: string;
                            empty_description: string;
                        };
                        campaigns: {
                            title: string;
                            description: string;
                            empty_title: string;
                            empty_description: string;
                        };
                        access: {
                            columns: string[];
                            empty_title: string;
                            empty_description: string;
                        };
                    };
                };
                referenceData: Record<string, string>;
                welcome: Record<string, string>;
                support: Record<string, string> & {
                    questions: Array<{ question: string; answer: string }>;
                };
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
