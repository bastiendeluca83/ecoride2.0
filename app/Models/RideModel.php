<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

class RideModel
{
    public static function create(array $r)
    {
        $sql = 'INSERT INTO rides (
                    driver_id, vehicle_id, from_city, to_city,
                    date_start, date_end, price, seats_left, status, is_electric_cached, created_at
                ) VALUES (
                    :driver, :vehicle, :fromc, :toc,
                    :ds, :de, :price, :seats, "PREVU",
                    (SELECT (energy="ELECTRIC") FROM vehicles WHERE id=:vehicle), NOW()
                )';
        $ok = Sql::pdo()->prepare($sql)->execute([
            ':driver'  => $r['driver_id'],
            ':vehicle' => $r['vehicle_id'],
            ':fromc'   => $r['from_city'],
            ':toc'     => $r['to_city'],
            ':ds'      => $r['date_start'],
            ':de'      => $r['date_end'] ?? null,
            ':price'   => (int)$r['price'],
            ':seats'   => (int)$r['seats_left'],
        ]);
        return $ok ? (int)Sql::pdo()->lastInsertId() : false;
    }

    public static function upcomingByDriver(int $driverId): array
    {
        $q = Sql::pdo()->prepare('SELECT * FROM rides WHERE driver_id=:d AND date_start >= NOW() ORDER BY date_start ASC');
        $q->execute([':d'=>$driverId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function historyByDriver(int $driverId): array
    {
        $q = Sql::pdo()->prepare('SELECT * FROM rides WHERE driver_id=:d AND date_start < NOW() ORDER BY date_start DESC');
        $q->execute([':d'=>$driverId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function isOwnedBy(int $rideId, int $userId): bool
    {
        $q = Sql::pdo()->prepare('SELECT COUNT(*) FROM rides WHERE id=:id AND driver_id=:u');
        $q->execute([':id'=>$rideId, ':u'=>$userId]);
        return (bool)$q->fetchColumn();
    }
}
