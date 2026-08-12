<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;

class SubmitUatCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageChangeReadiness->value) === true;
    }

    public function rules(): array
    {
        return ['criteria_confirmed' => ['accepted']];
    }
}
