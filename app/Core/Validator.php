<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Server side validation. Rules: required, email, min:n, max:n, between:a,b, in:a,b,c, int, numeric,
 * confirmed, slug, regex:/.../, nullable.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, string|list<string>> $rules */
    public function __construct(private readonly array $data, private readonly array $rules)
    {
    }

    public static function make(array $data, array $rules): self
    {
        $v = new self($data, $rules);
        $v->run();
        return $v;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            $isEmpty = $value === null || $value === '' || $value === [];
            if ($isEmpty && in_array('nullable', $rules, true)) {
                continue;
            }
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($name, $arg, $value, $isEmpty, $field);
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
        }
    }

    private function check(string $name, ?string $arg, mixed $value, bool $isEmpty, string $field): ?string
    {
        $str = is_scalar($value) ? (string) $value : '';
        return match ($name) {
            'nullable' => null,
            'required' => $isEmpty || (is_string($value) && trim($value) === '') ? t('validation.required') : null,
            'email' => !$isEmpty && (filter_var($str, FILTER_VALIDATE_EMAIL) === false || mb_strlen($str) > 190) ? t('validation.email') : null,
            'min' => !$isEmpty && mb_strlen($str) < (int) $arg ? t('validation.min', ['min' => $arg]) : null,
            'max' => !$isEmpty && mb_strlen($str) > (int) $arg ? t('validation.max', ['max' => $arg]) : null,
            'int' => !$isEmpty && filter_var($str, FILTER_VALIDATE_INT) === false ? t('validation.int') : null,
            'numeric' => !$isEmpty && !is_numeric($str) ? t('validation.numeric') : null,
            'between' => !$isEmpty && (!is_numeric($str) || (float) $str < (float) explode(',', (string) $arg)[0] || (float) $str > (float) explode(',', (string) $arg)[1])
                ? t('validation.between', ['min' => explode(',', (string) $arg)[0], 'max' => explode(',', (string) $arg)[1]]) : null,
            'in' => !$isEmpty && !in_array($str, explode(',', (string) $arg), true) ? t('validation.in') : null,
            'confirmed' => $str !== (string) ($this->data[$field . '_confirmation'] ?? '') ? t('validation.confirmed') : null,
            'slug' => !$isEmpty && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $str) ? t('validation.slug') : null,
            'regex' => !$isEmpty && !preg_match((string) $arg, $str) ? t('validation.format') : null,
            'password' => !$isEmpty && (!preg_match('/[A-Za-z]/', $str) || !preg_match('/\d/', $str)) ? t('validation.password') : null,
            default => throw new \InvalidArgumentException('Unknown rule ' . $name),
        };
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
