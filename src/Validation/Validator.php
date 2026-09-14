<?php

declare(strict_types=1);

namespace SedoPHP\Validation;

use SedoPHP\Database\Database;
use SedoPHP\Http\UploadedFile;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string>> $rules
     * @return array<string, list<string>>
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $pattern => $definition) {
            $fieldRules = is_array($definition) ? $definition : explode('|', $definition);
            $matches = self::expand($data, (string) $pattern);

            foreach ($matches as [$field, $value]) {
                $nullable = in_array('nullable', $fieldRules, true);

                if ($nullable && self::isEmpty($value)) {
                    continue;
                }

                foreach ($fieldRules as $rule) {
                    if ($rule === 'nullable') {
                        continue;
                    }

                    [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                    $message = self::check(
                        $name,
                        $parameter,
                        $field,
                        (string) $pattern,
                        $value,
                        $data,
                        $fieldRules,
                    );

                    if ($message !== null) {
                        $errors[$field][] = $message;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $fieldRules
     */
    private static function check(
        string $rule,
        ?string $parameter,
        string $field,
        string $pattern,
        mixed $value,
        array $data,
        array $fieldRules,
    ): ?string {
        $empty = self::isEmpty($value);
        $numericMode = in_array('numeric', $fieldRules, true) || in_array('integer', $fieldRules, true);

        $reference = $parameter === null ? null : self::resolveReference($parameter, $pattern, $field);
        $confirmedReference = $field . '_confirmation';

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
            'same' => self::dataGet($data, (string) $reference) !== $value ? "{$field} must match {$reference}." : null,
            'confirmed' => self::dataGet($data, $confirmedReference) !== $value ? "{$field} confirmation does not match." : null,
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

    /**
     * @param array<string, mixed> $data
     * @return list<array{0:string,1:mixed}>
     */
    private static function expand(array $data, string $pattern): array
    {
        if (!str_contains($pattern, '*')) {
            return [[$pattern, self::dataGet($data, $pattern)]];
        }

        $results = [];
        self::expandSegments($data, explode('.', $pattern), [], $results);
        return $results;
    }

    /**
     * @param list<string> $segments
     * @param list<string> $path
     * @param list<array{0:string,1:mixed}> $results
     */
    private static function expandSegments(mixed $current, array $segments, array $path, array &$results): void
    {
        if ($segments === []) {
            $results[] = [implode('.', $path), $current];
            return;
        }

        $segment = array_shift($segments);
        if ($segment === '*') {
            if (!is_array($current)) {
                return;
            }

            foreach ($current as $key => $value) {
                self::expandSegments($value, $segments, [...$path, (string) $key], $results);
            }
            return;
        }

        $next = is_array($current) && array_key_exists($segment, $current) ? $current[$segment] : null;
        self::expandSegments($next, $segments, [...$path, $segment], $results);
    }

    /** @param array<string, mixed> $data */
    private static function dataGet(array $data, string $path): mixed
    {
        if ($path === '') {
            return null;
        }

        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private static function resolveReference(string $reference, string $pattern, string $field): string
    {
        if (!str_contains($reference, '*')) {
            return $reference;
        }

        $patternSegments = explode('.', $pattern);
        $fieldSegments = explode('.', $field);
        $wildcards = [];

        foreach ($patternSegments as $index => $segment) {
            if ($segment === '*' && isset($fieldSegments[$index])) {
                $wildcards[] = $fieldSegments[$index];
            }
        }

        $offset = 0;
        return preg_replace_callback('/\*/', static function () use (&$offset, $wildcards): string {
            return $wildcards[$offset++] ?? '*';
        }, $reference) ?? $reference;
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value instanceof UploadedFile) {
            return !$value->isValid();
        }

        if (is_array($value)) {
            return $value === [];
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return $value === null;
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
        [$table, $column] = array_pad(explode(',', (string) $parameter, 2), 2, null);
        $table = trim((string) $table);

        if ($table === '') {
            return false;
        }

        $column = trim((string) $column);
        if ($column === '') {
            $segments = explode('.', $field);
            $column = (string) end($segments);
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
