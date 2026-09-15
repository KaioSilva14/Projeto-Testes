<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Period;

final class BudgetRepository
{
    /**
     * Orcamentos da competencia com o valor ja gasto em cada categoria.
     *
     * Traz todas as categorias (LEFT JOIN em budgets) para que a tela de
     * orcamento funcione como um formulario unico e completo.
     *
     * @return array<int, array{
     *     category_id: int,
     *     name: string,
     *     color: string,
     *     budget_cents: int,
     *     spent_cents: int,
     *     remaining_cents: int,
     *     usage_percent: float|null
     * }>
     */
    public function forPeriod(int $userId, Period $period): array
    {
        $rows = Database::select(
            'SELECT c.id   AS category_id,
                    c.name,
                    c.color,
                    COALESCE(b.amount_cents, 0) AS budget_cents,
                    COALESCE((
                        SELECT SUM(e.amount_cents)
                          FROM expenses e
                         WHERE e.category_id = c.id
                           AND e.user_id = c.user_id
                           AND e.spent_at >= ?
                           AND e.spent_at <= ?
                    ), 0) AS spent_cents
               FROM categories c
               LEFT JOIN budgets b
                      ON b.category_id = c.id
                     AND b.user_id = c.user_id
                     AND b.period = ?
              WHERE c.user_id = ?
              ORDER BY budget_cents DESC, c.name COLLATE NOCASE ASC',
            [$period->firstDay(), $period->lastDay(), $period->key(), $userId],
        );

        return array_map(
            static function (array $row): array {
                $budget = (int) $row['budget_cents'];
                $spent  = (int) $row['spent_cents'];

                return [
                    'category_id'     => (int) $row['category_id'],
                    'name'            => (string) $row['name'],
                    'color'           => (string) $row['color'],
                    'budget_cents'    => $budget,
                    'spent_cents'     => $spent,
                    'remaining_cents' => $budget - $spent,
                    // null quando nao ha orcamento definido: a view mostra "-".
                    'usage_percent'   => $budget > 0 ? round($spent / $budget * 100, 1) : null,
                ];
            },
            $rows,
        );
    }

    /**
     * Apenas as categorias com orcamento definido, para os cartoes de alerta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definedForPeriod(int $userId, Period $period): array
    {
        return array_values(array_filter(
            $this->forPeriod($userId, $period),
            static fn (array $row): bool => $row['budget_cents'] > 0,
        ));
    }

    /**
     * Cria ou atualiza o orcamento da categoria na competencia.
     *
     * Valor zero remove o registro, mantendo a tabela sem linhas inuteis.
     */
    public function save(int $userId, int $categoryId, Period $period, int $amountCents): void
    {
        if ($amountCents <= 0) {
            $this->delete($userId, $categoryId, $period);

            return;
        }

        // UPSERT apoiado no indice unico (user_id, category_id, period).
        Database::execute(
            "INSERT INTO budgets (user_id, category_id, period, amount_cents)
             VALUES (?, ?, ?, ?)
             ON CONFLICT (user_id, category_id, period)
             DO UPDATE SET amount_cents = excluded.amount_cents,
                           updated_at   = datetime('now', 'localtime')",
            [$userId, $categoryId, $period->key(), $amountCents],
        );
    }

    public function delete(int $userId, int $categoryId, Period $period): void
    {
        Database::execute(
            'DELETE FROM budgets WHERE user_id = ? AND category_id = ? AND period = ?',
            [$userId, $categoryId, $period->key()],
        );
    }

    /** Soma dos orcamentos definidos na competencia. */
    public function totalForPeriod(int $userId, Period $period): int
    {
        return (int) Database::scalar(
            'SELECT COALESCE(SUM(amount_cents), 0)
               FROM budgets
              WHERE user_id = ? AND period = ?',
            [$userId, $period->key()],
        );
    }

    /**
     * Copia os orcamentos de uma competencia para outra.
     *
     * Atalho para nao redigitar tudo a cada mes; sobrescreve o destino.
     */
    public function copyPeriod(int $userId, Period $from, Period $to): int
    {
        return Database::execute(
            "INSERT INTO budgets (user_id, category_id, period, amount_cents)
             SELECT user_id, category_id, ?, amount_cents
               FROM budgets
              WHERE user_id = ? AND period = ?
             ON CONFLICT (user_id, category_id, period)
             DO UPDATE SET amount_cents = excluded.amount_cents,
                           updated_at   = datetime('now', 'localtime')",
            [$to->key(), $userId, $from->key()],
        );
    }

    public function count(int $userId, Period $period): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM budgets WHERE user_id = ? AND period = ?',
            [$userId, $period->key()],
        );
    }
}
