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

    public static function create(
        int $driverId,int $vehicleId,string $fromCity,string $toCity,
        string $dateStart,string $dateEnd,int $price,int $seats,string $status='PREVU'
    ): int {
        $pdo = self::pdo();
        $sql = "INSERT INTO rides(driver_id,vehicle_id,from_city,to_city,date_start,date_end,price,seats_left,is_electric_cached,created_at)
                SELECT :d,:v,:fc,:tc,:ds,:de,:pr,:sl,(SELECT (energy='ELECTRIC') FROM vehicles WHERE id=:v),NOW()";
        $pdo->prepare($sql)->execute([
            ':d'=>$driverId,':v'=>$vehicleId,':fc'=>$fromCity,':tc'=>$toCity,
            ':ds'=>$dateStart,':de'=>$dateEnd,':pr'=>$price,':sl'=>$seats
        ]);
        return (int)$pdo->lastInsertId();
    }

    /* >>> AJOUT : méthode helper compatible avec le contrôleur qui passe un payload array */
    public static function createForDriver(int $driverId, int $vehicleId, array $payload) {
        $from = trim((string)($payload['from_city']  ?? ''));
        $to   = trim((string)($payload['to_city']    ?? ''));
        $ds   = trim((string)($payload['date_start'] ?? ''));
        $de   = trim((string)($payload['date_end']   ?? ''));
        $price= (int)($payload['price'] ?? 0);
        $seats= (int)($payload['seats'] ?? 0);

        if ($from==='' || $to==='' || $ds==='' || $de==='' || $seats<=0) return false;

        $id = self::create($driverId, $vehicleId, $from, $to, $ds, $de, $price, $seats);
        return $id > 0 ? $id : false;
    }
    /* ^^^ FIN AJOUT ^^^ */

    public static function findById(int $id): ?array {
        $sql = "SELECT r.*, u.email AS driver_email, v.brand, v.model, v.energy, v.seats
                FROM rides r
                JOIN users u ON u.id=r.driver_id
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.id=:id";
        return self::one($sql, [':id'=>$id]);
    }

    /** Recherche simple calée sur l’index (from_city,to_city,date_start) */
    public static function search(
        string $fromCity,string $toCity,string $date,
        bool $ecoOnly=false,?int $priceMax=null,?int $durationMaxMin=null
    ): array {
        $sql = "SELECT r.*, v.energy
                FROM rides r
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.from_city=:fc AND r.to_city=:tc AND DATE(r.date_start)=:d AND r.seats_left>0";
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

    public static function setStatus(int $rideId,string $status): bool {
        return self::pdo()->prepare("UPDATE rides SET status=:s WHERE id=:id")->execute([':s'=>$status, ':id'=>$rideId]);
    }

    public static function decrementSeats(int $rideId,int $n=1): bool {
        return self::pdo()->prepare("UPDATE rides SET seats_left=seats_left-:n WHERE id=:id AND seats_left>=:n")->execute([':n'=>$n, ':id'=>$rideId]);
    }

    public static function incrementSeats(int $rideId,int $n=1): bool {
        return self::pdo()->prepare("UPDATE rides SET seats_left=seats_left+:n WHERE id=:id")->execute([':n'=>$n, ':id'=>$rideId]);
    }

    public static function forDriverUpcoming(int $driverId): array {
        $sql = "SELECT r.*, v.brand, v.model, v.energy, v.seats
                FROM rides r
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.driver_id=:d AND r.date_start>=NOW()
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
}
