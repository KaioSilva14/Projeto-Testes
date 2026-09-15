<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Camada fina sobre PDO/SQLite com conexao unica reaproveitada na requisicao.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private static string $path = '';

    public static function configure(string $path): void
    {
        self::$path = $path;
        self::$pdo  = null;
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (self::$path === '') {
            throw new RuntimeException('Caminho do banco de dados nao configurado.');
        }

        $directory = dirname(self::$path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Nao foi possivel criar o diretorio: {$directory}");
        }

        try {
            self::$pdo = new PDO('sqlite:' . self::$path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Falha ao conectar no banco: ' . $e->getMessage(), 0, $e);
        }

        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec('PRAGMA journal_mode = WAL');

        return self::$pdo;
    }

    /**
     * @param  array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function select(string $sql, array $params = []): array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @param  array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Retorna a primeira coluna da primeira linha (COUNT, SUM, MAX...).
     *
     * @param array<string|int, mixed> $params
     */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int, mixed> $params */
    public static function execute(string $sql, array $params = []): int
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /** @param array<string|int, mixed> $params */
    public static function insert(string $sql, array $params = []): int
    {
        self::execute($sql, $params);

        return (int) self::connection()->lastInsertId();
    }

    /**
     * Executa o callback dentro de uma transacao, revertendo em caso de erro.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();

        // SQLite nao suporta transacoes aninhadas; reaproveita a ativa.
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }
}
