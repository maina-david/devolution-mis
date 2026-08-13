<?php

namespace App\Services;

use App\Models\LearningQuestionBank;
use App\Models\LearningQuizQuestion;
use Illuminate\Database\Eloquent\Collection;

class LearningQuestionSelector
{
    /** @return Collection<int, LearningQuizQuestion> */
    public function select(LearningQuestionBank $bank, string $enrollmentId, int $attemptNumber): Collection
    {
        $bank->loadMissing('items.question');
        $seed = "{$bank->checksum}|{$enrollmentId}|{$attemptNumber}";
        $candidates = $bank->items->groupBy('variant_group')->map(function ($items, string $group) use ($seed) {
            $ordered = $items->sortBy(fn ($item): string => hash('sha256', "{$seed}|{$group}|{$item->learning_quiz_question_id}"))->values();

            return $ordered->first()->question;
        })->filter()->values();
        if ($bank->randomize_questions) {
            $candidates = $candidates->sortBy(fn (LearningQuizQuestion $question): string => hash('sha256', "{$seed}|question|{$question->id}"))->values();
        } else {
            $candidates = $candidates->sortBy(fn (LearningQuizQuestion $question): int => $bank->items->firstWhere('learning_quiz_question_id', $question->id)->sequence)->values();
        }

        return new Collection($candidates->take($bank->selection_count)->filter(fn (mixed $question): bool => $question instanceof LearningQuizQuestion)->all());
    }

    /** @return array<string, string> */
    public function options(LearningQuestionBank $bank, LearningQuizQuestion $question, string $enrollmentId, int $attemptNumber): array
    {
        $options = array_filter($question->options, is_string(...));
        if (! $bank->randomize_options) {
            return $options;
        }
        $seed = "{$bank->checksum}|{$enrollmentId}|{$attemptNumber}|{$question->id}";
        uksort($options, fn (string $left, string $right): int => hash('sha256', "{$seed}|{$left}") <=> hash('sha256', "{$seed}|{$right}"));

        return $options;
    }
}
