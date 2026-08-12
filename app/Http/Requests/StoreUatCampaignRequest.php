<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\UatCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUatCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageChangeReadiness->value) === true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', Rule::unique((new UatCampaign)->getTable(), 'code')],
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['required', 'string', 'min:30', 'max:3000'],
            'environment' => ['required', 'string', 'max:80'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'county_ids' => ['required', 'array', 'min:1', 'max:47'],
            'county_ids.*' => ['required', 'uuid', 'distinct', 'exists:counties,id'],
            'acceptance_criteria' => ['required', 'array', 'min:1', 'max:30'],
            'acceptance_criteria.*' => ['required', 'string', 'min:10', 'max:500'],
            'required_roles' => ['required', 'array', 'min:1', 'max:9'],
            'required_roles.*' => ['required', Rule::enum(UserRole::class), 'distinct'],
            'minimum_counties' => ['required', 'integer', 'min:1', 'max:47'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $countyIds = $this->input('county_ids');

                if (is_array($countyIds) && $this->integer('minimum_counties') > count(array_unique($countyIds))) {
                    $validator->errors()->add('minimum_counties', __('change-readiness.validation.minimum_counties'));
                }
            },
        ];
    }
}
