<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Aplica o schema do banco.
 *
 * O arquivo schema.sql usa CREATE ... IF NOT EXISTS, portanto rodar de novo
 * e seguro e idempotente.
 */
final class Migrator
{
    public static function run(): void
    {
        $file = BASE_PATH . '/database/schema.sql';

        if (!is_file($file)) {
            throw new RuntimeException('Arquivo database/schema.sql nao encontrado.');
        }

        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new RuntimeException('Nao foi possivel ler database/schema.sql.');
        }

        Database::connection()->exec($sql);
    }

    /** Cria o schema na primeira execucao, sem exigir comando manual. */
    public static function ensure(): void
    {
        if (!self::isInstalled()) {
            self::run();
        }
    }

    public static function isInstalled(): bool
    {
        $table = Database::scalar(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'",
        );

        return $table !== null;
    }

    /** @return array<string, int> Contagem de registros por tabela. */
    public static function stats(): array
    {
        $stats = [];

        foreach (['users', 'categories', 'expenses', 'budgets'] as $table) {
            $stats[$table] = (int) Database::scalar("SELECT COUNT(*) FROM {$table}");
        }

        return $stats;
    }
}
