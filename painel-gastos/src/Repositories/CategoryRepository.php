<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Config;
use App\Core\Database;

final class CategoryRepository
{
    /**
     * Todas as categorias do usuario, em ordem alfabetica.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForUser(int $userId): array
    {
        return Database::select(
            'SELECT id, name, color, created_at
               FROM categories
              WHERE user_id = ?
              ORDER BY name COLLATE NOCASE ASC',
            [$userId],
        );
    }

    /**
     * Mapa id => nome, util para montar selects e validar importacoes.
     *
     * @return array<int, string>
     */
    public function namesById(int $userId): array
    {
        $map = [];

        foreach ($this->allForUser($userId) as $category) {
            $map[(int) $category['id']] = (string) $category['name'];
        }

        return $map;
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        return Database::selectOne(
            'SELECT id, name, color, created_at FROM categories WHERE user_id = ? AND id = ?',
            [$userId, $id],
        );
    }

    /** @return array<string, mixed>|null */
    public function findByName(int $userId, string $name): ?array
    {
        return Database::selectOne(
            'SELECT id, name, color FROM categories WHERE user_id = ? AND lower(name) = lower(?)',
            [$userId, trim($name)],
        );
    }

    public function nameExists(int $userId, string $name, int $ignoreId = 0): bool
    {
        $count = Database::scalar(
            'SELECT COUNT(*) FROM categories
              WHERE user_id = ? AND lower(name) = lower(?) AND id <> ?',
            [$userId, trim($name), $ignoreId],
        );

        return (int) $count > 0;
    }

    public function create(int $userId, string $name, string $color): int
    {
        return Database::insert(
            'INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)',
            [$userId, trim($name), $color],
        );
    }

    public function update(int $userId, int $id, string $name, string $color): void
    {
        Database::execute(
            'UPDATE categories SET name = ?, color = ? WHERE user_id = ? AND id = ?',
            [trim($name), $color, $userId, $id],
        );
    }

    public function delete(int $userId, int $id): void
    {
        Database::execute(
            'DELETE FROM categories WHERE user_id = ? AND id = ?',
            [$userId, $id],
        );
    }

    /** Quantidade de despesas vinculadas (bloqueia exclusao quando > 0). */
    public function expenseCount(int $userId, int $id): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM expenses WHERE user_id = ? AND category_id = ?',
            [$userId, $id],
        );
    }

    /**
     * Busca a categoria pelo nome ou cria uma nova com cor da paleta.
     * Usado pela importacao de CSV.
     */
    public function findOrCreate(int $userId, string $name): int
    {
        $existing = $this->findByName($userId, $name);

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->create($userId, $name, $this->nextColor($userId));
    }

    /** Proxima cor da paleta ainda nao usada pelo usuario. */
    public function nextColor(int $userId): string
    {
        /** @var array<int, string> $palette */
        $palette = (array) Config::get('palette', ['#2563eb']);

        $used = array_column($this->allForUser($userId), 'color');

        foreach ($palette as $color) {
            if (!in_array($color, $used, true)) {
                return (string) $color;
            }
        }

        return (string) $palette[count($used) % count($palette)];
    }

    /**
     * Categorias com o total gasto no intervalo, da maior para a menor.
     *
     * LEFT JOIN garante que categorias sem lancamento aparecam com zero, o
     * que mantem a lista de orcamentos completa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function withTotals(int $userId, string $from, string $to): array
    {
        return Database::select(
            'SELECT c.id,
                    c.name,
                    c.color,
                    COALESCE(SUM(e.amount_cents), 0) AS total_cents,
                    COUNT(e.id)                      AS expense_count
               FROM categories c
               LEFT JOIN expenses e
                      ON e.category_id = c.id
                     AND e.spent_at >= ?
                     AND e.spent_at <= ?
              WHERE c.user_id = ?
              GROUP BY c.id, c.name, c.color
              ORDER BY total_cents DESC, c.name COLLATE NOCASE ASC',
            [$from, $to, $userId],
        );
    }

    public function count(int $userId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM categories WHERE user_id = ?',
            [$userId],
        );
    }
}
