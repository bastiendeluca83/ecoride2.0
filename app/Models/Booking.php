<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

class Booking
{
    /* Helpers */
    private static function pdo(): \PDO { return Sql::pdo(); }
    private static function one(string $sql, array $p=[]): ?array {
        $st=self::pdo()->prepare($sql); $st->execute($p);
        $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null;
    }
    private static function all(string $sql, array $p=[]): array {
        $st=self::pdo()->prepare($sql); $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    public static function create(int $rideId,int $passengerId,int $creditsUsed,string $status='CONFIRMED'): int {
        $pdo = self::pdo();
        $sql = "INSERT INTO bookings(ride_id,passenger_id,status,credits_spent,created_at)
                VALUES(:r,:u,:s,:c,NOW())";
        $pdo->prepare($sql)->execute([':r'=>$rideId, ':u'=>$passengerId, ':c'=>$creditsUsed, ':s'=>$status]);
        return (int)$pdo->lastInsertId();
    }

    public static function confirm(int $id): bool {
        return self::pdo()->prepare("UPDATE bookings SET status='CONFIRMED' WHERE id=:id")->execute([':id'=>$id]);
    }

    public static function setStatus(int $id,string $status): bool {
        return self::pdo()->prepare("UPDATE bookings SET status=:s WHERE id=:id")->execute([':s'=>$status, ':id'=>$id]);
    }

    public static function findById(int $id): ?array { return self::one("SELECT * FROM bookings WHERE id=:id", [':id'=>$id]); }

    public static function findByRideAndUser(int $rideId,int $userId): ?array {
        return self::one("SELECT * FROM bookings WHERE ride_id=:r AND passenger_id=:u LIMIT 1", [':r'=>$rideId, ':u'=>$userId]);
    }

    /* === Pour dashboard USER === */
    public static function forPassengerUpcoming(int $userId): array {
        $sql = "SELECT b.*, r.from_city, r.to_city, r.date_start, r.date_end, r.price
                FROM bookings b
                JOIN rides r ON r.id=b.ride_id
                WHERE b.passenger_id=:u AND r.date_start>=NOW() AND b.status='CONFIRMED'
                ORDER BY r.date_start ASC";
        return self::all($sql, [':u'=>$userId]);
    }

    public static function forPassengerPast(int $userId): array {
        $sql = "SELECT b.*, r.from_city, r.to_city, r.date_start, r.date_end, r.price
                FROM bookings b
                JOIN rides r ON r.id=b.ride_id
                WHERE b.passenger_id=:u AND r.date_start<NOW()
                ORDER BY r.date_start DESC";
        return self::all($sql, [':u'=>$userId]);
    }

    /* === Pour dashboard EMPLOYEE === */
    public static function cancelledLast(int $limit = 20): array {
        $sql = "SELECT b.*, r.from_city, r.to_city, r.date_start, u.email AS passenger_email
                FROM bookings b
                JOIN rides r ON r.id=b.ride_id
                JOIN users u ON u.id=b.passenger_id
                WHERE b.status = 'CANCELLED'
                ORDER BY b.created_at DESC
                LIMIT :lim";
        $st = self::pdo()->prepare($sql);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function delete(int $id): bool { return self::pdo()->prepare("DELETE FROM bookings WHERE id=:id")->execute([':id'=>$id]); }
}
