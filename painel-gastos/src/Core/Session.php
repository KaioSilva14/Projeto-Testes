<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envolve $_SESSION com mensagens flash e regeneracao de id no login.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) Config::get('session.name', 'painel_sid'));

        session_set_cookie_params([
            'lifetime' => (int) Config::get('session.lifetime', 28800),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_destroy();
    }

    /** Guarda uma mensagem para exibir na proxima requisicao. */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Le e limpa as mensagens flash pendentes.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public static function pullFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($messages) ? $messages : [];
    }

    /**
     * Mantem os dados enviados para repopular o formulario apos um erro.
     *
     * @param array<string, mixed> $input
     */
    public static function flashInput(array $input, array $errors = []): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token']);

        $_SESSION['_old']    = $input;
        $_SESSION['_errors'] = $errors;
    }

    /** @return array<string, mixed> */
    public static function pullOld(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        return is_array($old) ? $old : [];
    }

    /** @return array<string, string> */
    public static function pullErrors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);

        return is_array($errors) ? $errors : [];
    }
}
