<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\Sql; // même helper que tes autres modèles
use PDO;

final class Vehicle
{
    private static function pdo(): PDO { return Sql::pdo(); }

    public static function forUser(int $userId): array
    {
        $st = self::pdo()->prepare(
          "SELECT id, user_id, brand, model, color, energy, plate, first_reg_date, seats
             FROM vehicles WHERE user_id=:uid ORDER BY id DESC"
        );
        $st->execute([':uid'=>$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findOwned(int $id, int $userId): ?array
    {
        $st = self::pdo()->prepare("SELECT * FROM vehicles WHERE id=:id AND user_id=:uid");
        $st->execute([':id'=>$id, ':uid'=>$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(int $userId, array $d): bool
    {
        $st = self::pdo()->prepare(
          "INSERT INTO vehicles (user_id, brand, model, color, energy, plate, first_reg_date, seats)
           VALUES (:uid, :brand, :model, :color, :energy, :plate, :first_reg_date, :seats)"
        );
        return $st->execute([
            ':uid'=>$userId,
            ':brand'=>$d['brand'] ?? null, ':model'=>$d['model'] ?? null, ':color'=>$d['color'] ?? null,
            ':energy'=>$d['energy'] ?? null, ':plate'=>$d['plate'] ?? null,
            ':first_reg_date'=>($d['first_reg_date'] ?: null),
            ':seats'=>(int)($d['seats'] ?? 0),
        ]);
    }

    public static function update(int $id, int $userId, array $d): bool
    {
        $st = self::pdo()->prepare(
          "UPDATE vehicles
              SET brand=:brand, model=:model, color=:color, energy=:energy, plate=:plate,
                  first_reg_date=:first_reg_date, seats=:seats
            WHERE id=:id AND user_id=:uid"
        );
        $st->execute([
            ':brand'=>$d['brand'] ?? null, ':model'=>$d['model'] ?? null, ':color'=>$d['color'] ?? null,
            ':energy'=>$d['energy'] ?? null, ':plate'=>$d['plate'] ?? null,
            ':first_reg_date'=>($d['first_reg_date'] ?: null),
            ':seats'=>(int)($d['seats'] ?? 0),
            ':id'=>$id, ':uid'=>$userId,
        ]);
        return $st->rowCount()>0;
    }

    public static function delete(int $id, int $userId): bool
    {
        $st = self::pdo()->prepare("DELETE FROM vehicles WHERE id=:id AND user_id=:uid");
        $st->execute([':id'=>$id, ':uid'=>$userId]);
        return $st->rowCount()>0;
    }
}
