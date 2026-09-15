<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;

final class UserRepository
{
    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::selectOne(
            'SELECT id, name, email, password_hash, created_at FROM users WHERE id = ?',
            [$id],
        );
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return Database::selectOne(
            'SELECT id, name, email, password_hash, created_at FROM users WHERE lower(email) = lower(?)',
            [trim($email)],
        );
    }

    public function emailExists(string $email, int $ignoreId = 0): bool
    {
        $count = Database::scalar(
            'SELECT COUNT(*) FROM users WHERE lower(email) = lower(?) AND id <> ?',
            [trim($email), $ignoreId],
        );

        return (int) $count > 0;
    }

    public function create(string $name, string $email, string $password): int
    {
        return Database::insert(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)',
            [trim($name), trim($email), Auth::hash($password)],
        );
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        Database::execute(
            'UPDATE users SET name = ?, email = ? WHERE id = ?',
            [trim($name), trim($email), $id],
        );
    }

    public function updatePassword(int $id, string $password): void
    {
        Database::execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [Auth::hash($password), $id],
        );
    }

    public function count(): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM users');
    }
}
