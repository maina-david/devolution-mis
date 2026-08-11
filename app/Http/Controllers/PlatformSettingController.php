<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePlatformSettingRequest;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlatformSettingController extends Controller
{
    public function update(UpdatePlatformSettingRequest $request, string $currentTeam, PlatformSetting $setting, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $previous = $setting->value;
        $setting->update(['value' => $request->validated('value'), 'updated_by' => $actor->id]);
        $auditLogger->record($actor, $setting, 'platform-setting.updated', "Platform setting updated: {$setting->label}.", metadata: ['previous' => $previous, 'current' => $setting->value]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Platform setting updated.']);

        return back();
    }
}
