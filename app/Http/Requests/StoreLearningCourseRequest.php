<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLearningCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageLearning->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:learning_courses,code'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:1000'],
            'description' => ['required', 'string', 'max:15000'],
            'category' => ['required', 'string', 'max:100'],
            'level' => ['required', 'in:foundation,intermediate,advanced'],
            'delivery_mode' => ['required', 'in:self_paced,blended,instructor_led'],
            'language' => ['required', 'string', 'size:2', Rule::in(ReferenceCatalogue::languages())],
            'passing_score' => ['required', 'numeric', 'between:0,100'],
            'maximum_attempts' => ['required', 'integer', 'between:1,10'],
            'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'question_bank' => ['nullable', 'array:selection_count,randomize_questions,randomize_options,description'],
            'question_bank.selection_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'question_bank.randomize_questions' => ['nullable', 'boolean'],
            'question_bank.randomize_options' => ['nullable', 'boolean'],
            'question_bank.description' => ['nullable', 'string', 'max:2000'],
            'modules' => ['required', 'array', 'min:1', 'max:30'],
            'modules.*.title' => ['required', 'string', 'max:255'],
            'modules.*.description' => ['nullable', 'string', 'max:3000'],
            'modules.*.lessons' => ['required', 'array', 'min:1', 'max:50'],
            'modules.*.lessons.*.title' => ['required', 'string', 'max:255'],
            'modules.*.lessons.*.summary' => ['nullable', 'string', 'max:1000'],
            'modules.*.lessons.*.content_type' => ['required', 'in:video,audio,text,quiz,toolkit,manual'],
            'modules.*.lessons.*.content_body' => ['nullable', 'required_if:modules.*.lessons.*.content_type,text', 'string', 'max:50000'],
            'modules.*.lessons.*.content_url' => ['nullable', 'url', 'max:2000'],
            'modules.*.lessons.*.estimated_minutes' => ['required', 'integer', 'min:0', 'max:10000'],
            'modules.*.lessons.*.is_downloadable' => ['boolean'],
            'modules.*.lessons.*.questions' => ['nullable', 'array', 'max:100'],
            'modules.*.lessons.*.questions.*.question' => ['required_with:modules.*.lessons.*.questions', 'string', 'max:3000'],
            'modules.*.lessons.*.questions.*.options' => ['required_with:modules.*.lessons.*.questions', 'array', 'min:2', 'max:10'],
            'modules.*.lessons.*.questions.*.correct_option' => ['required_with:modules.*.lessons.*.questions', 'string', 'max:10'],
            'modules.*.lessons.*.questions.*.explanation' => ['nullable', 'string', 'max:3000'],
            'modules.*.lessons.*.questions.*.points' => ['required_with:modules.*.lessons.*.questions', 'numeric', 'gt:0'],
            'modules.*.lessons.*.questions.*.variant_group' => ['nullable', 'string', 'max:100'],
            'modules.*.lessons.*.questions.*.difficulty' => ['nullable', Rule::in(['foundation', 'standard', 'advanced'])],
            'modules.*.lessons.*.questions.*.tags' => ['nullable', 'array', 'max:20'],
            'modules.*.lessons.*.questions.*.tags.*' => ['string', 'max:50'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('modules', []) as $moduleIndex => $module) {
                foreach (($module['lessons'] ?? []) as $lessonIndex => $lesson) {
                    if (($lesson['content_type'] ?? null) === 'quiz' && empty($lesson['questions'])) {
                        $validator->errors()->add("modules.{$moduleIndex}.lessons.{$lessonIndex}.questions", 'Quiz lessons require at least one question.');
                    }
                }
            }
            $selectionCount = $this->integer('question_bank.selection_count');
            $variantGroups = [];
            foreach ($this->array('modules') as $moduleIndex => $module) {
                foreach (($module['lessons'] ?? []) as $lessonIndex => $lesson) {
                    foreach (($lesson['questions'] ?? []) as $questionIndex => $question) {
                        $fallbackGroup = "question-{$moduleIndex}-{$lessonIndex}-{$questionIndex}";
                        $variantGroups[(string) ($question['variant_group'] ?? $fallbackGroup)] = true;
                    }
                }
            }
            if ($selectionCount > 0 && $selectionCount > count($variantGroups)) {
                $validator->errors()->add('question_bank.selection_count', 'Selection count cannot exceed the available question variant groups.');
            }
        }];
    }
}
