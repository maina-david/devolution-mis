<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssessmentScorecardVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageAssessmentConfiguration->value) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'change_notes' => ['nullable', 'string', 'max:5000'],
            'calculation_method' => ['required', Rule::in(['weighted_sum', 'mcda'])],
            'mcda_configuration' => ['required', 'array:normalization,aggregation,missing_data'],
            'mcda_configuration.normalization' => ['required', Rule::in(['none', 'min_max', 'benefit_cost', 'percentage'])],
            'mcda_configuration.aggregation' => ['required', Rule::in(['weighted_sum', 'weighted_product'])],
            'mcda_configuration.missing_data' => ['required', Rule::in(['incomplete', 'zero', 'exclude'])],
            'performance_thresholds' => ['required', 'array', 'min:1'],
            'performance_thresholds.*' => ['array:label,minimum,maximum,color'],
            'performance_thresholds.*.label' => ['required', 'string', 'max:100'],
            'performance_thresholds.*.minimum' => ['required', 'numeric', 'between:0,100'],
            'performance_thresholds.*.maximum' => ['required', 'numeric', 'between:0,100'],
            'performance_thresholds.*.color' => ['nullable', 'string', 'max:30'],
            'functions' => ['required', 'array', 'min:14'],
            'functions.*' => ['array:code,name,description,function_type,weight,sequence,thematic_areas'],
            'functions.*.code' => ['required', 'string', 'max:80', 'distinct'],
            'functions.*.name' => ['required', 'string', 'max:255'],
            'functions.*.description' => ['nullable', 'string', 'max:5000'],
            'functions.*.function_type' => ['required', Rule::in(['devolved', 'enabler'])],
            'functions.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'functions.*.sequence' => ['required', 'integer', 'min:1'],
            'functions.*.thematic_areas' => ['required', 'array', 'min:1'],
            'functions.*.thematic_areas.*' => ['array:code,name,description,weight,sequence,standards'],
            'functions.*.thematic_areas.*.code' => ['required', 'string', 'max:80'],
            'functions.*.thematic_areas.*.name' => ['required', 'string', 'max:255'],
            'functions.*.thematic_areas.*.description' => ['nullable', 'string', 'max:5000'],
            'functions.*.thematic_areas.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'functions.*.thematic_areas.*.sequence' => ['required', 'integer', 'min:1'],
            'functions.*.thematic_areas.*.standards' => ['required', 'array', 'min:1'],
            'functions.*.thematic_areas.*.standards.*' => ['array:code,name,description,norm_reference,weight,sequence,criteria'],
            'functions.*.thematic_areas.*.standards.*.code' => ['required', 'string', 'max:80'],
            'functions.*.thematic_areas.*.standards.*.name' => ['required', 'string', 'max:255'],
            'functions.*.thematic_areas.*.standards.*.description' => ['nullable', 'string', 'max:5000'],
            'functions.*.thematic_areas.*.standards.*.norm_reference' => ['nullable', 'string', 'max:5000'],
            'functions.*.thematic_areas.*.standards.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'functions.*.thematic_areas.*.standards.*.sequence' => ['required', 'integer', 'min:1'],
            'functions.*.thematic_areas.*.standards.*.criteria' => ['required', 'array', 'min:1'],
            'functions.*.thematic_areas.*.standards.*.criteria.*' => ['array:code,name,description,weight,maximum_score,scoring_method,formula,thresholds,is_mandatory,sequence,evidence_requirements'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.code' => ['required', 'string', 'max:80'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.name' => ['required', 'string', 'max:255'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.description' => ['nullable', 'string', 'max:5000'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.maximum_score' => ['required', 'numeric', 'gt:0'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.scoring_method' => ['required', Rule::in(['binary', 'scale', 'formula'])],
            'functions.*.thematic_areas.*.standards.*.criteria.*.formula' => ['present', 'array'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.thresholds' => ['present', 'array'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.is_mandatory' => ['required', 'boolean'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.sequence' => ['required', 'integer', 'min:1'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements' => ['required', 'array', 'min:1'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*' => ['array:code,name,description,minimum_documents,allowed_categories,accepted_mime_types,requires_verification,is_mandatory'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.code' => ['required', 'string', 'max:80'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.name' => ['required', 'string', 'max:255'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.description' => ['nullable', 'string', 'max:5000'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.minimum_documents' => ['required', 'integer', 'min:1', 'max:100'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.allowed_categories' => ['present', 'array'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.allowed_categories.*' => ['string', 'max:80'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.accepted_mime_types' => ['present', 'array'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.accepted_mime_types.*' => ['string', 'max:150'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.requires_verification' => ['required', 'boolean'],
            'functions.*.thematic_areas.*.standards.*.criteria.*.evidence_requirements.*.is_mandatory' => ['required', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $functions = $this->input('functions', []);
            $devolvedCount = collect(is_array($functions) ? $functions : [])->where('function_type', 'devolved')->count();
            if ($devolvedCount !== 14) {
                $validator->errors()->add('functions', 'A scorecard must define exactly fourteen devolved functions; enabler themes may be added separately.');
            }

            $thresholds = $this->input('performance_thresholds', []);
            foreach (is_array($thresholds) ? $thresholds : [] as $index => $threshold) {
                if (is_array($threshold) && ($threshold['minimum'] ?? 0) > ($threshold['maximum'] ?? 100)) {
                    $validator->errors()->add("performance_thresholds.{$index}.maximum", 'The maximum must be greater than or equal to the minimum.');
                }
            }
        }];
    }
}
