<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Token anti-CSRF por sessao, validado em toda requisicao POST.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put(self::KEY, $token);
        }

        return $token;
    }

    public static function check(?string $candidate): bool
    {
        $token = Session::get(self::KEY);

        return is_string($token)
            && $token !== ''
            && is_string($candidate)
            && hash_equals($token, $candidate);
    }

    /** Campo oculto pronto para embutir em formularios. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
