<?php

declare(strict_types=1);

/**
 * Popula o banco com dados de demonstracao.
 *
 *   php database/seed.php                 12 meses de historico
 *   php database/seed.php --meses=24      historico mais longo
 *   php database/seed.php --reset         apaga os dados do demo e refaz
 *
 * Credenciais criadas: demo@painel.local / senha123
 */

use App\Core\Migrator;
use App\Core\Money;
use App\Services\DemoSeeder;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script roda apenas na linha de comando.\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';

Migrator::ensure();

$reset  = in_array('--reset', $argv, true);
$months = 12;

foreach ($argv as $argument) {
    if (preg_match('/^--meses=(\d+)$/', (string) $argument, $matches) === 1) {
        $months = max(1, min(60, (int) $matches[1]));
    }
}

echo "Gerando {$months} mes(es) de dados de demonstracao...\n";

$start  = microtime(true);
$result = (new DemoSeeder())->run($months, $reset);
$took   = round((microtime(true) - $start) * 1000);

echo "\n";
echo $result['created']
    ? "Usuario criado: " . DemoSeeder::EMAIL . "\n"
    : "Usuario existente reaproveitado: " . DemoSeeder::EMAIL . "\n";

printf("Senha:      %s\n", DemoSeeder::PASSWORD);
printf("Categorias: %d\n", $result['categories']);
printf("Despesas:   %d\n", $result['expenses']);
printf("Orcamentos: %d\n", $result['budgets']);
printf("Tempo:      %d ms\n", $took);

if ($result['expenses'] === 0 && !$reset) {
    echo "\nO usuario demo ja tinha lancamentos. Use --reset para recriar.\n";
}

echo "\nTotais gerais no banco:\n";

foreach (Migrator::stats() as $table => $count) {
    printf("  %-12s %d\n", $table, $count);
}

echo "\nSuba o servidor com: php -S localhost:8000 -t public\n";
echo "Valor de exemplo formatado: " . Money::format(123456) . "\n";
