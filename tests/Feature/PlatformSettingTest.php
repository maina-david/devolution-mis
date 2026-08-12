<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_update_a_setting_and_audit_is_recorded(): void
    {
        $setting = PlatformSetting::factory()->create(['value' => 'design']);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->patch(route('platform-settings.update', [$setting]), ['value' => 'sandbox'])->assertRedirect();

        $this->assertSame('sandbox', $setting->fresh()?->value);
        $this->assertSame($admin->id, $setting->fresh()?->updated_by);
        $this->assertSame('platform-setting.updated', AuditEvent::query()->sole()->action);
    }

    public function test_non_platform_admin_cannot_update_platform_settings(): void
    {
        $setting = PlatformSetting::factory()->create(['value' => 'design']);
        $admin = User::factory()->devolutionAdmin()->create();

        $this->actingAs($admin)->patch(route('platform-settings.update', [$setting]), ['value' => 'live'])->assertForbidden();
        $this->assertSame('design', $setting->fresh()?->value);
    }
}
