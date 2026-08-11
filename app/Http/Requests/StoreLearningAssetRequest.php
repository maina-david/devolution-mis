<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLearningAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageLearning->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'source_type' => ['required', 'in:scanned,digital'],
            'rights_holder' => ['required', 'string', 'max:255'],
            'licence' => ['required', 'in:government_open,permission_granted,third_party_restricted,internal_training'],
            'accessible_alternative' => ['required', 'string', 'max:2000'],
            'transcript_available' => ['required', 'boolean'],
            'is_downloadable' => ['required', 'boolean'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,tif,tiff,doc,docx,xls,xlsx,csv,txt,mp4,webm,mp3,wav,m4a', 'max:512000'],
        ];
    }
}
