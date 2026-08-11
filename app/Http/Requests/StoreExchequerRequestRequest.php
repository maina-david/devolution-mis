<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchequerRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageGrants->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['county_grant_id' => ['required', 'uuid', 'exists:county_grants,id'], 'tranche_reference' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'min:0.01'], 'currency' => ['required', 'string', 'size:3', Rule::in(ReferenceCatalogue::currencies())]];
    }
}
