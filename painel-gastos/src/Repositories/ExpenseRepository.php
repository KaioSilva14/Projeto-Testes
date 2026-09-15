<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Period;
use App\Support\ExpenseFilter;

/**
 * Consultas e agregacoes de despesas.
 *
 * Todo metodo recebe o $userId e o aplica no WHERE: o isolamento entre contas
 * e garantido na camada de dados, nao apenas nos controllers.
 */
final class ExpenseRepository
{
    private const SELECT_COLUMNS = 'e.id,
            e.description,
            e.amount_cents,
            e.spent_at,
            e.payment_method,
            e.notes,
            e.category_id,
            c.name  AS category_name,
            c.color AS category_color';

    /**
     * Pagina a listagem aplicando os filtros informados.
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     total_cents: int,
     *     page: int,
     *     per_page: int,
     *     last_page: int
     * }
     */
    public function paginate(int $userId, ExpenseFilter $filter): array
    {
        ['sql' => $where, 'params' => $params] = $filter->conditions($userId);

        $total = (int) Database::scalar(
            "SELECT COUNT(*)
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE {$where}",
            $params,
        );

        $totalCents = (int) Database::scalar(
            "SELECT COALESCE(SUM(e.amount_cents), 0)
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE {$where}",
            $params,
        );

        $lastPage = max(1, (int) ceil($total / $filter->perPage));
        $page     = min($filter->page, $lastPage);
        $offset   = ($page - 1) * $filter->perPage;

        // LIMIT/OFFSET sao interpolados por serem inteiros ja convertidos em
        // PHP: o driver SQLite trataria o valor vinculado como texto.
        $items = Database::select(
            'SELECT ' . self::SELECT_COLUMNS . "
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE {$where}
              ORDER BY {$filter->orderBy()}
              LIMIT {$filter->perPage} OFFSET {$offset}",
            $params,
        );

        return [
            'items'       => $items,
            'total'       => $total,
            'total_cents' => $totalCents,
            'page'        => $page,
            'per_page'    => $filter->perPage,
            'last_page'   => $lastPage,
        ];
    }

