<?php

namespace App\Http\Middleware;

use App\Enums\SupportedLocale;
use App\Enums\UserRole;
use App\Models\AssessmentCycle;
use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'localization' => [
                'current' => app()->getLocale(),
                'supported' => collect(SupportedLocale::cases())
                    ->map(fn (SupportedLocale $locale): array => [
                        'code' => $locale->value,
                        'label' => $locale->label(),
                        'nativeLabel' => $locale->label(),
                        'flag' => $locale->flag(),
                    ])->values()->all(),
                'copy' => [
                    'chooseLanguage' => __('idmis.locale.choose_language'),
                    'language' => __('idmis.locale.language'),
                    'currentLanguage' => __('idmis.locale.current_language'),
                    'help' => __('idmis.header.help'),
                    'faqs' => __('idmis.header.faqs'),
                    'openAccountMenu' => __('idmis.header.open_account_menu'),
                    'theme' => __('idmis.header.theme'),
                    'chooseTheme' => __('idmis.header.choose_theme'),
                    'light' => __('idmis.header.light'),
                    'dark' => __('idmis.header.dark'),
                    'system' => __('idmis.header.system'),
                    'notifications' => __('idmis.header.notifications'),
                    'settings' => __('idmis.header.settings'),
                    'fileManager' => __('idmis.header.file_manager'),
                    'unread' => __('idmis.header.unread'),
                    'noNotifications' => __('idmis.header.no_notifications'),
                    'viewAllNotifications' => __('idmis.header.view_all_notifications'),
                    'skipToMainContent' => __('idmis.public.skip_to_main_content'),
                    'home' => __('idmis.public.home'),
                    'publicNavigation' => __('idmis.public.navigation'),
                    'citizenEngagement' => __('idmis.public.citizen_engagement'),
                    'departmentName' => __('idmis.public.department_name'),
                    'systemName' => __('idmis.public.system_name'),
                    'departmentWebsite' => __('idmis.public.department_website'),
                    'accessibilitySupport' => __('idmis.public.accessibility_support'),
                    'republic' => __('idmis.public.republic'),
                    'primaryNavigation' => __('idmis.public.primary_navigation'),
                    'verifyCertificate' => __('idmis.public.verify_certificate'),
                    'dashboard' => __('idmis.public.dashboard'),
                    'signIn' => __('idmis.public.sign_in'),
                    'systemLinks' => __('idmis.public.system_links'),
                    'helpSupport' => __('idmis.public.help_support'),
                    'verifyLearningCertificate' => __('idmis.public.verify_learning_certificate'),
                    'authorizedAccessDescription' => __('idmis.public.authorized_access_description'),
                    'postalAddress' => __('idmis.public.postal_address'),
                    'copyright' => __('idmis.public.copyright', ['year' => now()->year]),
                    'complaints' => __('idmis.public.complaints'),
                    'privacyNotice' => __('idmis.public.privacy_notice'),
                    'dataRights' => __('idmis.public.data_rights'),
                    'authorizedAccessOnly' => __('idmis.public.authorized_access_only'),
                    'secureGovernmentAccess' => __('idmis.public.secure_government_access'),
                    'secureGovernmentAccessDescription' => __('idmis.public.secure_government_access_description'),
                    'accountProvisioning' => __('idmis.public.account_provisioning'),
                    'accountProvisioningDescription' => __('idmis.public.account_provisioning_description'),
                    'protectCredentials' => __('idmis.public.protect_credentials'),
                    'protectCredentialsDescription' => __('idmis.public.protect_credentials_description'),
                    'authenticationHelp' => __('idmis.public.authentication_help'),
                    'toggleNavigation' => __('idmis.public.toggle_navigation'),
                    'logIn' => __('idmis.public.log_in'),
                    'emailAddress' => __('idmis.public.email_address'),
                    'workEmailPlaceholder' => __('idmis.public.work_email_placeholder'),
                    'password' => __('idmis.public.password'),
                    'forgotPassword' => __('idmis.public.forgot_password'),
                    'rememberMe' => __('idmis.public.remember_me'),
                    'administratorGrantedAccess' => __('idmis.public.administrator_granted_access'),
                    'getHelp' => __('idmis.public.get_help'),
                    'loginTitle' => __('idmis.public.login_title'),
                    'loginDescription' => __('idmis.public.login_description'),
                    'authenticating' => __('idmis.public.authenticating'),
                    'signInWithPasskey' => __('idmis.public.sign_in_with_passkey'),
                    'continueWithEmail' => __('idmis.public.continue_with_email'),
                ],
                'common' => __('idmis.common'),
                'authentication' => __('auth.ui'),
                'globalSearch' => __('idmis.global_search'),
                'navigation' => __('idmis.navigation'),
                'citizen' => __('citizen'),
                'dataRights' => __('data-rights'),
                'dataGovernance' => array_merge(__('data-governance'), __('data-governance-forms')),
                'privacyDocuments' => __('privacy-documents'),
                'evidence' => __('evidence'),
                'documentRepository' => __('document-repository'),
                'learning' => __('learning'),
                'learningAnalytics' => __('learning-analytics'),
                'knowledge' => array_replace_recursive(__('knowledge'), [
                    'ui' => array_merge(__('knowledge.ui'), __('knowledge-forms')),
                ]),
                'supportDesk' => __('support-desk'),
                'assessmentRecord' => __('assessment-record'),
                'igr' => array_replace_recursive(__('igr'), [
                    'ui' => array_merge(__('igr.ui'), __('igr-forms')),
                ]),
                'igrDocuments' => __('igr-documents'),
                'operations' => __('operations'),
                'notifications' => __('notifications'),
                'userActivity' => __('user-activity'),
                'evaluationFindings' => array_merge(__('evaluation-findings'), __('evaluation-finding-forms')),
                'departmentalPerformance' => __('departmental-performance'),
                'performanceDocuments' => __('performance-documents'),
                'dswg' => array_merge(__('dswg'), __('dswg-workspace')),
                'integrationManagement' => array_merge(__('integration-management'), __('integration-management-forms')),
                'workflowManagement' => __('workflow-management'),
                'workflowSimulator' => __('workflow-simulator'),
                'bulkActions' => __('bulk-actions'),
                'partnerCoordination' => array_merge(__('partner-coordination'), __('partner-governance-forms')),
                'dashboard' => __('dashboard'),
                'migration' => __('migration.ui'),
                'travelClearance' => __('travel-clearance'),
                'innovationReplications' => __('innovation-replications'),
                'assessmentConfiguration' => __('assessment-configuration'),
                'assessmentAnalytics' => __('assessment-analytics'),
                'exchequer' => __('exchequer'),
                'correctivePlans' => __('corrective-plans'),
                'countyDetail' => __('county-detail'),
                'evaluationPanel' => __('evaluation-panel'),
                'indicatorDefinitions' => __('indicator-definitions'),
                'programmeUserProfile' => __('programme-user-profile'),
                'programmeWorkspace' => __('programme-workspace'),
                'evaluationDocuments' => __('evaluation-documents'),
                'help' => __('help'),
                'accessControl' => __('access-control'),
                'auditAssurance' => __('audit-assurance'),
                'settingsProfile' => __('settings-profile'),
                'settingsSecurity' => __('settings-security'),
                'monitoringResults' => __('monitoring-results'),
                'analytics' => __('analytics'),
                'projects' => array_merge(__('projects'), __('project-details-forms')),
                'security' => array_replace_recursive(__('security'), [
                    'workspace' => array_merge(__('security.workspace'), [
                        'forms' => __('security-forms'),
                    ]),
                ]),
                'referenceData' => __('reference-data'),
                'welcome' => __('welcome'),
                'support' => __('support'),
            ],
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'role' => $user->programmeRole()->name,
                    'role_label' => $user->programmeRole()->label(),
                    'permissions' => $user->programmePermissionValues(),
                    'county_identity' => $this->countyIdentity($user),
                    'avatar' => $user->profile_photo_path ? route('profile.photo', ['v' => $user->profile_photo_checksum]) : null,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'assessmentCycles' => fn () => $user
                ? AssessmentCycle::query()
                    ->orderByDesc('period_start')
                    ->get(['id', 'code', 'name'])
                    ->map(fn (AssessmentCycle $cycle): array => [
                        'id' => $cycle->id,
                        'name' => "{$cycle->name} ({$cycle->code})",
                    ])
                    ->values()
                    ->all()
                : [],
            'notificationSummary' => fn () => [
                'unread' => $user?->unreadNotifications()->count() ?? 0,
                'recent' => $user?->notifications()->latest()->limit(5)->get()->map(fn (DatabaseNotification $notification): array => [
                    'id' => $notification->id,
                    'title' => (string) ($notification->data['title'] ?? __('idmis.common.notification')),
                    'message' => (string) ($notification->data['message'] ?? ''),
                    'category' => (string) ($notification->data['category'] ?? 'general'),
                    'url' => is_string($notification->data['url'] ?? null) ? $notification->data['url'] : null,
                    'readAt' => $notification->read_at?->toISOString(),
                    'createdAt' => $notification->created_at?->toISOString(),
                ])->values()->all() ?? [],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function countyIdentity(User $user): ?array
    {
        if (! in_array($user->programmeRole(), [UserRole::CountyOfficial, UserRole::CountyAdmin], true)) {
            return null;
        }

        return $user->county()->first()?->identityCell();
    }
}
