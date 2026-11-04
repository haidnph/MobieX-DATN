<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class User
{
    public static function create(string $name, string $email, string $password): int
    {
        $db = Database::connection();
        $statement = $db->prepare(
            "INSERT INTO users (name, email, password, is_active)
             VALUES (:name, :email, :password_hash, 1)"
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $id=(int)$db->lastInsertId();
        $role=$db->prepare("SELECT id FROM roles WHERE slug='customer' LIMIT 1"); $role->execute(); $roleId=$role->fetchColumn();
        if($roleId!==false){$assign=$db->prepare('INSERT IGNORE INTO role_user (user_id,role_id) VALUES (?,?)');$assign->execute([$id,$roleId]);}
        return $id;
    }

    public static function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT u.id,u.name,u.email,u.password AS password_hash,
                    COALESCE((SELECT r.slug FROM role_user ru JOIN roles r ON r.id=ru.role_id WHERE ru.user_id=u.id ORDER BY (r.slug='admin') DESC LIMIT 1),'customer') AS role
             FROM users u WHERE u.email=:email AND u.is_active=1 LIMIT 1"
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT u.id,u.name,u.email,
                    COALESCE((SELECT r.slug FROM role_user ru JOIN roles r ON r.id=ru.role_id WHERE ru.user_id=u.id ORDER BY (r.slug='admin') DESC LIMIT 1),'customer') AS role
             FROM users u WHERE u.id=:id AND u.is_active=1 LIMIT 1"
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

    public static function issuePasswordReset(string $email): ?string
    {
        $user=self::findByEmail($email); if(!$user)return null;
        $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token); $db=Database::connection();
        $db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$user['id']]);
        $q=$db->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))');$q->execute([$user['id'],$hash]);
        return $token;
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $db=Database::connection();$db->beginTransaction();
        try{$q=$db->prepare('SELECT user_id FROM password_resets WHERE token_hash=? AND expires_at>NOW() FOR UPDATE');$q->execute([hash('sha256',$token)]);$userId=$q->fetchColumn();if(!$userId){$db->rollBack();return false;}
        $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$userId]);$db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$userId]);$db->commit();return true;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function saveRememberToken(int $userId, string $selector, string $tokenHash, string $expiresAt): void
    {
        Database::connection()->prepare('UPDATE users SET remember_token=? WHERE id=?')->execute([$selector.':'.$tokenHash,$userId]);
    }

    public static function findRememberToken(string $selector): ?array
    {
        $statement=Database::connection()->prepare('SELECT id AS user_id,remember_token FROM users WHERE remember_token LIKE ? LIMIT 1');
        $statement->execute([$selector.':%']);$row=$statement->fetch(PDO::FETCH_ASSOC);if(!$row)return null;
        [, $hash]=explode(':',(string)$row['remember_token'],2);return ['user_id'=>$row['user_id'],'token_hash'=>$hash];
    }

    public static function deleteRememberToken(string $selector): void
    {
        Database::connection()->prepare('UPDATE users SET remember_token=NULL WHERE remember_token LIKE ?')->execute([$selector.':%']);
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


}
