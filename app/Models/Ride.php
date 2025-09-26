<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

class Ride
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

    /** Crée un trajet */
    public static function create(
        int $driverId,int $vehicleId,string $fromCity,string $toCity,
        string $dateStart,string $dateEnd,int $price,int $seats,string $status='PREVU'
    ): int {
        $pdo = self::pdo();
        $sql = "INSERT INTO rides(
                    driver_id, vehicle_id, from_city, to_city,
                    date_start, date_end, price, seats_left,
                    is_electric_cached, status, created_at
                )
                SELECT
                    :d, :v, :fc, :tc,
                    :ds, :de, :pr, :sl,
                    (SELECT (energy='ELECTRIC') FROM vehicles WHERE id=:v),
                    :st, NOW()";
        $pdo->prepare($sql)->execute([
            ':d'=>$driverId, ':v'=>$vehicleId, ':fc'=>$fromCity, ':tc'=>$toCity,
            ':ds'=>$dateStart, ':de'=>$dateEnd, ':pr'=>$price, ':sl'=>$seats, ':st'=>$status
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** Helper pour contrôleur (payload array) */
    public static function createForDriver(int $driverId, int $vehicleId, array $payload) {
        $from = trim((string)($payload['from_city']  ?? ''));
        $to   = trim((string)($payload['to_city']    ?? ''));
        $ds   = trim((string)($payload['date_start'] ?? ''));
        $de   = trim((string)($payload['date_end']   ?? ''));
        $price= (int)($payload['price'] ?? 0);
        $seats= (int)($payload['seats'] ?? 0);

        if ($from==='' || $to==='' || $ds==='' || $de==='' || $seats<=0) return false;
        $id = self::create($driverId, $vehicleId, $from, $to, $ds, $de, $price, $seats, 'PREVU');
        return $id > 0 ? $id : false;
    }

    /** Anti-doublon simple */
    public static function ensureRide(
        int $driverId, int $vehicleId, string $fromCity, string $toCity,
        string $dateStart, string $dateEnd, int $price, ?int $seatsLeft = null
    ): ?int {
        $exists = self::one(
            "SELECT id FROM rides
             WHERE driver_id=:d AND vehicle_id=:v AND from_city=:fc AND to_city=:tc AND date_start=:ds
             LIMIT 1",
            [':d'=>$driverId, ':v'=>$vehicleId, ':fc'=>$fromCity, ':tc'=>$toCity, ':ds'=>$dateStart]
        );
        if ($exists) return (int)$exists['id'];

        if ($seatsLeft === null) {
            $row = self::one("SELECT seats FROM vehicles WHERE id=:v", [':v'=>$vehicleId]);
            $seatsLeft = (int)($row['seats'] ?? 0);
            if ($seatsLeft <= 0) $seatsLeft = 1;
        }
        $id = self::create($driverId, $vehicleId, $fromCity, $toCity, $dateStart, $dateEnd, $price, $seatsLeft, 'PREVU');
        return $id > 0 ? $id : null;
    }

    public static function findById(int $id): ?array {
        $sql = "SELECT r.*, u.email AS driver_email, v.brand, v.model, v.energy, v.seats
                FROM rides r
                JOIN users u ON u.id=r.driver_id
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.id=:id";
        return self::one($sql, [':id'=>$id]);
    }

    public static function search(string $fromCity,string $toCity,string $date,
        bool $ecoOnly=false,?int $priceMax=null,?int $durationMaxMin=null
    ): array {
        $sql = "SELECT r.*, v.energy
                FROM rides r
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.from_city=:fc AND r.to_city=:tc AND DATE(r.date_start)=:d
                  AND r.seats_left>0 AND r.status IN ('PREVU','STARTED')";
        $p=[':fc'=>$fromCity, ':tc'=>$toCity, ':d'=>$date];
        if ($ecoOnly) { $sql .= " AND r.is_electric_cached=1"; }
        if ($priceMax!==null) { $sql .= " AND r.price <= :pm"; $p[':pm']=$priceMax; }
        if ($durationMaxMin!==null) { $sql .= " AND TIMESTAMPDIFF(MINUTE,r.date_start,r.date_end) <= :dm"; $p[':dm']=$durationMaxMin; }
        $sql .= " ORDER BY r.date_start ASC";
        return self::all($sql, $p);
    }

    public static function listByDriver(int $driverId): array {
        return self::all("SELECT * FROM rides WHERE driver_id=:d ORDER BY date_start DESC", [':d'=>$driverId]);
    }

    public static function setStatus(int $rideId,string $status, ?int $driverId=null): bool {
        $sql = "UPDATE rides SET status=:s WHERE id=:id";
        $params = [':s'=>$status, ':id'=>$rideId];
        if ($driverId !== null) { $sql .= " AND driver_id=:d"; $params[':d']=$driverId; }
        return self::pdo()->prepare($sql)->execute($params);
    }

    public static function markStarted(int $rideId, int $driverId): bool {
        $sql = "UPDATE rides
                SET status='STARTED', started_at = IFNULL(started_at, NOW())
                WHERE id=:id AND driver_id=:d AND status='PREVU'";
        return self::pdo()->prepare($sql)->execute([':id'=>$rideId, ':d'=>$driverId]);
    }

    public static function markFinished(int $rideId, int $driverId): bool {
        $sql = "UPDATE rides
                SET status='FINISHED', date_end = NOW()
                WHERE id=:id AND driver_id=:d AND status='STARTED'";
        return self::pdo()->prepare($sql)->execute([':id'=>$rideId, ':d'=>$driverId]);
    }

    public static function markCancelled(int $rideId, int $driverId): bool {
        $sql = "UPDATE rides
                SET status='CANCELLED'
                WHERE id=:id AND driver_id=:d AND status IN ('PREVU','STARTED')";
        return self::pdo()->prepare($sql)->execute([':id'=>$rideId, ':d'=>$driverId]);
    }

    public static function decrementSeats(int $rideId,int $n=1): bool {
        return self::pdo()->prepare(
            "UPDATE rides SET seats_left=seats_left-:n WHERE id=:id AND seats_left>=:n"
        )->execute([':n'=>$n, ':id'=>$rideId]);
    }

    public static function incrementSeats(int $rideId,int $n=1): bool {
        return self::pdo()->prepare(
            "UPDATE rides SET seats_left=seats_left+:n WHERE id=:id"
        )->execute([':n'=>$n, ':id'=>$rideId]);
    }

    /** Trajets à venir pour le conducteur (+ statut non annulé) */
    public static function forDriverUpcoming(int $driverId): array {
        $sql = "SELECT r.*, v.brand, v.model, v.energy, v.seats
                FROM rides r
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.driver_id=:d AND r.date_start>=NOW()
                  AND r.status IN ('PREVU','STARTED')
                ORDER BY r.date_start ASC";
        return self::all($sql, [':d'=>$driverId]);
    }

    public static function forDriverPast(int $driverId): array {
        $sql = "SELECT r.*, v.brand, v.model, v.energy, v.seats
                FROM rides r
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.driver_id=:d AND r.date_start<NOW()
                ORDER BY r.date_start DESC";
        return self::all($sql, [':d'=>$driverId]);
    }

    /** Passagers confirmés (nom + avatar) */
    public static function passengersForRide(int $rideId): array {
        $sql = "SELECT u.id,u.email,u.avatar_path,
                       TRIM(CONCAT_WS(' ', NULLIF(u.prenom,''), NULLIF(u.nom,''))) AS display_name
                FROM bookings b
                JOIN users u ON u.id = b.passenger_id
                WHERE b.ride_id = :r AND b.status = 'CONFIRMED'
                ORDER BY b.created_at ASC";
        return self::all($sql, [':r'=>$rideId]);
    }

    /** E-mails des passagers confirmés */
    public static function passengersEmails(int $rideId): array {
        $rows = self::all("SELECT u.email
                           FROM bookings b JOIN users u ON u.id=b.passenger_id
                           WHERE b.ride_id=:r AND b.status='CONFIRMED'", [':r'=>$rideId]);
        return array_values(array_filter(array_map(fn($r)=>$r['email']??null, $rows)));
    }

    public static function driverInfo(int $rideId): ?array {
        $sql = "SELECT u.id,u.avatar_path,
                       TRIM(CONCAT_WS(' ', NULLIF(u.prenom,''), NULLIF(u.nom,''))) AS display_name
                FROM rides r JOIN users u ON u.id=r.driver_id
                WHERE r.id=:r LIMIT 1";
        return self::one($sql, [':r'=>$rideId]);
    }

    public static function countCompletedByDriver(int $userId): int {
        $st = self::pdo()->prepare("SELECT COUNT(*) FROM rides r
                                    WHERE r.driver_id=:u AND r.status='FINISHED'");
        $st->execute([':u'=>$userId]);
        return (int)$st->fetchColumn();
    }
}
