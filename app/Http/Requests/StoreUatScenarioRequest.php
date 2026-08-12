<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\UatCampaign;
use App\Models\UatScenario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUatScenarioRequest extends FormRequest
{
    private const MODULES = ['citizen-feedback', 'e-learning', 'partner-coordination', 'dswg-coordination', 'project-management', 'departmental-performance', 'monitoring-evaluation', 'grievance-redress', 'central-repository', 'analytics-reporting', 'igr-resolutions', 'devolution-assessment', 'travel-clearance', 'knowledge-management', 'shared-platform'];

    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageChangeReadiness->value) === true;
    }

    public function rules(): array
    {
        $campaign = $this->route('campaign');
        $campaignId = $campaign instanceof UatCampaign ? $campaign->id : null;

        return [
            'code' => ['required', 'string', 'max:80', Rule::unique((new UatScenario)->getTable(), 'code')->where('uat_campaign_id', $campaignId)],
            'module' => ['required', Rule::in(self::MODULES)],
            'title' => ['required', 'string', 'max:255'],
            'actor_role' => ['required', Rule::enum(UserRole::class)],
            'priority' => ['required', Rule::in(['critical', 'high', 'normal', 'low'])],
            'journey' => ['required', 'string', 'min:20', 'max:3000'],
            'preconditions' => ['required', 'array', 'min:1', 'max:20'],
            'preconditions.*' => ['required', 'string', 'max:500'],
            'steps' => ['required', 'array', 'min:1', 'max:30'],
            'steps.*' => ['required', 'string', 'max:500'],
            'expected_result' => ['required', 'string', 'min:20', 'max:3000'],
            'accessibility_needs' => ['nullable', 'string', 'max:2000'],
            'low_connectivity_variant' => ['nullable', 'string', 'max:2000'],
            'required' => ['required', 'boolean'],
        ];
    }
}
