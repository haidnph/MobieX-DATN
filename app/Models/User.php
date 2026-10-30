<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class User
{
    public static function create(string $name, string $email, string $password): int
    {
        $db = Database::connection();
        $statement = $db->prepare(
            "INSERT INTO users (name, email, password_hash, role)
             VALUES (:name, :email, :password_hash, 'customer')"
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function isAdmin(int $id): bool
    {
        $user = self::findById($id);
        return $user !== null && ($user['role'] ?? '') === 'admin';
    }

    public static function saveRememberToken(int $userId, string $selector, string $tokenHash, string $expiresAt): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at)
             VALUES (:user_id, :selector, :token_hash, :expires_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'selector' => $selector,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public static function findRememberToken(string $selector): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT user_id, token_hash FROM user_remember_tokens
             WHERE selector = :selector AND expires_at > NOW() LIMIT 1'
        );
        $statement->execute(['selector' => $selector]);
        $token = $statement->fetch(PDO::FETCH_ASSOC);
        return $token ?: null;
    }

    public static function deleteRememberToken(string $selector): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM user_remember_tokens WHERE selector = :selector'
        );
        $statement->execute(['selector' => $selector]);
    }

    public static function createResetToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $delete = $db->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
            $delete->execute(['user_id' => $userId]);

            $insert = $db->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, :expires_at)'
            );
            $insert->execute([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function resetPassword(string $tokenHash, string $password): bool
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $query = $db->prepare(
                'SELECT user_id FROM password_resets
                 WHERE token_hash = :token_hash AND expires_at > NOW() LIMIT 1'
            );
            $query->execute(['token_hash' => $tokenHash]);
            $reset = $query->fetch(PDO::FETCH_ASSOC);
            if (!$reset) {
                $db->commit();
                return false;
            }

            $update = $db->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $update->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $reset['user_id'],
            ]);

            $delete = $db->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
            $delete->execute(['user_id' => $reset['user_id']]);
            $db->commit();
            return true;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
