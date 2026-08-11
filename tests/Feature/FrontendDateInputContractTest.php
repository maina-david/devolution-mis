<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class FrontendDateInputContractTest extends TestCase
{
    public function test_frontend_uses_calendar_picker_instead_of_native_date_inputs(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('js')),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! in_array($file->getExtension(), ['tsx', 'jsx'], true)) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $this->assertIsString($source, "Unable to read {$file->getPathname()}.");
            $this->assertDoesNotMatchRegularExpression(
                '/type\s*=\s*["\'](?:date|datetime-local|time)["\']/',
                $source,
                "Native date/time control found in {$file->getPathname()}; use DatePickerField or TimePickerField.",
            );
        }
    }

    public function test_shared_date_time_picker_follows_shadcn_calendar_composition(): void
    {
        $source = file_get_contents(resource_path('js/components/date-picker-field.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('<Popover>', $source);
        $this->assertStringContainsString('<Calendar', $source);
        $this->assertStringContainsString('<TimePickerField', $source);
        $this->assertStringContainsString('includeTime', $source);

        $timeSource = file_get_contents(resource_path('js/components/time-picker-field.tsx'));
        $this->assertIsString($timeSource);
        $this->assertStringContainsString('<SearchableSelect', $timeSource);
        $this->assertStringNotContainsString('type="time"', $timeSource);
    }
}
