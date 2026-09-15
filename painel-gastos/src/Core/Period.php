<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Representa uma competencia mensal no formato "YYYY-MM".
 *
 * Toda a aplicacao gira em torno do mes de referencia (dashboard, orcamentos
 * e relatorios), por isso a manipulacao fica concentrada aqui.
 */
final class Period
{
    private const MONTHS = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    private const SHORT_MONTHS = [
        1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
    ];

    private function __construct(
        public readonly int $year,
        public readonly int $month,
    ) {
    }

    public static function fromString(?string $value): self
    {
        if (is_string($value) && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', trim($value), $matches) === 1) {
            return new self((int) $matches[1], (int) $matches[2]);
        }

        return self::current();
    }

    public static function current(): self
    {
        $now = new DateTimeImmutable('now');

        return new self((int) $now->format('Y'), (int) $now->format('n'));
    }

    public static function isValid(?string $value): bool
    {
        return is_string($value)
            && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($value)) === 1;
    }

    /** Chave usada em consultas e formularios: "2026-09". */
    public function key(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function firstDay(): string
    {
        return $this->key() . '-01';
    }

    public function lastDay(): string
    {
        return $this->key() . '-' . sprintf('%02d', $this->daysInMonth());
    }

    public function daysInMonth(): int
    {
        return (int) (new DateTimeImmutable($this->firstDay()))->format('t');
    }

    /** "Setembro de 2026" */
    public function label(): string
    {
        return self::MONTHS[$this->month] . ' de ' . $this->year;
    }

    /** "Set/26" — usado em eixos de grafico. */
    public function shortLabel(): string
    {
        return self::SHORT_MONTHS[$this->month] . '/' . substr((string) $this->year, 2);
    }

    /** Desloca o periodo em N meses (negativo volta no tempo). */
    public function shift(int $months): self
    {
        $date = (new DateTimeImmutable($this->firstDay()))
            ->modify(sprintf('%+d month', $months));

        return new self((int) $date->format('Y'), (int) $date->format('n'));
    }

    public function previous(): self
    {
        return $this->shift(-1);
    }

    public function next(): self
    {
        return $this->shift(1);
    }

    public function isCurrentMonth(): bool
    {
        return $this->key() === self::current()->key();
    }

    /**
     * Quantos dias do mes ja passaram. Em meses passados equivale ao mes
     * inteiro; no mes corrente, ao dia de hoje. Usado para medias diarias.
     */
    public function elapsedDays(): int
    {
        if (!$this->isCurrentMonth()) {
            return $this->key() > self::current()->key() ? 0 : $this->daysInMonth();
        }

        return (int) (new DateTimeImmutable('now'))->format('j');
    }

    /**
     * Sequencia de periodos terminando neste, do mais antigo ao mais recente.
     *
     * @return array<int, self>
     */
    public function lastMonths(int $count): array
    {
        $count   = max(1, $count);
        $periods = [];

        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $periods[] = $this->shift(-$offset);
        }

        return $periods;
    }

    /**
     * Lista de periodos entre dois pontos (inclusive), do mais antigo ao mais
     * recente. Inverte automaticamente se vierem fora de ordem.
     *
     * @return array<int, self>
     */
    public static function range(self $from, self $to): array
    {
        if ($from->key() > $to->key()) {
            [$from, $to] = [$to, $from];
        }

        $periods = [];
        $cursor  = $from;

        while ($cursor->key() <= $to->key()) {
            $periods[] = $cursor;
            $cursor    = $cursor->next();
        }

        return $periods;
    }

    /** Formata uma data ISO (Y-m-d) como d/m/Y. */
    public static function formatDate(string $isoDate): string
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', substr($isoDate, 0, 10));

        return $date === false ? $isoDate : $date->format('d/m/Y');
    }
}
