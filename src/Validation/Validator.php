<?php

declare(strict_types=1);

namespace SedoPHP\Validation;

final class Validator
{
    /** @param array<string, mixed> $data @param array<string, string|array<int, string>> $rules @return array<string, list<string>> */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $definition) {
            $fieldRules = is_array($definition) ? $definition : explode('|', $definition);
            $value = $data[$field] ?? null;
            $nullable = in_array('nullable', $fieldRules, true);

            if ($nullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($fieldRules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $message = self::check($name, $parameter, $field, $value, $data);
                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $data */
    private static function check(string $rule, ?string $parameter, string $field, mixed $value, array $data): ?string
    {
        $empty = $value === null || $value === '';

        return match ($rule) {
            'required' => $empty ? "{$field} is required." : null,
            'string' => !$empty && !is_string($value) ? "{$field} must be a string." : null,
            'integer' => !$empty && filter_var($value, FILTER_VALIDATE_INT) === false ? "{$field} must be an integer." : null,
            'boolean' => !$empty && filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null ? "{$field} must be a boolean." : null,
            'email' => !$empty && filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "{$field} must be a valid email address." : null,
            'url' => !$empty && filter_var($value, FILTER_VALIDATE_URL) === false ? "{$field} must be a valid URL." : null,
            'min' => !$empty && self::length($value) < (int) $parameter ? "{$field} must be at least {$parameter} characters." : null,
            'max' => !$empty && self::length($value) > (int) $parameter ? "{$field} may not be greater than {$parameter} characters." : null,
            'same' => ($data[$parameter ?? ''] ?? null) !== $value ? "{$field} must match {$parameter}." : null,
            'confirmed' => ($data[$field . '_confirmation'] ?? null) !== $value ? "{$field} confirmation does not match." : null,
            'in' => !$empty && !in_array((string) $value, explode(',', (string) $parameter), true) ? "{$field} has an invalid value." : null,
            default => "Unknown validation rule: {$rule}.",
        };
    }

    private static function length(mixed $value): int
    {
        $string = (string) $value;
        return function_exists('mb_strlen') ? mb_strlen($string) : strlen($string);
    }
}
