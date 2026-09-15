<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Acesso a configuracao usando notacao de ponto: Config::get('app.name').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $file): void
    {
        $items = require $file;

        self::$items = is_array($items) ? $items : [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$items;
    }
}
