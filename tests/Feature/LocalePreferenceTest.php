<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_and_reuse_the_session_locale(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from('/settings/profile')->patch(route('locale.update'), [
            'locale' => 'sw',
        ]);

        $response->assertRedirect('/settings/profile')
            ->assertSessionHas('locale', 'sw')
            ->assertSessionHas('status', 'Lugha yako chaguomsingi imesasishwa.');

        $this->assertDatabaseHas('user_locale_preferences', [
            'user_id' => $user->id,
            'locale' => 'sw',
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('lang="sw"', false)
            ->assertInertia(fn ($page) => $page
                ->where('localization.current', 'sw')
                ->where('localization.copy.chooseLanguage', 'Chagua lugha')
                ->where('localization.common.rows_per_page', 'Safu kwa kila ukurasa')
                ->where('localization.common.verified_county_identity', 'Utambulisho wa kaunti uliothibitishwa')
                ->where('localization.common.apply_filters', 'Tumia vichujio')
                ->where('localization.globalSearch.button', 'Tafuta IDMIS')
                ->where('localization.globalSearch.searching', 'Inatafuta rekodi zilizoidhinishwa…')
                ->where('localization.auditAssurance.fail_closed', 'Uthibitishaji unaokataa hitilafu')
                ->where('localization.navigation.platform_governance', 'Utawala wa jukwaa')
                ->where('localization.evidence.manage_document', 'Simamia hati')
                ->where('localization.evidence.outcomes.uploaded', 'Ushahidi umepakiwa kwa usalama.')
                ->where('localization.learning.heading', 'Kituo cha mafunzo ya ugatuzi')
                ->where('localization.learning.reconciliation_rationale', 'Sababu ya upatanisho')
                ->where('localization.programmeUserProfile.governed_identity_record', 'Rekodi ya utambulisho inayosimamiwa')
                ->where('localization.userActivity.online_now', 'Mtandaoni sasa')
                ->has('localization.supported', 3));

        $this->flushSession();
        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn ($page) => $page->where('localization.current', 'sw'));
    }

    public function test_guest_can_change_session_locale_without_creating_a_profile_preference(): void
    {
        $this->from(route('citizen-engagement.index'))
            ->patch(route('locale.update'), ['locale' => 'sw'])
            ->assertRedirect(route('citizen-engagement.index'))
            ->assertSessionHas('locale', 'sw')
            ->assertSessionHas('status', 'Lugha ya kuonyesha imesasishwa kwa kivinjari hiki.');

        $this->assertDatabaseCount('user_locale_preferences', 0);
        $this->assertDatabaseCount('audit_events', 0);

        $this->withSession(['locale' => 'sw'])
            ->get(route('citizen-engagement.index'))
            ->assertOk()
            ->assertSee('lang="sw"', false)
            ->assertInertia(fn ($page) => $page
                ->where('localization.current', 'sw')
                ->where('localization.copy.citizenEngagement', 'Ushirikishwaji wa wananchi'));
    }

    public function test_saved_locale_drives_translated_queued_notification_payloads(): void
    {
        $user = User::factory()->create();
        $user->localePreference()->create(['locale' => 'fr']);
        $user->load('localePreference');

        $this->assertSame('fr', $user->preferredLocale());

        App::setLocale($user->preferredLocale());
        $payload = ProgrammeAlert::translated(
            titleKey: 'assessment-record.notifications.workflow_updated_title',
            messageKey: 'assessment-record.notifications.workflow_updated_message',
            category: 'assessment',
            messageParameters: ['cycle' => 'ACPA-2027-28', 'county' => 'Makueni'],
        )->toArray($user);

        $this->assertSame('Flux d’évaluation mis à jour', $payload['title']);
        $this->assertSame('L’évaluation ACPA-2027-28 de Makueni est passée à l’étape suivante de son flux gouverné.', $payload['message']);
    }

    public function test_locale_change_rejects_an_unsupported_locale(): void
    {
        $this->patch(route('locale.update'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');

        $this->actingAs(User::factory()->create())
            ->patch(route('locale.update'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }

    public function test_header_locale_selector_has_screen_reader_and_wayfinder_contracts(): void
    {
        $source = file_get_contents(resource_path('js/components/locale-menu.tsx'));
        $publicShell = file_get_contents(resource_path('js/components/public-site-header.tsx'));

        $this->assertIsString($source);
        $this->assertIsString($publicShell);
        $this->assertStringContainsString("import { update as updateLocale } from '@/routes/locale';", $source);
        $this->assertStringContainsString('aria-label={`${localization.copy.chooseLanguage}', $source);
        $this->assertStringContainsString('role="status"', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringContainsString('lang={locale.code}', $source);
        $this->assertStringContainsString('aria-hidden="true">{locale.flag}', $source);
        $this->assertStringContainsString('<LocaleMenu inverse />', $publicShell);
    }

    public function test_global_authorized_search_uses_localized_accessible_status_contracts(): void
    {
        $source = file_get_contents(resource_path('js/components/global-search-dialog.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('const copy = localization.globalSearch;', $source);
        $this->assertStringContainsString('aria-label={copy.button}', $source);
        $this->assertStringContainsString('role="status"', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringContainsString('role="alert"', $source);
        $this->assertStringNotContainsString('Searching authorized records…', $source);
        $this->assertStringNotContainsString('Search is temporarily unavailable.', $source);
    }

    public function test_shared_linked_document_boundary_uses_the_request_locale_for_conflicts_and_outcomes(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/LinkedDocumentController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("__('linked-documents.errors.project_closed')", $source);
        $this->assertStringContainsString("__('linked-documents.outcomes.project_uploaded')", $source);
        $this->assertStringContainsString("__('linked-documents.activity.support_uploaded')", $source);
        $this->assertStringNotContainsString("'message' => 'Project record uploaded securely.'", $source);

        App::setLocale('sw');
        $this->assertSame('Hati za mradi zimefungwa baada ya kufungwa kwa mradi.', __('linked-documents.errors.project_closed'));
        $this->assertSame('Rekodi ya mradi imepakiwa kwa usalama.', __('linked-documents.outcomes.project_uploaded'));

        App::setLocale('fr');
        $this->assertSame('Les dossiers de performance sont verrouillés en dehors de l’étape applicable de leur cycle de vie.', __('linked-documents.errors.performance_stage'));
    }

    public function test_historical_and_reference_import_application_controls_use_the_active_locale(): void
    {
        $source = file_get_contents(app_path('Actions/ApplyHistoricalDataMigration.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("__('migration.apply.independent_operator')", $source);
        $this->assertStringContainsString("__('migration.apply.acpa_component_conflict')", $source);
        $this->assertStringContainsString("__('migration.apply.bulk_audit'", $source);
        $this->assertStringNotContainsString('Only an approved migration batch can be applied.', $source);

        App::setLocale('sw');
        $this->assertSame('Mtekelezaji wa tatu aliye huru lazima atekeleze uhamishaji ulioidhinishwa.', __('migration.apply.independent_operator'));
        $this->assertSame('Uundaji upya wa ACPA ya zamani umetumika kwa rekodi 6 zisizobadilika.', __('migration.apply.acpa_audit', ['count' => 6]));

        App::setLocale('fr');
        $this->assertSame('Une référence de programme ou de comté a changé après l’examen. Préparez à nouveau la source.', __('migration.apply.programme_county_changed'));
    }

    public function test_audit_assurance_controls_localize_every_evidence_label_and_preserve_accessible_actions(): void
    {
        $source = file_get_contents(resource_path('js/components/audit-assurance-controls.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('const copy = usePage().props.localization.auditAssurance;', $source);
        $this->assertStringContainsString('aria-label={copy.open_actions}', $source);
        $this->assertStringContainsString('label={copy.chain_root}', $source);
        $this->assertStringContainsString('value={meta?.findingCodes || copy.none}', $source);
        $this->assertStringNotContainsString('Fail-closed verification', $source);
        $this->assertStringNotContainsString('Audit assurance evidence', $source);
    }

    public function test_shared_county_identity_localizes_logo_names_verification_and_empty_groups(): void
    {
        $source = file_get_contents(resource_path('js/components/county-identity.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('alt={`${county.name} ${copy.county_official_logo}`}', $source);
        $this->assertStringContainsString('{copy.verified_county_identity}', $source);
        $this->assertStringContainsString('{copy.none}', $source);
        $this->assertStringNotContainsString('Verified county identity', $source);
    }

    public function test_shared_date_and_multi_select_controls_use_locale_copy_and_locale_aware_dates(): void
    {
        $datePicker = file_get_contents(resource_path('js/components/date-picker-field.tsx'));
        $dateRange = file_get_contents(resource_path('js/components/date-range-filter.tsx'));
        $multiSelect = file_get_contents(resource_path('js/components/searchable-multi-select.tsx'));

        $this->assertIsString($datePicker);
        $this->assertIsString($dateRange);
        $this->assertIsString($multiSelect);
        $this->assertStringContainsString('formatDateTime(date,', $datePicker);
        $this->assertStringContainsString('copy.select_date', $datePicker);
        $this->assertStringContainsString('copy.apply_filters', $dateRange);
        $this->assertStringContainsString('copy.clear_filters', $dateRange);
        $this->assertStringContainsString('formatDateTime(range.from,', $dateRange);
        $this->assertStringContainsString('copy.select_one_or_more', $multiSelect);
        $this->assertStringContainsString('copy.no_matching_options', $multiSelect);
        $this->assertStringNotContainsString('Apply filters', $dateRange);
        $this->assertStringNotContainsString('No matching options.', $multiSelect);
    }

    public function test_official_devolution_branding_is_used_for_app_and_browser_icons(): void
    {
        $logo = file_get_contents(resource_path('js/components/app-logo-icon.tsx'));
        $rootView = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertIsString($logo);
        $this->assertIsString($rootView);
        $this->assertStringContainsString('/images/branding/devolution-emblem.png', $logo);
        $this->assertStringContainsString('href="/favicon.ico" sizes="32x32"', $rootView);
        $this->assertStringContainsString('href="/apple-touch-icon.png" sizes="180x180"', $rootView);
        $this->assertFileExists(public_path('images/branding/devolution-emblem.png'));
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
        $this->assertFileDoesNotExist(public_path('favicon.svg'));
    }

    public function test_every_translation_catalogue_has_matching_keys_and_placeholders_in_all_supported_locales(): void
    {
        $englishFiles = collect(glob(lang_path('en/*.php')) ?: [])->map(fn (string $path): string => basename($path))->sort()->values();

        foreach (['sw', 'fr'] as $locale) {
            $localizedFiles = collect(glob(lang_path("{$locale}/*.php")) ?: [])->map(fn (string $path): string => basename($path))->sort()->values();
            $this->assertSame($englishFiles->all(), $localizedFiles->all(), "{$locale} must contain every English translation domain.");

            foreach ($englishFiles as $file) {
                $english = $this->flattenTranslations(require lang_path("en/{$file}"));
                $localized = $this->flattenTranslations(require lang_path("{$locale}/{$file}"));
                $this->assertSame(array_keys($english), array_keys($localized), "{$locale}/{$file} translation keys diverge from English.");
                foreach ($english as $key => $message) {
                    preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $message, $englishPlaceholders);
                    preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $localized[$key], $localizedPlaceholders);
                    $this->assertEqualsCanonicalizing(array_values(array_unique($englishPlaceholders[0])), array_values(array_unique($localizedPlaceholders[0])), "{$locale}/{$file}:{$key} must preserve replacement placeholders.");
                }
            }
        }
    }

    public function test_framework_validation_messages_are_actually_localized_instead_of_falling_back_to_english(): void
    {
        $english = $this->flattenTranslations(require lang_path('en/validation.php'));
        foreach (['sw', 'fr'] as $locale) {
            $localized = $this->flattenTranslations(require lang_path("{$locale}/validation.php"));
            foreach ($english as $key => $message) {
                $this->assertNotSame($message, $localized[$key], "{$locale} validation message {$key} still falls back to English.");
            }
        }
    }

    public function test_learning_workspace_uses_the_shared_catalogue_and_active_locale_without_inline_interface_copy(): void
    {
        $source = file_get_contents(resource_path('js/pages/learning/index.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('const copy = localization.learning;', $source);
        $this->assertStringContainsString('toLocaleString(locale)', $source);
        $this->assertStringContainsString('displayValue(copy,', $source);
        $this->assertStringNotContainsString('DEFAULT_LOCALE', $source);

        $english = $this->flattenTranslations(require lang_path('en/learning.php'));
        $this->assertArrayHasKey('variant_assurance', $english);
        $this->assertArrayHasKey('offline_package_assurance', $english);
        $this->assertArrayHasKey('asset_security_notice', $english);
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, string>
     */
    private function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $flattened = [];
        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $flattened += $this->flattenTranslations($value, $path);
            } elseif (is_string($value)) {
                $flattened[$path] = $value;
            }
        }

        return $flattened;
    }
}
