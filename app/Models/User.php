<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

class User
{
    /* =========================
       Helpers DB
       ========================= */
    private static function pdo(): \PDO { return Sql::pdo(); }

    private static function one(string $sql, array $p = []): ?array {
        $st = self::pdo()->prepare($sql); $st->execute($p);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function all(string $sql, array $p = []): array {
        $st = self::pdo()->prepare($sql); $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================
       Détection schéma FR/EN
       ========================= */
    private static ?bool $useEnglishCols = null;

    private static function useEnglish(): bool
    {
        if (self::$useEnglishCols !== null) return self::$useEnglishCols;
        $st = self::pdo()->prepare("SHOW COLUMNS FROM users LIKE 'last_name'");
        $st->execute();
        self::$useEnglishCols = (bool)$st->fetch(PDO::FETCH_ASSOC);
        return self::$useEnglishCols;
    }

    /* =========================
       CRUD / Auth
       ========================= */
    public static function create(string $pseudo, string $email, string $plainPassword, string $role = 'USER', int $credits = 20): int
    {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
        $sql  = "INSERT INTO users(pseudo,email,password_hash,role,credits,created_at)
                 VALUES(:p,:e,:h,:r,:c,NOW())";
        $pdo = self::pdo();
        $pdo->prepare($sql)->execute([':p'=>$pseudo, ':e'=>$email, ':h'=>$hash, ':r'=>$role, ':c'=>$credits]);
        return (int)$pdo->lastInsertId();
    }

    public static function findByEmail(string $email): ?array
    {
        return self::one("SELECT * FROM users WHERE email=:e LIMIT 1", [':e'=>$email]);
    }

    public static function verifyPassword(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'] ?? '')) return null;
        return $user;
    }

    public static function listAll(?string $role = null): array
    {
        if ($role) return self::all("SELECT * FROM users WHERE role=:r ORDER BY created_at DESC", [':r'=>$role]);
        return self::all("SELECT * FROM users ORDER BY created_at DESC");
    }

    public static function delete(int $id): bool
    {
        return self::pdo()->prepare("DELETE FROM users WHERE id=:id")->execute([':id'=>$id]);
    }

    public static function setRole(int $id, string $role): bool
    {
        return self::pdo()->prepare("UPDATE users SET role=:r WHERE id=:id")->execute([':r'=>$role, ':id'=>$id]);
    }

    public static function adjustCredits(int $id, int $delta): bool
    {
        return self::pdo()->prepare("UPDATE users SET credits = credits + :d WHERE id=:id")->execute([':d'=>$delta, ':id'=>$id]);
    }

    public static function firstOrCreateByEmail(string $pseudo, string $email, string $plain, string $role='USER', int $credits=20): int
    {
        $row = self::findByEmail($email);
        if ($row) return (int)$row['id'];
        return self::create($pseudo, $email, $plain, $role, $credits);
    }

    public static function emailExists(string $email, int $excludeId = 0): bool
    {
        $st = self::pdo()->prepare('SELECT 1 FROM users WHERE email = :e AND id <> :id LIMIT 1');
        $st->execute([':e'=>$email, ':id'=>$excludeId]);
        return (bool)$st->fetchColumn();
    }

    /* =========================
       Profil (FR/EN mapping)
       ========================= */
    public static function findById(int $id): ?array
    {
        if (self::useEnglish()) {
            $sql = 'SELECT id,
                           last_name  AS nom,
                           first_name AS prenom,
                           email,
                           phone      AS telephone,
                           address    AS adresse,
                           credits,
                           role
                    FROM users WHERE id = :id';
        } else {
            $sql = 'SELECT id, nom, prenom, email, telephone, adresse, credits, role
                    FROM users WHERE id = :id';
        }
        try { return self::one($sql, [':id'=>$id]); }
        catch (\Throwable $e) { error_log('[User::findById] '.$e->getMessage()); return null; }
    }

    /**
     * $data accepte FR (nom, prenom, telephone, adresse, email) et/ou EN (last_name, first_name, phone, address, email)
     */
    public static function updateProfile(int $id, array $data): bool
    {
        $useEN = self::useEnglish();
        $map = $useEN
            ? ['nom'=>'last_name','prenom'=>'first_name','telephone'=>'phone','adresse'=>'address','email'=>'email',
               'last_name'=>'last_name','first_name'=>'first_name','phone'=>'phone','address'=>'address']
            : ['nom'=>'nom','prenom'=>'prenom','telephone'=>'telephone','adresse'=>'adresse','email'=>'email',
               'last_name'=>'nom','first_name'=>'prenom','phone'=>'telephone','address'=>'adresse'];

        $set = []; $p = [':id'=>$id];
        foreach ($data as $k=>$v) {
            if (!isset($map[$k])) continue;
            $col = $map[$k];
            $val = is_string($v) ? trim($v) : $v;
            $set[]      = "$col = :$col";
            $p[":$col"] = $val;
        }
        if (!$set) return false;

        $sql = 'UPDATE users SET '.implode(', ',$set).' WHERE id = :id';
        try { return self::pdo()->prepare($sql)->execute($p); }
        catch (\Throwable $e) { error_log('[User::updateProfile] '.$e->getMessage()); return false; }
    }

    public static function updatePassword(int $id, string $plain): bool
    {
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        try { return self::pdo()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                                 ->execute([':h'=>$hash, ':id'=>$id]); }
        catch (\Throwable $e) { error_log('[User::updatePassword] '.$e->getMessage()); return false; }
    }
}
