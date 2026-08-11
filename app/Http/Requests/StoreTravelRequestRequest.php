<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTravelRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitTravelRequests->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'county_id' => [Rule::requiredIf(fn (): bool => $this->user()?->programmeRole()->hasNationalScope() === false), 'nullable', 'uuid', 'exists:counties,id'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'],
            'travel_type' => ['required', 'in:domestic,international'],
            'purpose' => ['required', 'string', 'max:255'],
            'justification' => ['required', 'string', 'max:10000'],
            'destination_country' => ['required', 'string', 'max:100', Rule::in(ReferenceCatalogue::countryNames())],
            'destination_county' => ['nullable', 'string', 'max:100'],
            'destination_city' => ['required', 'string', 'max:100'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(ReferenceCatalogue::currencies())],
            'funding_source' => ['required', 'string', 'max:255'],
            'cost_centre' => ['nullable', 'string', 'max:100'],
            'hris_employee_reference' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'in:normal,urgent'],
            'itineraries' => ['required', 'array', 'min:1', 'max:20'],
            'itineraries.*.origin' => ['required', 'string', 'max:255'],
            'itineraries.*.destination' => ['required', 'string', 'max:255'],
            'itineraries.*.departs_at' => ['required', 'date'],
            'itineraries.*.arrives_at' => ['required', 'date', 'after:itineraries.*.departs_at'],
            'itineraries.*.transport_mode' => ['required', 'in:air,road,rail,water,other'],
            'itineraries.*.carrier' => ['nullable', 'string', 'max:255'],
            'itineraries.*.estimated_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $itineraries = collect($this->array('itineraries'))->filter(fn (mixed $itinerary): bool => is_array($itinerary));
            $itineraryCost = (float) $itineraries->sum(fn (array $itinerary): float => (float) ($itinerary['estimated_cost'] ?? 0));

            if ($itineraryCost > (float) $this->input('estimated_cost', 0)) {
                $validator->errors()->add('estimated_cost', 'The total request estimate must cover all itinerary segments.');
            }

            $departure = $this->date('departure_date');
            $return = $this->date('return_date');
            foreach ($itineraries as $index => $itinerary) {
                $segmentDeparture = isset($itinerary['departs_at']) ? Carbon::parse($itinerary['departs_at']) : null;
                $segmentArrival = isset($itinerary['arrives_at']) ? Carbon::parse($itinerary['arrives_at']) : null;
                if ($departure && $segmentDeparture?->isBefore($departure->startOfDay())) {
                    $validator->errors()->add("itineraries.{$index}.departs_at", 'The segment cannot depart before the travel start date.');
                }
                if ($return && $segmentArrival?->isAfter($return->endOfDay())) {
                    $validator->errors()->add("itineraries.{$index}.arrives_at", 'The segment cannot arrive after the travel return date.');
                }
            }
        }];
    }
}
