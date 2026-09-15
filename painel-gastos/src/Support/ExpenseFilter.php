<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use App\Core\Period;
use App\Core\Request;

/**
 * Filtros da listagem de despesas, traduzidos em SQL parametrizado.
 *
 * Ordenacao e direcao passam por listas brancas: o valor que vem da query
 * string nunca e concatenado no SQL.
 */
final class ExpenseFilter
{
    /** Rotulo exibido => coluna real usada no ORDER BY. */
    private const SORTABLE = [
        'data'      => 'e.spent_at',
        'valor'     => 'e.amount_cents',
        'descricao' => 'e.description',
        'categoria' => 'c.name',
        'forma'     => 'e.payment_method',
    ];

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $categoryId,
        public readonly ?string $paymentMethod,
        public readonly string $search,
        public readonly string $sort,
        public readonly string $direction,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * Monta o filtro a partir da requisicao.
     *
     * Sem intervalo explicito, usa o mes de referencia (?mes=YYYY-MM) inteiro.
     */
    public static function fromRequest(Request $request): self
    {
        $period = Period::fromString($request->query('mes'));

        $from = self::normalizeDate($request->query('de')) ?? $period->firstDay();
        $to   = self::normalizeDate($request->query('ate')) ?? $period->lastDay();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $categoryId = $request->queryInt('categoria');
        $payment    = $request->query('forma', '');
        $methods    = array_keys((array) Config::get('payment_methods', []));

        $sort = (string) $request->query('ordenar', 'data');
        $sort = array_key_exists($sort, self::SORTABLE) ? $sort : 'data';

        $direction = strtolower((string) $request->query('direcao', 'desc'));
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return new self(
            from:          $from,
            to:            $to,
            categoryId:    $categoryId > 0 ? $categoryId : null,
            paymentMethod: in_array($payment, $methods, true) ? $payment : null,
            search:        (string) $request->query('q', ''),
            sort:          $sort,
            direction:     $direction,
            page:          max(1, $request->queryInt('pagina', 1)),
            perPage:       max(5, min(100, $request->queryInt('por_pagina', (int) Config::get('pagination.per_page', 15)))),
        );
    }

    /** Intervalo de um mes inteiro, sem os demais filtros (usado em relatorios). */
    public static function forPeriod(Period $period): self
    {
        return new self(
            from:          $period->firstDay(),
            to:            $period->lastDay(),
            categoryId:    null,
            paymentMethod: null,
            search:        '',
            sort:          'data',
            direction:     'desc',
            page:          1,
            perPage:       (int) Config::get('pagination.per_page', 15),
        );
    }

    private static function normalizeDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /**
     * Clausula WHERE (sem a palavra WHERE) e seus parametros posicionais.
     *
     * @return array{sql: string, params: array<int, mixed>}
     */
    public function conditions(int $userId): array
    {
        $sql    = ['e.user_id = ?', 'e.spent_at >= ?', 'e.spent_at <= ?'];
        $params = [$userId, $this->from, $this->to];

        if ($this->categoryId !== null) {
            $sql[]    = 'e.category_id = ?';
            $params[] = $this->categoryId;
        }

        if ($this->paymentMethod !== null) {
            $sql[]    = 'e.payment_method = ?';
            $params[] = $this->paymentMethod;
        }

        if ($this->search !== '') {
            $sql[]    = "(e.description LIKE ? ESCAPE '!' OR e.notes LIKE ? ESCAPE '!')";
            $term     = '%' . self::escapeLike($this->search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        return ['sql' => implode(' AND ', $sql), 'params' => $params];
    }

    /**
     * Neutraliza os curingas do LIKE digitados pelo usuario.
     *
     * Usa "!" como caractere de escape (e nao a barra invertida) para manter
     * o SQL legivel e evitar camadas de escape em PHP.
     */
    private static function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /** ORDER BY seguro, com id como desempate estavel para a paginacao. */
    public function orderBy(): string
    {
        $column    = self::SORTABLE[$this->sort];
        $direction = strtoupper($this->direction);

        return "{$column} {$direction}, e.id {$direction}";
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** Indica se algum filtro alem do intervalo de datas esta ativo. */
    public function isFiltered(): bool
    {
        return $this->categoryId !== null
            || $this->paymentMethod !== null
            || $this->search !== '';
    }

    /**
     * Filtros como query string, para preservar o estado em links.
     *
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        $query = [
            'de'  => $this->from,
            'ate' => $this->to,
        ];

        if ($this->categoryId !== null) {
            $query['categoria'] = $this->categoryId;
        }

        if ($this->paymentMethod !== null) {
            $query['forma'] = $this->paymentMethod;
        }

        if ($this->search !== '') {
            $query['q'] = $this->search;
        }

        if ($this->sort !== 'data' || $this->direction !== 'desc') {
            $query['ordenar'] = $this->sort;
            $query['direcao'] = $this->direction;
        }

        return $query;
    }

    /** Direcao a usar ao clicar no cabecalho de uma coluna. */
    public function toggleDirection(string $column): string
    {
        if ($this->sort !== $column) {
            return $column === 'descricao' || $column === 'categoria' ? 'asc' : 'desc';
        }

        return $this->direction === 'asc' ? 'desc' : 'asc';
    }

    /** @return array<int, string> */
    public static function sortableColumns(): array
    {
        return array_keys(self::SORTABLE);
    }
}
