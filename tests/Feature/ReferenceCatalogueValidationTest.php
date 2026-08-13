<?php

namespace Tests\Feature;

use App\Support\ReferenceCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class ReferenceCatalogueValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_catalogue_contains_expected_iso_and_iana_reference_values(): void
    {
        $this->assertContains('KE', ReferenceCatalogue::countryCodes());
        $this->assertContains('Kenya', ReferenceCatalogue::countryNames());
        $this->assertContains('KES', ReferenceCatalogue::currencies());
        $this->assertContains('en', ReferenceCatalogue::languages());
        $this->assertContains('Africa/Nairobi', ReferenceCatalogue::timezones());
        $this->assertSame('KE', ReferenceCatalogue::defaultCountryCode());
        $this->assertSame('Kenya', ReferenceCatalogue::defaultCountryName());
        $this->assertSame('KES', ReferenceCatalogue::defaultCurrency());
        $this->assertSame('en', ReferenceCatalogue::defaultLanguage());
        $this->assertSame('eng', ReferenceCatalogue::defaultOcrLanguage());
        $this->assertSame('en-KE', ReferenceCatalogue::defaultLocale());
        $this->assertSame('Africa/Nairobi', ReferenceCatalogue::defaultTimezone());
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        $dependencies = $package['dependencies'];

        $this->assertSame($dependencies['countries-and-timezones'], ReferenceCatalogue::packageVersions()['countriesAndTimezones']);
        $this->assertSame($dependencies['currency-codes'], ReferenceCatalogue::packageVersions()['currencyCodes']);
        $this->assertSame($dependencies['iso-639-1'], ReferenceCatalogue::packageVersions()['iso6391']);
        $this->assertSame($dependencies['iso-639-3'], ReferenceCatalogue::packageVersions()['iso6393']);
    }

    public function test_reference_columns_do_not_retain_database_literal_defaults(): void
    {
        $rows = DB::select("SELECT table_name, column_name, column_default FROM information_schema.columns WHERE table_schema = 'public' AND column_name IN ('currency', 'timezone', 'country', 'country_code', 'destination_country', 'residency_country', 'language', 'locale') AND column_default IS NOT NULL");

        $this->assertSame([], $rows);
    }

    public function test_catalogue_integrity_failures_follow_the_active_locale(): void
    {
        $stringList = new ReflectionMethod(ReferenceCatalogue::class, 'stringList');
        $defaults = new ReflectionMethod(ReferenceCatalogue::class, 'defaults');

        app()->setLocale('fr');

        try {
            $stringList->invoke(null, [], 'currencies');
            $this->fail('An empty generated catalogue field must fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('reference-catalogue.errors.empty_field', ['field' => 'currencies']), $exception->getMessage());
        }

        app()->setLocale('sw');

        try {
            $defaults->invoke(null, ['countryCode' => '']);
            $this->fail('An invalid generated catalogue default must fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('reference-catalogue.errors.invalid_default', ['key' => 'countryCode']), $exception->getMessage());
        }

        $english = require lang_path('en/reference-catalogue.php');
        $kiswahili = require lang_path('sw/reference-catalogue.php');
        $french = require lang_path('fr/reference-catalogue.php');

        $this->assertSame(array_keys($english['errors']), array_keys($kiswahili['errors']));
        $this->assertSame(array_keys($english['errors']), array_keys($french['errors']));
    }

    #[DataProvider('referenceValueProvider')]
    public function test_package_backed_rules_accept_real_values_and_reject_fabricated_values(
        string $valid,
        string $invalid,
        string $catalogue,
    ): void {
        $allowed = match ($catalogue) {
            'countryCodes' => ReferenceCatalogue::countryCodes(),
            'countryNames' => ReferenceCatalogue::countryNames(),
            'currencies' => ReferenceCatalogue::currencies(),
            'languages' => ReferenceCatalogue::languages(),
            'timezones' => ReferenceCatalogue::timezones(),
            default => throw new \InvalidArgumentException("Unknown catalogue: {$catalogue}"),
        };
        $rules = ['value' => ['required', Rule::in($allowed)]];

        $this->assertFalse(Validator::make(['value' => $valid], $rules)->fails());
        $this->assertTrue(Validator::make(['value' => $invalid], $rules)->fails());
    }

    /** @return array<string, array{string, string, string}> */
    public static function referenceValueProvider(): array
    {
        return [
            'country code' => ['KE', 'XX', 'countryCodes'],
            'country name' => ['Kenya', 'Atlantis', 'countryNames'],
            'currency' => ['KES', 'ZZZ', 'currencies'],
            'language' => ['en', 'zz', 'languages'],
            'timezone' => ['Africa/Nairobi', 'Mars/Olympus', 'timezones'],
        ];
    }

    #[DataProvider('requestProvider')]
    public function test_reference_request_rules_use_the_generated_catalogue(string $relativePath): void
    {
        $source = (string) file_get_contents(app_path('Http/Requests/'.$relativePath));

        $this->assertStringContainsString('ReferenceCatalogue::', $source);
    }

    /** @return array<string, array{string}> */
    public static function requestProvider(): array
    {
        return [
            'business calendar' => ['StoreBusinessCalendarRequest.php'],
            'data asset' => ['StoreDataAssetRequest.php'],
            'devolution project' => ['StoreDevolutionProjectRequest.php'],
            'DSWG series' => ['StoreDswgMeetingSeriesRequest.php'],
            'exchequer' => ['StoreExchequerRequestRequest.php'],
            'innovation funding' => ['StoreInnovationFundingDecisionRequest.php'],
            'knowledge item' => ['StoreKnowledgeItemRequest.php'],
            'learning course' => ['StoreLearningCourseRequest.php'],
            'partner agreement' => ['StorePartnerAgreementRequest.php'],
            'partner contribution' => ['StorePartnerContributionRequest.php'],
            'partner profile' => ['StorePartnerProfileRequest.php'],
            'programme store' => ['StoreProgrammeRequest.php'],
            'programme update' => ['UpdateProgrammeRequest.php'],
            'project budget' => ['StoreProjectBudgetLineRequest.php'],
            'project procurement' => ['StoreProjectProcurementRequest.php'],
            'travel clearance' => ['StoreTravelRequestRequest.php'],
        ];
    }
}
