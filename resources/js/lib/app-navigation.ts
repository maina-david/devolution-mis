import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    BookOpen,
    BriefcaseBusiness,
    ChartNoAxesCombined,
    LayoutGrid,
    Settings2,
} from 'lucide-react';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as accessControlIndex } from '@/routes/access-control';
import { index as analyticsIndex } from '@/routes/analytics';
import { edit as appearanceEdit } from '@/routes/appearance';
import { index as assessmentConfigurationIndex } from '@/routes/assessment-configuration';
import { index as assessmentsIndex } from '@/routes/assessments';
import { index as assessmentAnalyticsIndex } from '@/routes/assessments/analytics';
import { index as auditIndex } from '@/routes/audit';
import { index as auditAssuranceIndex } from '@/routes/audit-assurance';
import { index as changeReadinessIndex } from '@/routes/change-readiness';
import { index as citizenCasesIndex } from '@/routes/citizen-cases';
import { index as countiesIndex } from '@/routes/counties';
import { index as dataGovernanceIndex } from '@/routes/data-governance';
import { index as dataMigrationsIndex } from '@/routes/data-migrations';
import { index as departmentalPerformanceIndex } from '@/routes/departmental-performance';
import { index as dswgIndex } from '@/routes/dswg';
import { index as evidenceIndex } from '@/routes/evidence';
import { index as exchequerIndex } from '@/routes/exchequer';
import { index as grantsIndex } from '@/routes/grants';
import { index as igrResolutionsIndex } from '@/routes/igr-resolutions';
import { index as integrationsIndex } from '@/routes/integrations';
import { index as knowledgeIndex } from '@/routes/knowledge';
import { index as knowledgeCommunityAnalyticsIndex } from '@/routes/knowledge/community-analytics';
import { index as innovationReplicationsIndex } from '@/routes/knowledge/innovation-replications';
import { index as learningIndex } from '@/routes/learning';
import { index as learningAnalyticsIndex } from '@/routes/learning/analytics';
import { index as monitoringEvaluationIndex } from '@/routes/monitoring-evaluation';
import { index as operationsIndex } from '@/routes/operations';
import { index as partnersIndex } from '@/routes/partners';
import { index as platformIndex } from '@/routes/platform';
import { edit as profileEdit } from '@/routes/profile';
import { index as usersIndex } from '@/routes/programme-users';
import { index as projectsIndex } from '@/routes/projects';
import { index as referenceDataIndex } from '@/routes/reference-data';
import { index as reportsIndex } from '@/routes/reports';
import { edit as securityEdit } from '@/routes/security';
import { index as securityGovernanceIndex } from '@/routes/security-governance';
import { index as supportDeskIndex } from '@/routes/support-desk';
import { index as travelClearanceIndex } from '@/routes/travel-clearance';
import { index as userActivityIndex } from '@/routes/user-activity';
import { index as workflowsIndex } from '@/routes/workflows';
import type { BreadcrumbItem, NavItem } from '@/types';

export type AppNavigationGroup = {
    title: string;
    icon: LucideIcon;
    items: NavItem[];
    showChildren?: boolean;
    contextualSubgroups?: Array<{
        title: string;
        itemTitles: string[];
    }>;
};

export type ContextualNavigationSection = {
    title: string | null;
    items: NavItem[];
};

type Candidate = NavItem & { visible?: boolean };

const visible = (items: Candidate[]): NavItem[] =>
    items.filter((item) => item.visible !== false);