    /**
     * Todas as despesas do filtro, sem paginacao (exportacao CSV).
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForFilter(int $userId, ExpenseFilter $filter): array
    {
        ['sql' => $where, 'params' => $params] = $filter->conditions($userId);

        return Database::select(
            'SELECT ' . self::SELECT_COLUMNS . "
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE {$where}
              ORDER BY " . $filter->orderBy(),
            $params,
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        return Database::selectOne(
            'SELECT ' . self::SELECT_COLUMNS . '
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE e.user_id = ? AND e.id = ?',
            [$userId, $id],
        );
    }

    /** @param array{category_id: int, description: string, amount_cents: int, spent_at: string, payment_method: string, notes: string|null} $data */
    public function create(int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO expenses
                 (user_id, category_id, description, amount_cents, spent_at, payment_method, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $data['category_id'],
                $data['description'],
                $data['amount_cents'],
                $data['spent_at'],
                $data['payment_method'],
                $data['notes'],
            ],
        );
    }

    /** @param array{category_id: int, description: string, amount_cents: int, spent_at: string, payment_method: string, notes: string|null} $data */
    public function update(int $userId, int $id, array $data): void
    {
        Database::execute(
            'UPDATE expenses
                SET category_id = ?, description = ?, amount_cents = ?,
                    spent_at = ?, payment_method = ?, notes = ?
              WHERE user_id = ? AND id = ?',
            [
                $data['category_id'],
                $data['description'],
                $data['amount_cents'],
                $data['spent_at'],
                $data['payment_method'],
                $data['notes'],
                $userId,
                $id,
            ],
        );
    }

    public function delete(int $userId, int $id): int
    {
        return Database::execute(
            'DELETE FROM expenses WHERE user_id = ? AND id = ?',
            [$userId, $id],
        );
    }

    // ------------------------------------------------------------------
    // Agregacoes usadas por dashboard, relatorios e API de graficos
    // ------------------------------------------------------------------

    /**
     * Resumo do intervalo: total, quantidade, ticket medio e maior despesa.
     *
     * @return array{total_cents: int, expense_count: int, average_cents: int, max_cents: int}
     */
    public function summary(int $userId, string $from, string $to): array
    {
        $row = Database::selectOne(
            'SELECT COALESCE(SUM(amount_cents), 0) AS total_cents,
                    COUNT(*)                       AS expense_count,
                    COALESCE(MAX(amount_cents), 0) AS max_cents
               FROM expenses
              WHERE user_id = ? AND spent_at >= ? AND spent_at <= ?',
            [$userId, $from, $to],
        ) ?? [];

        $total = (int) ($row['total_cents'] ?? 0);
        $count = (int) ($row['expense_count'] ?? 0);

        return [
            'total_cents'   => $total,
            'expense_count' => $count,
            'average_cents' => $count > 0 ? (int) round($total / $count) : 0,
            'max_cents'     => (int) ($row['max_cents'] ?? 0),
        ];
    }

    public function totalBetween(int $userId, string $from, string $to): int
    {
        return (int) Database::scalar(
            'SELECT COALESCE(SUM(amount_cents), 0)
               FROM expenses
              WHERE user_id = ? AND spent_at >= ? AND spent_at <= ?',
            [$userId, $from, $to],
        );
    }

    /**
     * Total por categoria no intervalo (dados do grafico de rosca).
     *
     * @return array<int, array{id: int, name: string, color: string, total_cents: int, expense_count: int}>
     */
    public function totalsByCategory(int $userId, string $from, string $to): array
    {
        $rows = Database::select(
            'SELECT c.id, c.name, c.color,
                    SUM(e.amount_cents) AS total_cents,
                    COUNT(e.id)         AS expense_count
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE e.user_id = ? AND e.spent_at >= ? AND e.spent_at <= ?
              GROUP BY c.id, c.name, c.color
              ORDER BY total_cents DESC',
            [$userId, $from, $to],
        );

        return array_map(
            static fn (array $row): array => [
                'id'            => (int) $row['id'],
                'name'          => (string) $row['name'],
                'color'         => (string) $row['color'],
                'total_cents'   => (int) $row['total_cents'],
                'expense_count' => (int) $row['expense_count'],
            ],
            $rows,
        );
    }

    /**
     * Total por dia do mes, com zeros nos dias sem lancamento.
     *
     * O preenchimento e feito em PHP porque SQLite nao tem generate_series.
     *
     * @return array<int, array{day: int, date: string, total_cents: int}>
     */
    public function dailyTotals(int $userId, Period $period): array
    {
        $rows = Database::select(
            'SELECT spent_at, SUM(amount_cents) AS total_cents
               FROM expenses
              WHERE user_id = ? AND spent_at >= ? AND spent_at <= ?
              GROUP BY spent_at',
            [$userId, $period->firstDay(), $period->lastDay()],
        );

        $byDate = [];

        foreach ($rows as $row) {
            $byDate[(string) $row['spent_at']] = (int) $row['total_cents'];
        }

        $series = [];

        for ($day = 1; $day <= $period->daysInMonth(); $day++) {
            $date = $period->key() . '-' . sprintf('%02d', $day);

            $series[] = [
                'day'         => $day,
                'date'        => $date,
                'total_cents' => $byDate[$date] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Total de cada competencia informada, preservando a ordem recebida.
     *
     * @param  array<int, Period> $periods
     * @return array<string, int> chave "YYYY-MM" => centavos
     */
    public function totalsByPeriod(int $userId, array $periods): array
    {
        if ($periods === []) {
            return [];
        }

        $first = $periods[0];
        $last  = $periods[count($periods) - 1];

        $rows = Database::select(
            "SELECT substr(spent_at, 1, 7) AS period, SUM(amount_cents) AS total_cents
               FROM expenses
              WHERE user_id = ? AND spent_at >= ? AND spent_at <= ?
              GROUP BY period",
            [$userId, $first->firstDay(), $last->lastDay()],
        );

        $byPeriod = [];

        foreach ($rows as $row) {
            $byPeriod[(string) $row['period']] = (int) $row['total_cents'];
        }

        $totals = [];

        foreach ($periods as $period) {
            $totals[$period->key()] = $byPeriod[$period->key()] ?? 0;
        }

        return $totals;
    }

    /**
     * Total por forma de pagamento no intervalo.
     *
     * @return array<int, array{payment_method: string, total_cents: int, expense_count: int}>
     */
    public function totalsByPaymentMethod(int $userId, string $from, string $to): array
    {
        $rows = Database::select(
            'SELECT payment_method,
                    SUM(amount_cents) AS total_cents,
                    COUNT(*)          AS expense_count
               FROM expenses
              WHERE user_id = ? AND spent_at >= ? AND spent_at <= ?
              GROUP BY payment_method
              ORDER BY total_cents DESC',
            [$userId, $from, $to],
        );

        return array_map(
            static fn (array $row): array => [
                'payment_method' => (string) $row['payment_method'],
                'total_cents'    => (int) $row['total_cents'],
                'expense_count'  => (int) $row['expense_count'],
            ],
            $rows,
        );
    }

    /**
     * Matriz categoria x competencia, base da tabela comparativa.
     *
     * @param  array<int, Period> $periods
     * @return array<int, array{name: string, color: string, periods: array<string, int>, total_cents: int}>
     */
    public function matrixByCategory(int $userId, array $periods): array
    {
        if ($periods === []) {
            return [];
        }

        $first = $periods[0];
        $last  = $periods[count($periods) - 1];
        $keys  = array_map(static fn (Period $p): string => $p->key(), $periods);

        $rows = Database::select(
            "SELECT c.id, c.name, c.color,
                    substr(e.spent_at, 1, 7) AS period,
                    SUM(e.amount_cents)      AS total_cents
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE e.user_id = ? AND e.spent_at >= ? AND e.spent_at <= ?
              GROUP BY c.id, c.name, c.color, period",
            [$userId, $first->firstDay(), $last->lastDay()],
        );

        $matrix = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            if (!isset($matrix[$id])) {
                $matrix[$id] = [
                    'name'        => (string) $row['name'],
                    'color'       => (string) $row['color'],
                    'periods'     => array_fill_keys($keys, 0),
                    'total_cents' => 0,
                ];
            }

            $period = (string) $row['period'];
            $total  = (int) $row['total_cents'];

            $matrix[$id]['periods'][$period] = $total;
            $matrix[$id]['total_cents']     += $total;
        }

        uasort(
            $matrix,
            static fn (array $a, array $b): int => $b['total_cents'] <=> $a['total_cents'],
        );

        return array_values($matrix);
    }

    /**
     * Maiores despesas do intervalo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topExpenses(int $userId, string $from, string $to, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));

        return Database::select(
            'SELECT ' . self::SELECT_COLUMNS . "
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE e.user_id = ? AND e.spent_at >= ? AND e.spent_at <= ?
              ORDER BY e.amount_cents DESC, e.spent_at DESC
              LIMIT {$limit}",
            [$userId, $from, $to],
        );
    }

    /**
     * Ultimos lancamentos registrados (feed do dashboard).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(100, $limit));

        return Database::select(
            'SELECT ' . self::SELECT_COLUMNS . "
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE e.user_id = ?
              ORDER BY e.spent_at DESC, e.id DESC
              LIMIT {$limit}",
            [$userId],
        );
    }

    /** Competencia mais antiga com lancamentos, ou null se nao houver nenhum. */
    public function firstPeriod(int $userId): ?string
    {
        $value = Database::scalar(
            'SELECT substr(MIN(spent_at), 1, 7) FROM expenses WHERE user_id = ?',
            [$userId],
        );

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function count(int $userId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM expenses WHERE user_id = ?',
            [$userId],
        );
    }
}
