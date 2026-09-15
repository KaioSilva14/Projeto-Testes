<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

/**
 * Autenticacao por sessao com hash Bcrypt/Argon (password_hash).
 */
final class Auth
{
    private const KEY = '_user_id';

    /** @var array<string, mixed>|null */
    private static ?array $cachedUser = null;

    /**
     * Valida as credenciais e inicia a sessao autenticada.
     *
     * Usa password_verify sempre que o usuario existe, e mesmo quando nao
     * existe devolve a mesma mensagem generica, para nao revelar quais
     * e-mails estao cadastrados.
     */
    public static function attempt(string $email, string $password): bool
    {
        $user = (new UserRepository())->findByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        // Reforca o hash se o algoritmo padrao do PHP mudou.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            (new UserRepository())->updatePassword((int) $user['id'], $password);
        }

        self::login((int) $user['id']);

        return true;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::put(self::KEY, $userId);

        self::$cachedUser = null;
    }

    public static function logout(): void
    {
        self::$cachedUser = null;

        Session::destroy();
    }

    public static function check(): bool
    {
        return self::id() > 0;
    }

    public static function id(): int
    {
        return (int) Session::get(self::KEY, 0);
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $id = self::id();

        if ($id <= 0) {
            return null;
        }

        $user = (new UserRepository())->find($id);

        // Sessao aponta para um usuario removido: encerra por seguranca.
        if ($user === null) {
            self::logout();

            return null;
        }

        return self::$cachedUser = $user;
    }

    /** Id do usuario autenticado, ou redireciona para o login. */
    public static function requireId(): int
    {
        $id = self::id();

        if ($id <= 0) {
            Response::redirect('/login');
        }

        return $id;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