export function appNavigationGroups(
    permissions: string[],
): AppNavigationGroup[] {
    const can = (permission: string): boolean =>
        permissions.includes(permission);

    return [
        {
            title: 'Dashboard',
            icon: LayoutGrid,
            showChildren: false,
            items: [{ title: 'Dashboard', href: dashboard() }],
        },
        {
            title: 'County services',
            icon: Banknote,
            contextualSubgroups: [
                {
                    title: 'County oversight',
                    itemTitles: ['Counties', 'Citizen cases'],
                },
                {
                    title: 'Assessment & evidence',
                    itemTitles: ['Assessments', 'Evidence repository'],
                },
                {
                    title: 'Funding & operations',
                    itemTitles: [
                        'Grants',
                        'Exchequer tracking',
                        'Travel clearance',
                    ],
                },
            ],
            items: visible([
                {
                    title: 'Counties',
                    href: countiesIndex(),
                    visible: can('county-data:view'),
                },
                {
                    title: 'Citizen cases',
                    href: citizenCasesIndex(),
                    visible: can('citizen-cases:view'),
                },
                {
                    title: 'Assessments',
                    href: assessmentsIndex(),
                    visible: can('county-data:view'),
                },
                {
                    title: 'Evidence repository',
                    href: evidenceIndex(),
                    visible: can('county-data:view'),
                },
                {
                    title: 'Grants',
                    href: grantsIndex(),
                    visible: can('grants:view'),
                },
                {
                    title: 'Exchequer tracking',
                    href: exchequerIndex(),
                    visible: can('grants:view'),
                },
                {
                    title: 'Travel clearance',
                    href: travelClearanceIndex(),
                    visible: can('travel-clearance:view'),
                },
            ]),
        },
        {
            title: 'Delivery coordination',
            icon: BriefcaseBusiness,
            contextualSubgroups: [
                {
                    title: 'Programmes & partnerships',
                    itemTitles: ['Projects', 'Partners'],
                },
                {
                    title: 'Intergovernmental coordination',
                    itemTitles: ['Sector working groups', 'IGR resolutions'],
                },
                {
                    title: 'Results',
                    itemTitles: ['Monitoring & evaluation'],
                },
            ],
            items: visible([
                {
                    title: 'Projects',
                    href: projectsIndex(),
                    visible: can('projects:view'),
                },
                {
                    title: 'Partners',
                    href: partnersIndex(),
                    visible: can('partner-coordination:view'),
                },
                {
                    title: 'Sector working groups',
                    href: dswgIndex(),
                    visible: can('dswg:view'),
                },
                {
                    title: 'IGR resolutions',
                    href: igrResolutionsIndex(),
                    visible: can('igr-resolutions:view'),
                },
                {
                    title: 'Monitoring & evaluation',
                    href: monitoringEvaluationIndex(),
                    visible: can('monitoring-evaluation:view'),
                },
            ]),
        },
        {
            title: 'Performance & insights',
            icon: ChartNoAxesCombined,
            contextualSubgroups: [
                {
                    title: 'Analytics',
                    itemTitles: ['Analytics', 'Assessment analytics'],
                },
                {
                    title: 'Reporting & performance',
                    itemTitles: ['Reports', 'Departmental performance'],
                },
            ],
            items: visible([
                {
                    title: 'Analytics',
                    href: analyticsIndex(),
                    visible: can('analytics:view'),
                },
                {
                    title: 'Assessment analytics',
                    href: assessmentAnalyticsIndex(),
                    visible: can('county-data:view'),
                },
                {
                    title: 'Reports',
                    href: reportsIndex(),
                    visible: can('national-reports:view'),
                },
                {
                    title: 'Departmental performance',
                    href: departmentalPerformanceIndex(),
                    visible: can('departmental-performance:view'),
                },
            ]),
        },
        {
            title: 'Knowledge & capability',
            icon: BookOpen,
            contextualSubgroups: [
                {
                    title: 'Learning & readiness',
                    itemTitles: [
                        'E-Learning',
                        'Learning analytics',
                        'Rollout & training',
                        'Service desk',
                    ],
                },
                {
                    title: 'Knowledge exchange',
                    itemTitles: [
                        'Knowledge management',
                        'Community health',
                        'Innovation replication',
                    ],
                },
            ],
            items: visible([
                {
                    title: 'E-Learning',
                    href: learningIndex(),
                    visible: can('learning:view'),
                },
                {
                    title: 'Learning analytics',
                    href: learningAnalyticsIndex(),
                    visible: can('learning:view'),
                },
                {
                    title: 'Knowledge management',
                    href: knowledgeIndex(),
                    visible: can('knowledge:view'),
                },
                {
                    title: 'Community health',
                    href: knowledgeCommunityAnalyticsIndex(),
                    visible: can('knowledge-analytics:view'),
                },
                {
                    title: 'Innovation replication',
                    href: innovationReplicationsIndex(),
                    visible: can('knowledge:view'),
                },
                {
                    title: 'Rollout & training',
                    href: changeReadinessIndex(),
                    visible: can('change-readiness:view'),
                },
                {
                    title: 'Service desk',
                    href: supportDeskIndex(),
                    visible: can('support-desk:view'),
                },
            ]),
        },
        {
            title: 'Platform governance',
            icon: Settings2,
            contextualSubgroups: [
                {
                    title: 'Access & accountability',
                    itemTitles: [
                        'User access',
                        'Roles & permissions',
                        'Audit trail',
                        'Audit assurance',
                        'User activity',
                    ],
                },
                {
                    title: 'Configuration',
                    itemTitles: [
                        'Reference data',
                        'Historical migrations',
                        'Workflow registry',
                        'Assessment setup',
                        'Platform controls',
                    ],
                },
                {
                    title: 'Assurance & operations',
                    itemTitles: [
                        'Integrations',
                        'Operations',
                        'Data governance',
                        'Security governance',
                    ],
                },
            ],
            items: visible([
                {
                    title: 'User access',
                    href: usersIndex(),
                    visible:
                        can('county-users:manage') || can('user-access:manage'),
                },
                {
                    title: 'Roles & permissions',
                    href: accessControlIndex(),
                    visible: can('user-access:manage'),
                },
                {
                    title: 'Audit trail',
                    href: auditIndex(),
                    visible: can('audit-trail:view'),
                },
                {
                    title: 'Audit assurance',
                    href: auditAssuranceIndex(),
                    visible:
                        can('audit-trail:view') &&
                        (can('security-governance:manage') ||
                            can('platform:configure')),
                },
                {
                    title: 'User activity',
                    href: userActivityIndex(),
                    visible: can('user-activity:view'),
                },
                {
                    title: 'Reference data',
                    href: referenceDataIndex(),
                    visible: can('reference-data:manage'),
                },
                {
                    title: 'Historical migrations',
                    href: dataMigrationsIndex(),
                    visible:
                        can('reference-data:manage') ||
                        can('reference-data:approve') ||
                        can('operations:manage'),
                },
                {
                    title: 'Workflow registry',
                    href: workflowsIndex(),
                    visible: can('workflows:manage'),
                },
                {
                    title: 'Assessment setup',
                    href: assessmentConfigurationIndex(),
                    visible: can('assessment-configuration:manage'),
                },
                {
                    title: 'Integrations',
                    href: integrationsIndex(),
                    visible: can('integrations:view'),
                },
                {
                    title: 'Operations',
                    href: operationsIndex(),
                    visible: can('operations:view'),
                },
                {
                    title: 'Data governance',
                    href: dataGovernanceIndex(),
                    visible: can('data-governance:view'),
                },
                {
                    title: 'Security governance',
                    href: securityGovernanceIndex(),
                    visible: can('security-governance:view'),
                },
                {
                    title: 'Platform controls',
                    href: platformIndex(),
                    visible: can('platform:configure'),
                },
            ]),
        },
    ].filter((group) => group.items.length > 0);
}

