<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrantRequest extends FormRequest
{
    /** @return array{allocated_amount: float, disbursed_amount: float, status: string} */
    public function grantData(): array
    {
        return [
            'allocated_amount' => $this->float('allocated_amount'),
            'disbursed_amount' => $this->float('disbursed_amount'),
            'status' => $this->string('status')->toString(),
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageGrants->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'disbursed_amount' => ['required', 'numeric', 'min:0', 'lte:allocated_amount'],
            'status' => ['required', Rule::in(['planned', 'processing', 'approved', 'disbursed', 'received'])],
        ];
    }
}
