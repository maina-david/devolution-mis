<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\Sector;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSectorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageReferenceData->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_sector_id' => ['nullable', 'uuid', Rule::exists('sectors', 'id')->whereNull('deleted_at'), Rule::notIn([$this->sector()->id])],
            'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('sectors', 'code')->ignore($this->sector())],
            'name' => ['required', 'string', 'max:255', Rule::unique('sectors', 'name')->ignore($this->sector())],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentId = $this->string('parent_sector_id')->toString();

            if ($parentId === '' || $validator->errors()->has('parent_sector_id')) {
                return;
            }

            $sector = $this->sector();
            $visited = [];

            while ($parentId !== '') {
                if ($parentId === $sector->id || isset($visited[$parentId])) {
                    $validator->errors()->add('parent_sector_id', 'The selected parent would create a sector hierarchy cycle.');

                    return;
                }

                $visited[$parentId] = true;
                $parentId = (string) (Sector::query()->findOrFail($parentId)->parent_sector_id ?? '');
            }
        });
    }

    private function sector(): Sector
    {
        /** @var Sector $sector */
        $sector = $this->route('sector');

        return $sector;
    }
}