export function activeNavigationGroup(
    groups: AppNavigationGroup[],
    currentPath: string,
): AppNavigationGroup | undefined {
    return groups
        .flatMap((group) =>
            group.items.map((item) => ({
                group,
                path: toUrl(item.href).split('?')[0].replace(/\/$/u, ''),
            })),
        )
        .filter(
            ({ path }) =>
                currentPath === path || currentPath.startsWith(`${path}/`),
        )
        .sort((left, right) => right.path.length - left.path.length)[0]?.group;
}

/**
 * Resolve every feature link into exactly one contextual section.
 *
 * The sidebar deliberately renders work areas only. Feature links belong to
 * this contextual surface and are de-duplicated here so a malformed registry
 * cannot render the same destination in multiple header menus.
 */
export function contextualNavigationSections(
    group: AppNavigationGroup,
): ContextualNavigationSection[] {
    const availableItems = new Map(
        group.items.map((item) => [item.title, item] as const),
    );
    const assignedTitles = new Set<string>();
    const groupedSections = (group.contextualSubgroups ?? [])
        .map((subgroup) => ({
            title: subgroup.title,
            items: subgroup.itemTitles.flatMap((title) => {
                const item = availableItems.get(title);

                if (!item || assignedTitles.has(title)) {
                    return [];
                }

                assignedTitles.add(title);

                return [item];
            }),
        }))
        .filter((section) => section.items.length > 0);
    const ungroupedItems = group.items.filter(
        (item) => !assignedTitles.has(item.title),
    );

    return [
        ...(ungroupedItems.length > 0
            ? [{ title: null, items: ungroupedItems }]
            : []),
        ...groupedSections,
    ];
}

export function settingsNavigationGroup(
    currentPath: string,
): AppNavigationGroup | undefined {
    if (!currentPath.startsWith('/settings')) {
        return undefined;
    }

    return {
        title: 'Settings',
        icon: Settings2,
        items: [
            { title: 'Profile', href: profileEdit() },
            { title: 'Security', href: securityEdit() },
            { title: 'Appearance', href: appearanceEdit() },
        ],
    };
}

export function navigationItemIsActive(
    item: NavItem,
    currentPath: string,
): boolean {
    const path = toUrl(item.href).split('?')[0].replace(/\/$/u, '');

    return currentPath === path || currentPath.startsWith(`${path}/`);
}

export function navigationBreadcrumbs(
    groups: AppNavigationGroup[],
    currentPath: string,
): BreadcrumbItem[] {
    const match = groups
        .flatMap((group) =>
            group.items.map((item) => ({
                group,
                item,
                path: toUrl(item.href).split('?')[0].replace(/\/$/u, ''),
            })),
        )
        .filter(
            ({ path }) =>
                currentPath === path || currentPath.startsWith(`${path}/`),
        )
        .sort((left, right) => right.path.length - left.path.length)[0];

    if (!match) {
        return [];
    }

    if (match.group.title === match.item.title) {
        return [{ title: match.item.title, href: match.item.href }];
    }

    return [
        {
            title: match.group.title,
            href: match.group.items[0].href,
        },
        { title: match.item.title, href: match.item.href },
    ];
}

export function fallbackNavigationBreadcrumb(
    currentPath: string,
): BreadcrumbItem {
    const segments = currentPath.split('?')[0].split('/').filter(Boolean);
    const lastSegment = segments.at(-1) ?? 'dashboard';
    const label = /^[0-9a-f-]{32,36}$/iu.test(lastSegment)
        ? (segments.at(-2) ?? 'record')
        : lastSegment;
    const title = label
        .replaceAll('-', ' ')
        .replaceAll('_', ' ')
        .replace(/^./u, (character) => character.toUpperCase())
        .replace(/^Faqs$/u, 'FAQs');

    return { title, href: currentPath };
}
