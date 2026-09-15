<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Geracao de URLs cientes do subdiretorio em que a app esta publicada.
 */
final class Url
{
    private static string $base = '';

    public static function setBase(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    public static function base(): string
    {
        return self::$base;
    }

    /** Url::to('/despesas', ['mes' => '2026-09']) */
    public static function to(string $path = '/', array $query = []): string
    {
        $path = '/' . ltrim($path, '/');
        $url  = self::$base . ($path === '/' ? '/' : rtrim($path, '/'));

        if ($query !== []) {
            $filtered = array_filter(
                $query,
                static fn (mixed $value): bool => $value !== null && $value !== '',
            );

            if ($filtered !== []) {
                $url .= '?' . http_build_query($filtered);
            }
        }

        return $url === '' ? '/' : $url;
    }

    /** Caminho de um asset estatico dentro de public/. */
    public static function asset(string $path): string
    {
        return self::$base . '/assets/' . ltrim($path, '/');
    }
}
