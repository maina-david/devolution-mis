<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'ifmis.integration.status', 'group' => 'Integrations', 'label' => 'IFMIS integration', 'value' => 'design', 'type' => 'status', 'description' => 'Readiness state for the National Treasury IFMIS data exchange.'],
            ['key' => 'ippd.integration.status', 'group' => 'Integrations', 'label' => 'IPPD integration', 'value' => 'design', 'type' => 'status', 'description' => 'Readiness state for county human-resource payroll exchange.'],
            ['key' => 'evidence.retention.years', 'group' => 'Records', 'label' => 'Evidence retention (years)', 'value' => '7', 'type' => 'number', 'description' => 'Minimum retention period for assessment evidence.'],
            ['key' => 'access.review.frequency', 'group' => 'Security', 'label' => 'Privileged access review', 'value' => 'quarterly', 'type' => 'text', 'description' => 'Required cadence for privileged access certification.'],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
