<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validation simple de donnees de formulaire.
 */
class Validator
{
    private array $data;
    private array $errors = [];
    private array $labels;

    public function __construct(array $data, array $labels = [])
    {
        $this->data   = $data;
        $this->labels = $labels;
    }

    public function required(string $field, ?string $message = null): self
    {
        $value = $this->value($field);
        if ($value === null || $value === '' || $value === []) {
            $this->errors[$field] = $message ?? $this->label($field) . ' est obligatoire.';
        }
        return $this;
    }

    public function length(string $field, int $min, ?int $max = null, ?string $message = null): self
    {
        $value = (string) ($this->value($field) ?? '');
        $len   = mb_strlen($value);
        if ($len < $min || ($max !== null && $len > $max)) {
            $this->errors[$field] = $message ?? sprintf(
                '%s doit contenir entre %d et %s caractères.',
                $this->label($field),
                $min,
                $max ?? '∞'
            );
        }
        return $this;
    }

    public function email(string $field, ?string $message = null): self
    {
        $value = (string) ($this->value($field) ?? '');
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "L'adresse email n'est pas valide.";
        }
        return $this;
    }

    public function numeric(string $field, ?string $message = null): self
    {
        $value = $this->value($field);
        if ($value !== null && $value !== '' && !is_numeric(str_replace([' ', ','], ['', '.'], (string) $value))) {
            $this->errors[$field] = $message ?? $this->label($field) . ' doit être numérique.';
        }
        return $this;
    }

    public function date(string $field, ?string $message = null): self
    {
        $value = (string) ($this->value($field) ?? '');
        if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $this->errors[$field] = $message ?? 'La date doit être au format AAAA-MM-JJ.';
        }
        return $this;
    }

    public function in(string $field, array $allowed, ?string $message = null): self
    {
        $value = $this->value($field);
        if ($value !== null && $value !== '' && !in_array((string) $value, array_map('strval', $allowed), true)) {
            $this->errors[$field] = $message ?? $this->label($field) . ' contient une valeur invalide.';
        }
        return $this;
    }

    public function siren(string $field, ?string $message = null): self
    {
        $value = preg_replace('/\s+/', '', (string) ($this->value($field) ?? ''));
        if ($value !== '' && !preg_match('/^\d{9}$/', (string) $value)) {
            $this->errors[$field] = $message ?? 'Le SIREN doit comporter 9 chiffres.';
        }
        return $this;
    }

    public function siret(string $field, ?string $message = null): self
    {
        $value = preg_replace('/\s+/', '', (string) ($this->value($field) ?? ''));
        if ($value !== '' && !preg_match('/^\d{14}$/', (string) $value)) {
            $this->errors[$field] = $message ?? 'Le SIRET doit comporter 14 chiffres.';
        }
        return $this;
    }

    public function custom(string $field, bool $condition, string $message): self
    {
        if (!$condition) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : (string) reset($this->errors);
    }

    private function value(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
