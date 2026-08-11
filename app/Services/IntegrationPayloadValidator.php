<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class IntegrationPayloadValidator
{
    /** @param array<string, mixed> $payload
     * @param  array<string, mixed>  $schema
     */
    public function validate(array $payload, array $schema): void
    {
        $errors = [];
        foreach ((array) ($schema['required'] ?? []) as $field) {
            if (! is_string($field) || ! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                $errors["payload.{$field}"] = "The {$field} field is required by the published interface contract.";
            }
        }

        foreach ((array) ($schema['properties'] ?? []) as $field => $definition) {
            if (! is_string($field) || ! is_array($definition) || ! array_key_exists($field, $payload)) {
                continue;
            }
            $type = $definition['type'] ?? null;
            if (is_string($type) && ! $this->matchesType($payload[$field], $type)) {
                $errors["payload.{$field}"] = "The {$field} field must be {$type}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) && ! array_is_list($value),
            'null' => $value === null,
            default => false,
        };
    }
}
