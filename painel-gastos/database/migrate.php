<?php

declare(strict_types=1);

/**
 * Cria ou atualiza o schema do banco.
 *
 *   php database/migrate.php
 *   php database/migrate.php --fresh   (apaga o arquivo e recria do zero)
 *
 * A aplicacao tambem cria o schema sozinha na primeira requisicao; este
 * script existe para uso em scripts de deploy e para o --fresh.
 */

use App\Core\Config;
use App\Core\Migrator;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script roda apenas na linha de comando.\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';

$fresh = in_array('--fresh', $argv, true);
$path  = (string) Config::get('database.path');

if ($fresh) {
    // O modo WAL cria arquivos auxiliares que tambem precisam sair.
    foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
            echo "Removido: {$file}\n";
        }
    }
}

Migrator::run();

echo "Schema aplicado em: {$path}\n\n";
echo "Registros por tabela:\n";

foreach (Migrator::stats() as $table => $count) {
    printf("  %-12s %d\n", $table, $count);
}

echo "\nPara popular com dados de demonstracao: php database/seed.php\n";
