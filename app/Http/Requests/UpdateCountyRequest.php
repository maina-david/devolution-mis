<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\County;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCountyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageReferenceData->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $county = $this->county();

        return [
            'code' => ['required', 'integer', 'between:1,999', Rule::unique('counties', 'code')->ignore($county)],
            'name' => ['required', 'string', 'max:255', Rule::unique('counties', 'name')->ignore($county)],
            'region' => ['nullable', 'string', 'max:255'],
            'official_website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'map_x' => ['required', 'numeric', 'between:0,100'],
            'map_y' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    private function county(): County
    {
        /** @var County $county */
        $county = $this->route('county');

        return $county;
    }
}
