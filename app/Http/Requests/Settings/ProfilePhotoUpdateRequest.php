<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ProfilePhotoUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image()->max(2 * 1024),
                'mimes:jpg,jpeg,png,webp',
                Rule::dimensions()->minWidth(256)->minHeight(256)->maxWidth(2048)->maxHeight(2048),
            ],
        ];
    }

    public function photo(): UploadedFile
    {
        /** @var UploadedFile $photo */
        $photo = $this->file('photo');

        return $photo;
    }
}
