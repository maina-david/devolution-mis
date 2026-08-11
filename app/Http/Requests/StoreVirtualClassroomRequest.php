<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVirtualClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageLearning->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['learning_course_id' => ['required', 'uuid', 'exists:learning_courses,id'], 'facilitator_id' => ['required', 'uuid', 'exists:users,id'], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'platform' => ['required', 'string', 'max:100'], 'join_url' => ['required', 'url', 'max:2000'], 'capacity' => ['nullable', 'integer', 'min:1'], 'status' => ['required', 'in:scheduled,live,completed,cancelled']];
    }
}
