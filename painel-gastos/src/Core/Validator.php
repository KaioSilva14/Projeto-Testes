<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Validacao encadeavel com mensagens em portugues.
 *
 * Cada campo guarda apenas o primeiro erro encontrado, que e o que a view
 * exibe abaixo do input.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    /** @param array<string, mixed> $data */
    public static function make(array $data): self
    {
        return new self($data);
    }

    private function value(string $field): string
    {
        $value = $this->data[$field] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function add(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;

        return $this;
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->add($field, "{$label} e obrigatorio.");
        }

        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->add($field, "{$label} deve ter no maximo {$max} caracteres.");
        }

        return $this;
    }

    public function minLength(string $field, int $min, string $label): self
    {
        $value = $this->value($field);

        if ($value !== '' && mb_strlen($value) < $min) {
            $this->add($field, "{$label} deve ter no minimo {$min} caracteres.");
        }

        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = $this->value($field);

        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->add($field, "Informe um {$label} valido.");
        }

        return $this;
    }

    public function matches(string $field, string $other, string $label): self
    {
        if ($this->value($field) !== $this->value($other)) {
            $this->add($field, "{$label} nao confere.");
        }

        return $this;
    }

    /** Data no formato Y-m-d, rejeitando dias inexistentes (31/02). */
    public function date(string $field, string $label): self
    {
        $value = $this->value($field);

        if ($value === '') {
            return $this;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            $this->add($field, "{$label} deve ser uma data valida.");
        }

        return $this;
    }

    /** Valor monetario positivo. */
    public function money(string $field, string $label): self
    {
        $value = $this->value($field);

        if ($value === '') {
            return $this;
        }

        $cents = Money::toCents($value);

        if ($cents === null) {
            $this->add($field, "{$label} deve ser um numero.");

            return $this;
        }

        if ($cents <= 0) {
            $this->add($field, "{$label} deve ser maior que zero.");
        }

        return $this;
    }

    /** Valor monetario que aceita zero (usado em orcamentos). */
    public function moneyOrZero(string $field, string $label): self
    {
        $value = $this->value($field);

        if ($value !== '' && Money::toCents($value) === null) {
            $this->add($field, "{$label} deve ser um numero.");
        }

        return $this;
    }

    /** @param array<int, string> $allowed */
    public function in(string $field, array $allowed, string $label): self
    {
        $value = $this->value($field);

        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->add($field, "{$label} selecionado nao e valido.");
        }

        return $this;
    }

    /** Cor hexadecimal no formato #rrggbb. */
    public function hexColor(string $field, string $label): self
    {
        $value = $this->value($field);

        if ($value !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
            $this->add($field, "{$label} deve ser uma cor hexadecimal (#rrggbb).");
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
