<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReconcilePartnerContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManagePartners->value) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['verified', 'exception', 'rejected'])],
            'verified_committed_amount' => ['required', 'decimal:0,2', 'min:0', 'max:90000000000000000'],
            'verified_disbursed_amount' => ['required', 'decimal:0,2', 'min:0', 'max:90000000000000000'],
            'verified_in_kind_value' => ['required', 'decimal:0,2', 'min:0', 'max:90000000000000000'],
            'source_reference' => ['required', 'string', 'max:255'],
            'review_note' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->string('decision')->toString() === 'verified' && $this->float('verified_disbursed_amount') > $this->float('verified_committed_amount')) {
                $validator->errors()->add('verified_disbursed_amount', 'A verified disbursement cannot exceed the verified commitment. Record an exception instead.');
            }
        }];
    }
}
