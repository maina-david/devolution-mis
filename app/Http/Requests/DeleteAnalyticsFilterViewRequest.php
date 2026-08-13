<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\AnalyticsFilterView;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAnalyticsFilterViewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $view = $this->route('filterView');

        return $this->user()?->can(ProgrammePermission::ViewAnalytics->value) === true
            && $view instanceof AnalyticsFilterView
            && $view->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
