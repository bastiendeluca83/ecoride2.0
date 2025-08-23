<?php
namespace App\Models;

use PDO;

class User extends BaseModels
{
    /** Crée un utilisateur et retourne son id */
    public static function create(
        string $pseudo,
        string $email,
        string $plainPassword,
        string $role = 'USER',
        int $credits = 20
    ): int {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
        $sql  = "INSERT INTO users(pseudo,email,password_hash,role,credits,created_at)
                 VALUES(:p,:e,:h,:r,:c,NOW())";
        $pdo = self::pdo();
        $pdo->prepare($sql)->execute([
            ':p' => $pseudo,
            ':e' => $email,
            ':h' => $hash,
            ':r' => $role,
            ':c' => $credits
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** Trouve un user par email */
    public static function findByEmail(string $email): ?array
    {
        return self::one("SELECT * FROM users WHERE email=:e LIMIT 1", [':e' => $email]);
    }

    /** Trouve un user par id (avec moyenne des avis) */
    public static function findById(int $id): ?array
    {
        $sql = "SELECT u.*,
                       (SELECT AVG(a.note) FROM avis a
                        WHERE a.chauffeur_id = u.id AND a.status='VALIDE') AS note_moyenne
                FROM users u
                WHERE u.id = :id";
        return self::one($sql, [':id' => $id]);
    }

    /** Vérifie le couple email/mot de passe */
    public static function verifyPassword(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) return null;
        return $user;
    }

    /** Mise à jour profil (pseudo, photo, credits, role) */
    public static function updateProfile(int $id, array $fields): bool
    {
        $allowed = ['pseudo', 'photo', 'credits', 'role'];
        $set = [];
        $p = [':id' => $id];

        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $set[] = "$k = :$k";
                $p[":$k"] = $v;
            }
        }
        if (!$set) return false;

        $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
        return self::pdo()->prepare($sql)->execute($p);
    }

    /** Change le mot de passe */
    public static function updatePassword(int $id, string $plain): bool
    {
        $h = password_hash($plain, PASSWORD_BCRYPT);
        return self::pdo()->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
                          ->execute([':h' => $h, ':id' => $id]);
    }

    /** Change le rôle */
    public static function setRole(int $id, string $role): bool
    {
        return self::pdo()->prepare("UPDATE users SET role = :r WHERE id = :id")
                          ->execute([':r' => $role, ':id' => $id]);
    }

    /** Ajoute/retire des crédits (delta peut être négatif) */
    public static function adjustCredits(int $id, int $delta): bool
    {
        return self::pdo()->prepare("UPDATE users SET credits = credits + :d WHERE id = :id")
                          ->execute([':d' => $delta, ':id' => $id]);
    }

    /** Idempotent pour tes tests: crée si non existant (par email) */
    public static function firstOrCreateByEmail(
        string $pseudo,
        string $email,
        string $plain,
        string $role = 'USER',
        int $credits = 20
    ): int {
        $row = self::findByEmail($email);
        if ($row) return (int)$row['id'];
        return self::create($pseudo, $email, $plain, $role, $credits);
    }

    /** Liste tous les users (option: par rôle) */
    public static function listAll(?string $role = null): array
    {
        if ($role) {
            return self::all("SELECT * FROM users WHERE role = :r ORDER BY created_at DESC", [':r' => $role]);
        }
        return self::all("SELECT * FROM users ORDER BY created_at DESC");
    }

    /** Supprime un user */
    public static function delete(int $id): bool
    {
        return self::pdo()->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
    }
}
