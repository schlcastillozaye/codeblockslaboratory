<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Server-side validator. JavaScript never replaces these rules.
 */
final class Validator
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        foreach ($rules as $field => $ruleString) {
            $this->validateField($field, $ruleString);
        }
    }

    /** @param array<string, mixed> $data @param array<string, string> $rules */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    private function validateField(string $field, string $ruleString): void
    {
        $rules = explode('|', $ruleString);
        $value = $this->data[$field] ?? null;
        $nullable = in_array('nullable', $rules, true);
        $isMissing = $value === null || (is_string($value) && trim($value) === '');

        if ($isMissing && $nullable) {
            $this->validated[$field] = null;
            return;
        }

        foreach ($rules as $rule) {
            if ($rule === '' || $rule === 'nullable') {
                continue;
            }
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

            if ($this->failsRule($name, $value, $param, $field)) {
                return; // stop at first error per field
            }
        }

        $this->validated[$field] = is_string($value) ? trim($value) : $value;
    }

    private function failsRule(string $rule, mixed $value, ?string $param, string $field): bool
    {
        $label = str_replace('_', ' ', $field);

        switch ($rule) {
            case 'required':
                if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
                    return $this->fail($field, ucfirst($label) . ' is required.');
                }
                return false;

            case 'email':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $this->fail($field, 'Enter a valid email address.');
                }
                return false;

            case 'integer':
                if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', trim($value)))) {
                    return $this->fail($field, ucfirst($label) . ' must be a whole number.');
                }
                return false;

            case 'numeric':
                if (!is_numeric($value)) {
                    return $this->fail($field, ucfirst($label) . ' must be a number.');
                }
                return false;

            case 'min':
                $min = (float) $param;
                if (is_numeric($value) && (float) $value < $min) {
                    return $this->fail($field, ucfirst($label) . ' must be at least ' . $param . '.');
                }
                if (is_string($value) && mb_strlen(trim($value)) < $min) {
                    return $this->fail($field, ucfirst($label) . ' must be at least ' . (int) $min . ' characters.');
                }
                return false;

            case 'max':
                $max = (float) $param;
                if (is_numeric($value) && (float) $value > $max) {
                    return $this->fail($field, ucfirst($label) . ' must not exceed ' . $param . '.');
                }
                if (is_string($value) && mb_strlen(trim($value)) > $max) {
                    return $this->fail($field, ucfirst($label) . ' must not exceed ' . (int) $max . ' characters.');
                }
                return false;

            case 'date':
                $parsed = is_string($value) ? date_create($value) : false;
                if (!$parsed) {
                    return $this->fail($field, ucfirst($label) . ' must be a valid date.');
                }
                return false;

            case 'after_or_equal':
                $date = is_string($value) ? date_create($value) : false;
                if (!$date) {
                    return $this->fail($field, 'Enter a valid date.');
                }
                $target = $param === 'today' ? new \DateTimeImmutable('today') : new \DateTimeImmutable((string) $param);
                if ($date->setTime(0, 0) < $target->setTime(0, 0)) {
                    return $this->fail($field, ucfirst($label) . ' cannot be in the past.');
                }
                return false;

            case 'after':
                $date = is_string($value) ? date_create($value) : false;
                if (!$date) {
                    return $this->fail($field, 'Enter a valid date.');
                }
                $target = $param === 'today' ? new \DateTimeImmutable('today') : new \DateTimeImmutable((string) $param);
                if ($date->setTime(0, 0) <= $target->setTime(0, 0)) {
                    return $this->fail($field, ucfirst($label) . ' must be after ' . $param . '.');
                }
                return false;

            case 'before_or_equal':
                $date = is_string($value) ? date_create($value) : false;
                if (!$date) {
                    return $this->fail($field, 'Enter a valid date.');
                }
                $target = $param === 'today' ? new \DateTimeImmutable('today') : new \DateTimeImmutable((string) $param);
                if ($date->setTime(0, 0) > $target->setTime(0, 0)) {
                    return $this->fail($field, ucfirst($label) . ' cannot be later than ' . $param . '.');
                }
                return false;

            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array((string) $value, $allowed, true)) {
                    return $this->fail($field, 'Select a valid ' . strtolower($label) . '.');
                }
                return false;

            case 'same':
                if (($this->data[$param] ?? null) !== $value) {
                    return $this->fail($field, ucfirst($label) . ' does not match.');
                }
                return false;

            case 'regex':
                if (!is_string($value) || !preg_match($param ?? '', $value)) {
                    return $this->fail($field, 'The ' . strtolower($label) . ' format is invalid.');
                }
                return false;

            case 'password':
                if (!is_string($value)
                    || mb_strlen($value) < 8
                    || !preg_match('/[A-Za-z]/', $value)
                    || !preg_match('/\d/', $value)) {
                    return $this->fail($field, 'Password must be at least 8 characters and include a letter and a number.');
                }
                return false;
        }

        return false;
    }

    private function fail(string $field, string $message): bool
    {
        $this->errors[$field] = $message;
        return true;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
