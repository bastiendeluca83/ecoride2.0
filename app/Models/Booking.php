<?php
namespace App\Models;


use PDO;


class Booking extends BaseModels
{
public static function create(int $rideId,int $passengerId,int $creditsUsed,string $status='RESERVE'): int {
$pdo = self::pdo();
$sql = "INSERT INTO reservations(ride_id,passager_id,credits_utilises,confirmed,status,created_at) VALUES(:r,:u,:c,0,:s,NOW())";
$pdo->prepare($sql)->execute([':r'=>$rideId, ':u'=>$passengerId, ':c'=>$creditsUsed, ':s'=>$status]);
return (int)$pdo->lastInsertId();
}


public static function confirm(int $id): bool { return self::pdo()->prepare("UPDATE reservations SET confirmed=1 WHERE id=:id")->execute([':id'=>$id]); }


public static function setStatus(int $id,string $status): bool { return self::pdo()->prepare("UPDATE reservations SET status=:s WHERE id=:id")->execute([':s'=>$status, ':id'=>$id]); }


public static function findById(int $id): ?array { return self::one("SELECT * FROM reservations WHERE id=:id", [':id'=>$id]); }


public static function findByRideAndUser(int $rideId,int $userId): ?array { return self::one("SELECT * FROM reservations WHERE ride_id=:r AND passager_id=:u LIMIT 1", [':r'=>$rideId, ':u'=>$userId]); }


public static function listByPassenger(int $userId): array {
$sql = "SELECT res.*, r.from_city, r.to_city, r.date_start, r.date_end, r.status AS ride_status FROM reservations res JOIN rides r ON r.id=res.ride_id WHERE res.passager_id=:u ORDER BY res.created_at DESC";
return self::all($sql, [':u'=>$userId]);
}


public static function delete(int $id): bool { return self::pdo()->prepare("DELETE FROM reservations WHERE id=:id")->execute([':id'=>$id]); }
}
