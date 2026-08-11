<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\Programme;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProgrammeCountyCoverageRequest extends FormRequest
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
        return [
            'programme_id' => ['required', 'uuid', Rule::exists('programmes', 'id')->whereNull('deleted_at')],
            'county_id' => ['required', 'uuid', Rule::exists('counties', 'id')->whereNull('deleted_at')],
            'implementation_lead_id' => ['nullable', 'uuid', Rule::exists('organizations', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('status', 'active'))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['planned', 'active', 'paused', 'closed'])],
            'funding_allocation' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'uppercase', Rule::in(ReferenceCatalogue::currencies())],
            'source_reference' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $programme = Programme::query()->find($this->string('programme_id')->toString());
            $startsOn = $this->date('starts_on');
            $endsOn = $this->filled('ends_on') ? $this->date('ends_on') : null;

            if ($programme?->starts_on && $startsOn?->isBefore($programme->starts_on)) {
                $validator->errors()->add('starts_on', 'County coverage cannot begin before the programme starts.');
            }

            if ($programme?->ends_on && ($endsOn === null || $endsOn->isAfter($programme->ends_on))) {
                $validator->errors()->add('ends_on', 'County coverage must end on or before the programme end date.');
            }
        }];
    }
}
