<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

class RideModel
{
    public static function create(array $r)
    {
        $sql = 'INSERT INTO rides (driver_id, vehicle_id, from_city, to_city, start_at, price, seats, status)
                VALUES (:driver, :vehicle, :fromc, :toc, :start, :price, :seats, "scheduled")';
        $ok = Sql::pdo()->prepare($sql)->execute([
            ':driver'=>$r['driver_id'],
            ':vehicle'=>$r['vehicle_id'],
            ':fromc'=>$r['from_city'],
            ':toc'=>$r['to_city'],
            ':start'=>$r['start_at'],
            ':price'=>$r['price'],
            ':seats'=>$r['seats'],
        ]);
        return $ok ? (int)Sql::pdo()->lastInsertId() : false;
    }

    public static function upcomingByDriver(int $driverId): array
    {
        $q = Sql::pdo()->prepare('SELECT * FROM rides WHERE driver_id=:d AND status IN ("scheduled","started") ORDER BY start_at ASC');
        $q->execute([':d'=>$driverId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function historyByDriver(int $driverId): array
    {
        $q = Sql::pdo()->prepare('SELECT * FROM rides WHERE driver_id=:d AND status IN ("ended","canceled") ORDER BY start_at DESC');
        $q->execute([':d'=>$driverId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function isOwnedBy(int $rideId, int $userId): bool
    {
        $q = Sql::pdo()->prepare('SELECT COUNT(*) FROM rides WHERE id=:id AND driver_id=:u');
        $q->execute([':id'=>$rideId, ':u'=>$userId]);
        return (bool)$q->fetchColumn();
    }

    public static function markStarted(int $rideId): bool
    {
        return Sql::pdo()->prepare('UPDATE rides SET status="started" WHERE id=:id')->execute([':id'=>$rideId]);
    }

    public static function markEnded(int $rideId): bool
    {
        return Sql::pdo()->prepare('UPDATE rides SET status="ended" WHERE id=:id')->execute([':id'=>$rideId]);
    }

    public static function cancel(int $rideId): bool
    {
        // TODO: ici tu pourras gérer remboursements et notifications
        return Sql::pdo()->prepare('UPDATE rides SET status="canceled" WHERE id=:id')->execute([':id'=>$rideId]);
    }
}
