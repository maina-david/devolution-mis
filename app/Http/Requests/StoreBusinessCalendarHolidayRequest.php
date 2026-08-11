<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessCalendarHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageWorkflows->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $calendar = $this->route('businessCalendar');

        return ['holiday_date' => ['required', 'date', Rule::unique((new BusinessCalendarHoliday)->getTable())->where('business_calendar_id', $calendar instanceof BusinessCalendar ? $calendar->id : null)->whereNull('deleted_at')], 'name' => ['required', 'string', 'max:255'], 'category' => ['required', 'in:public_holiday,government_closure,exception'], 'source_reference' => ['required', 'string', 'min:5', 'max:500']];
    }
}
