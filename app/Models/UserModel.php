<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

class User
{
    public static function findById(int $id): ?array
    {
        $sql = 'SELECT id,
                       last_name  AS nom,
                       first_name AS prenom,
                       email,
                       phone      AS telephone,
                       address    AS adresse,
                       credits,
                       role
                FROM users WHERE id = :id';
        $st = Sql::pdo()->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Accepte indifféremment clés FR (nom, prenom, telephone, adresse, email)
     * ou EN (last_name, first_name, phone, address, email).
     */
    public static function updateProfile(int $id, array $data): bool
    {
        $map = [
            'nom'        => 'last_name',
            'prenom'     => 'first_name',
            'telephone'  => 'phone',
            'adresse'    => 'address',
            'email'      => 'email',
            // on accepte aussi les noms EN si le formulaire les envoie
            'last_name'  => 'last_name',
            'first_name' => 'first_name',
            'phone'      => 'phone',
            'address'    => 'address',
        ];

        $set = [];
        $p   = [':id' => $id];

        foreach ($data as $k => $v) {
            if (!isset($map[$k])) continue;
            $col = $map[$k];
            $val = is_string($v) ? trim($v) : $v; // petite normalisation
            $set[]        = "$col = :$col";
            $p[":$col"]   = $val;
        }

        if (!$set) return false;
        $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
        return Sql::pdo()->prepare($sql)->execute($p);
    }

    public static function updatePassword(int $id, string $plain): bool
    {
        $hash = password_hash($plain, PASSWORD_DEFAULT); // standard, évolutif
        $st = Sql::pdo()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        return $st->execute([':h' => $hash, ':id' => $id]);
    }
}
