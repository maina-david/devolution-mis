<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIntegrationSystemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageIntegrations->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['owner_organization_id' => ['nullable', 'uuid', 'exists:organizations,id'], 'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', 'unique:integration_systems,code'], 'name' => ['required', 'string', 'max:255'], 'purpose' => ['required', 'string', 'max:5000'], 'system_owner' => ['required', 'string', 'max:255'], 'environment' => ['required', 'in:sandbox,test,production'], 'transport' => ['required', 'in:fixture,https_json,sftp'], 'auth_scheme' => ['required', 'in:none,oauth2_client_credentials,mtls,bearer_vault,sftp_key_vault'], 'credential_reference' => ['nullable', 'string', 'max:255'], 'base_url' => ['nullable', 'url', 'max:2000'], 'direction' => ['required', 'in:inbound,outbound,bidirectional'], 'data_classification' => ['required', 'in:public,official,confidential,restricted'], 'status' => ['required', 'in:design,contract_review,approved,active,suspended,retired']];
    }
}
