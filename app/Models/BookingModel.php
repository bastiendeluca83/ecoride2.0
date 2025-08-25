<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

class BookingModel
{
    public static function upcomingByPassenger(int $userId): array
    {
        $sql = 'SELECT b.*, r.from_city, r.to_city, r.start_at, r.price, r.status
                FROM bookings b
                JOIN rides r ON r.id = b.ride_id
                WHERE b.passenger_id = :u AND b.status = "confirmed" AND r.status IN ("scheduled","started")
                ORDER BY r.start_at ASC';
        $q = Sql::pdo()->prepare($sql);
        $q->execute([':u'=>$userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function historyByPassenger(int $userId): array
    {
        $sql = 'SELECT b.*, r.from_city, r.to_city, r.start_at, r.price, r.status
                FROM bookings b
                JOIN rides r ON r.id = b.ride_id
                WHERE b.passenger_id = :u AND b.status IN ("completed","canceled")
                ORDER BY r.start_at DESC';
        $q = Sql::pdo()->prepare($sql);
        $q->execute([':u'=>$userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
