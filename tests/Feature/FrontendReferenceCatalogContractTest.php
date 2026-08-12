<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrontendReferenceCatalogContractTest extends TestCase
{
    public function test_reference_catalogue_dependencies_and_iso_iana_sources_are_declared(): void
    {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        $dependencies = $package['dependencies'];

        $this->assertArrayHasKey('countries-and-timezones', $dependencies);
        $this->assertArrayHasKey('currency-codes', $dependencies);
        $this->assertArrayHasKey('iso-639-1', $dependencies);
        $this->assertArrayHasKey('iso-639-3', $dependencies);
        $this->assertArrayHasKey('country-state-city', $dependencies);

        $source = (string) file_get_contents(resource_path('js/lib/reference-catalog.ts'));
        $this->assertStringContainsString("from 'countries-and-timezones'", $source);
        $this->assertStringContainsString("from 'currency-codes'", $source);
        $this->assertStringContainsString("from 'iso-639-1'", $source);
        $this->assertStringContainsString('getAllCountries()', $source);
        $this->assertStringContainsString('getAllTimezones()', $source);
        $this->assertStringContainsString('ISO6391.getAllCodes()', $source);
        $this->assertStringContainsString("from '../../reference-catalog.json'", $source);
        $this->assertStringContainsString('export const DEFAULT_LOCALE', $source);
        $this->assertStringContainsString('export function formatCurrency', $source);
        $this->assertStringContainsString('export function formatDateTime', $source);

        $generator = (string) file_get_contents(resource_path('js/lib/generate-reference-catalog.mjs'));
        $this->assertStringContainsString("from 'iso-639-3'", $generator);
        $this->assertStringContainsString("packageJson.dependencies['country-state-city']", $generator);

        $geographyFields = (string) file_get_contents(resource_path('js/components/geography-catalog-fields.tsx'));
        $this->assertStringContainsString("from 'country-state-city'", $geographyFields);
        $this->assertStringContainsString('State.getStatesOfCountry', $geographyFields);
        $this->assertStringContainsString('City.getCitiesOfState', $geographyFields);
        $this->assertStringContainsString("import('country-state-city')", $geographyFields);

        $formSheet = (string) file_get_contents(resource_path('js/components/form-sheet.tsx'));
        $this->assertStringContainsString('{open &&', $formSheet);
    }

    public function test_operational_sources_do_not_embed_reference_default_literals(): void
    {
        $forbidden = "/['\"](?:KES|Kenya|KE|Africa\\/Nairobi|en-KE|en_US|eng)['\"]/";
        $paths = [app_path(), config_path(), database_path('factories'), database_path('seeders'), resource_path('js')];
        $violations = [];

        foreach ($paths as $path) {
            foreach (File::allFiles($path) as $file) {
                $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                if (in_array($relativePath, ['resources/js/lib/generate-reference-catalog.mjs', 'resources/js/lib/reference-catalog.ts'], true)) {
                    continue;
                }
                if (preg_match($forbidden, (string) file_get_contents($file->getPathname())) === 1) {
                    $violations[] = $relativePath;
                }
            }
        }

        $this->assertSame([], $violations, 'Operational reference literals remain: '.implode(', ', $violations));
    }

    #[DataProvider('formattedSurfaceProvider')]
    public function test_locale_sensitive_surfaces_use_package_derived_formatters(string $relativePath): void
    {
        $source = (string) file_get_contents(resource_path('js/'.$relativePath));

        $this->assertStringContainsString("from '@/lib/reference-catalog'", $source);
        $this->assertStringNotContainsString("currency: 'KES'", $source);
        $this->assertStringNotContainsString("Intl.NumberFormat('en-KE'", $source);
    }

    /** @return array<string, array{string}> */
    public static function formattedSurfaceProvider(): array
    {
        return [
            'dashboard' => ['pages/dashboard.tsx'],
            'county detail' => ['pages/counties/show.tsx'],
            'exchequer' => ['pages/exchequer/index.tsx'],
        ];
    }

    #[DataProvider('catalogueConsumerProvider')]
    public function test_reference_inputs_use_the_shared_package_backed_catalogue(string $relativePath): void
    {
        $source = (string) file_get_contents(resource_path('js/'.$relativePath));

        $this->assertStringContainsString('ReferenceCatalogSelect', $source);
    }

    /** @return array<string, array{string}> */
    public static function catalogueConsumerProvider(): array
    {
        return [
            'travel country and currency' => ['pages/travel-clearance/index.tsx'],
            'project initiation currency' => ['components/project-initiation-form.tsx'],
            'project register currencies' => ['pages/projects/show.tsx'],
            'partner currencies' => ['components/partner-coordination-forms.tsx'],
            'programme currency' => ['pages/reference-data/index.tsx'],
            'workflow timezone' => ['pages/workflows/index.tsx'],
            'DSWG timezone' => ['components/dswg-coordination-forms.tsx'],
            'learning language' => ['pages/learning/index.tsx'],
            'knowledge language and currency' => ['pages/knowledge/index.tsx'],
            'data residency country' => ['pages/data-governance/index.tsx'],
            'travel destination geography' => ['components/geography-catalog-fields.tsx'],
        ];
    }
}
