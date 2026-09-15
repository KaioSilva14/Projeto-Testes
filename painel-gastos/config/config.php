<?php

declare(strict_types=1);

/**
 * Configuracao central da aplicacao.
 *
 * Todos os valores podem ser sobrescritos por variaveis de ambiente,
 * o que permite usar o mesmo codigo em desenvolvimento e producao.
 */
return [
    'app' => [
        'name'     => 'Painel de Gastos',
        'env'      => getenv('APP_ENV') !== false ? (string) getenv('APP_ENV') : 'local',
        'debug'    => (getenv('APP_DEBUG') !== false ? (string) getenv('APP_DEBUG') : '1') === '1',
        'timezone' => 'America/Sao_Paulo',
        'currency' => 'R$',
    ],

    'database' => [
        // SQLite mantem o projeto sem dependencias externas de servidor.
        'path' => dirname(__DIR__) . '/storage/painel.sqlite',
    ],

    'session' => [
        'name'     => 'painel_gastos_sid',
        'lifetime' => 60 * 60 * 8,
    ],

    'pagination' => [
        'per_page' => 15,
    ],

    // Formas de pagamento aceitas em despesas e na importacao de CSV.
    'payment_methods' => [
        'pix'        => 'Pix',
        'debito'     => 'Cartao de debito',
        'credito'    => 'Cartao de credito',
        'dinheiro'   => 'Dinheiro',
        'boleto'     => 'Boleto',
        'transferencia' => 'Transferencia',
    ],

    // Paleta usada ao criar categorias novas (importacao e seed).
    'palette' => [
        '#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed',
        '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5',
    ],
];
