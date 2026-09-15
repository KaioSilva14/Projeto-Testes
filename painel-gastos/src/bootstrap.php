<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

define('BASE_PATH', dirname(__DIR__));

/**
 * Autoloader PSR-4 minimo: App\Core\Database -> src/Core/Database.php
 * Evita a necessidade de Composer para rodar o projeto.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = BASE_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Config::load(BASE_PATH . '/config/config.php');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

if (Config::get('app.debug') === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

Database::configure((string) Config::get('database.path'));
