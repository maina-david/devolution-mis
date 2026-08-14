<?php

namespace App\Services;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class WorkflowRuleEvaluator
{
    /**
     * @param  list<array<string, mixed>>  $rules
     * @param  array<string, mixed>  $context
     * @return array{passed: bool, results: list<array{field: string, operator: string, passed: bool, actual: mixed, expected: mixed}>}
     */
    public function evaluate(array $rules, array $context): array
    {
        $results = [];

        foreach ($rules as $rule) {
            $field = $this->requiredString($rule, 'field');
            $operator = $this->requiredString($rule, 'operator');
            $expected = Arr::get($rule, 'value');
            $actual = data_get($context, $field);
            $results[] = [
                'field' => $field,
                'operator' => $operator,
                'passed' => $this->compare($actual, $operator, $expected),
                'actual' => $actual,
                'expected' => $expected,
            ];
        }

        return ['passed' => collect($results)->every('passed'), 'results' => $results];
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'eq' => $actual === $expected,
            'neq' => $actual !== $expected,
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'present' => $actual !== null && $actual !== '',
            'absent' => $actual === null || $actual === '',
            default => throw new InvalidArgumentException(__('workflow-management.engine.errors.unsupported_operator', ['operator' => $operator])),
        };
    }

    /** @param array<string, mixed> $rule */
    private function requiredString(array $rule, string $key): string
    {
        $value = Arr::get($rule, $key);

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(__('workflow-management.engine.errors.rule_string_required', ['key' => $key]));
        }

        return $value;
    }
}
