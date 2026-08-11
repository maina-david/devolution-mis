<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\User;
use App\Models\VirtualClassroom;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordVirtualClassroomAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $classroom = $this->route('classroom');

        return $user instanceof User
            && $classroom instanceof VirtualClassroom
            && ($classroom->facilitator_id === $user->id || $user->can(ProgrammePermission::ManageLearning->value));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'learning_enrollment_id' => ['required', 'uuid', 'exists:learning_enrollments,id'],
            'attendance_status' => ['required', Rule::in(['present', 'partial', 'absent'])],
            'joined_at' => [Rule::requiredIf($this->input('attendance_status') !== 'absent'), 'nullable', 'date'],
            'left_at' => [Rule::requiredIf($this->input('attendance_status') !== 'absent'), 'nullable', 'date', 'after:joined_at'],
            'source' => ['required', Rule::in(['manual', 'provider_import'])],
            'provider_event_id' => [Rule::requiredIf($this->input('source') === 'provider_import'), 'nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
