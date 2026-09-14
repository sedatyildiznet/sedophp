<?php

declare(strict_types=1);

namespace SedoPHP\Validation;

use SedoPHP\Database\Database;
use SedoPHP\Http\UploadedFile;

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
                $message = self::check($name, $parameter, $field, $value, $data, $fieldRules);

                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $data @param array<int, string> $fieldRules */
    private static function check(
        string $rule,
        ?string $parameter,
        string $field,
        mixed $value,
        array $data,
        array $fieldRules,
    ): ?string {
        $empty = $value === null || $value === '';
        $numericMode = in_array('numeric', $fieldRules, true) || in_array('integer', $fieldRules, true);

        return match ($rule) {
            'required' => $empty ? "{$field} is required." : null,
            'string' => !$empty && !is_string($value) ? "{$field} must be a string." : null,
            'integer' => !$empty && filter_var($value, FILTER_VALIDATE_INT) === false ? "{$field} must be an integer." : null,
            'numeric' => !$empty && !is_numeric($value) ? "{$field} must be numeric." : null,
            'boolean' => !$empty && filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null ? "{$field} must be a boolean." : null,
            'array' => !$empty && !is_array($value) ? "{$field} must be an array." : null,
            'email' => !$empty && filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "{$field} must be a valid email address." : null,
            'url' => !$empty && filter_var($value, FILTER_VALIDATE_URL) === false ? "{$field} must be a valid URL." : null,
            'date' => !$empty && strtotime((string) $value) === false ? "{$field} must be a valid date." : null,
            'min' => !$empty && self::measure($value, $numericMode) < (float) $parameter ? "{$field} must be at least {$parameter}." : null,
            'max' => !$empty && self::measure($value, $numericMode) > (float) $parameter ? "{$field} may not be greater than {$parameter}." : null,
            'size' => !$empty && self::measure($value, $numericMode) !== (float) $parameter ? "{$field} must have size {$parameter}." : null,
            'same' => ($data[$parameter ?? ''] ?? null) !== $value ? "{$field} must match {$parameter}." : null,
            'confirmed' => ($data[$field . '_confirmation'] ?? null) !== $value ? "{$field} confirmation does not match." : null,
            'in' => !$empty && !in_array((string) $value, explode(',', (string) $parameter), true) ? "{$field} has an invalid value." : null,
            'regex' => !$empty && ($parameter === null || @preg_match($parameter, (string) $value) !== 1) ? "{$field} format is invalid." : null,
            'unique' => !$empty && self::databaseExists($parameter, $field, $value) ? "{$field} has already been taken." : null,
            'exists' => !$empty && !self::databaseExists($parameter, $field, $value) ? "{$field} does not exist." : null,
            'file' => !$empty && !($value instanceof UploadedFile && $value->isValid()) ? "{$field} must be a valid uploaded file." : null,
            'image' => !$empty && !($value instanceof UploadedFile && $value->isImage()) ? "{$field} must be a valid image." : null,
            'mimes' => !$empty && !self::validMimes($value, $parameter) ? "{$field} has an invalid file extension." : null,
            default => "Unknown validation rule: {$rule}.",
        };
    }

    private static function measure(mixed $value, bool $numericMode): float
    {
        if ($value instanceof UploadedFile) {
            return $value->size() / 1024;
        }

        if (is_array($value)) {
            return (float) count($value);
        }

        if ($numericMode && is_numeric($value)) {
            return (float) $value;
        }

        $string = (string) $value;
        return (float) (function_exists('mb_strlen') ? mb_strlen($string) : strlen($string));
    }

    private static function databaseExists(?string $parameter, string $field, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', (string) $parameter, 2), 2, $field);

        if ($table === '') {
            return false;
        }

        return Database::table($table)->where($column, $value)->exists();
    }

    private static function validMimes(mixed $value, ?string $parameter): bool
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            return false;
        }

        $allowed = array_map('strtolower', array_filter(array_map('trim', explode(',', (string) $parameter))));
        return $allowed !== [] && in_array($value->extension(), $allowed, true);
    }
}
