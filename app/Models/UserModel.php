<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

class User
{
    /** memo: détecte si la table users utilise les colonnes EN (last_name/first_name/phone/address) */
    private static ?bool $useEnglishCols = null;

    private static function useEnglish(): bool
    {
        if (self::$useEnglishCols !== null) return self::$useEnglishCols;
        $pdo = Sql::pdo();
        $st = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'last_name'");
        $st->execute();
        self::$useEnglishCols = (bool)$st->fetch(PDO::FETCH_ASSOC);
        return self::$useEnglishCols;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Sql::pdo();

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
            $sql = 'SELECT id,
                           nom,
                           prenom,
                           email,
                           telephone,
                           adresse,
                           credits,
                           role
                    FROM users WHERE id = :id';
        }

        try {
            $st = $pdo->prepare($sql);
            $st->execute([':id' => $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('[User::findById] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Accepte clés FR (nom, prenom, telephone, adresse, email)
     * et/ou EN (last_name, first_name, phone, address, email).
     * Mappe vers les colonnes réellement présentes.
     */
    public static function updateProfile(int $id, array $data): bool
    {
        $useEN = self::useEnglish();

        // mapping d’entrée -> colonnes réelles
        $map = $useEN
            ? [
                'nom'        => 'last_name',
                'prenom'     => 'first_name',
                'telephone'  => 'phone',
                'adresse'    => 'address',
                'email'      => 'email',
                'last_name'  => 'last_name',
                'first_name' => 'first_name',
                'phone'      => 'phone',
                'address'    => 'address',
              ]
            : [
                'nom'        => 'nom',
                'prenom'     => 'prenom',
                'telephone'  => 'telephone',
                'adresse'    => 'adresse',
                'email'      => 'email',
                'last_name'  => 'nom',
                'first_name' => 'prenom',
                'phone'      => 'telephone',
                'address'    => 'adresse',
              ];

        $set = []; $p = [':id' => $id];
        foreach ($data as $k => $v) {
            if (!isset($map[$k])) continue;
            $col = $map[$k];
            $val = is_string($v) ? trim($v) : $v;
            $set[]      = "$col = :$col";
            $p[":$col"] = $val;
        }
        if (!$set) return false;

        $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
        try {
            return Sql::pdo()->prepare($sql)->execute($p);
        } catch (\Throwable $e) {
            error_log('[User::updateProfile] ' . $e->getMessage());
            return false;
        }
    }

    public static function updatePassword(int $id, string $plain): bool
    {
        // on tente password_hash; si la colonne FR existe, adapte ici si besoin
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        try {
            return Sql::pdo()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                             ->execute([':h' => $hash, ':id' => $id]);
        } catch (\Throwable $e) {
            error_log('[User::updatePassword] ' . $e->getMessage());
            return false;
        }
    }
}
