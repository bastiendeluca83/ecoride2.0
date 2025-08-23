<?php
namespace App\Models;


use PDO;


class Ride extends BaseModels
{
public static function create(int $driverId,int $vehicleId,string $fromCity,string $toCity,string $dateStart,string $dateEnd,int $price,int $seats,string $status='PREVU'): int {
$pdo = self::pdo();
$sql = "INSERT INTO rides(driver_id,vehicle_id,from_city,to_city,date_start,date_end,price,seats_left,is_electric_cached,status,created_at)
SELECT :d,:v,:fc,:tc,:ds,:de,:pr,:sl,(SELECT (energie='electrique') FROM vehicles WHERE id=:v),:st,NOW()";
$pdo->prepare($sql)->execute([':d'=>$driverId,':v'=>$vehicleId,':fc'=>$fromCity,':tc'=>$toCity,':ds'=>$dateStart,':de'=>$dateEnd,':pr'=>$price,':sl'=>$seats,':st'=>$status]);
return (int)$pdo->lastInsertId();
}


public static function findById(int $id): ?array {
$sql = "SELECT r.*, u.pseudo, u.photo, v.marque, v.modele, v.energie FROM rides r JOIN users u ON u.id=r.driver_id LEFT JOIN vehicles v ON v.id=r.vehicle_id WHERE r.id=:id";
return self::one($sql, [':id'=>$id]);
}


public static function search(string $fromCity,string $toCity,string $date,bool $ecoOnly=false,?int $priceMax=null,?int $durationMaxMin=null,?int $noteMin=null): array {
$sql = "SELECT r.*, u.pseudo, u.photo, v.energie,
(SELECT AVG(a.note) FROM avis a WHERE a.chauffeur_id=r.driver_id AND a.status='VALIDE') AS note_moyenne
FROM rides r JOIN users u ON u.id=r.driver_id LEFT JOIN vehicles v ON v.id=r.vehicle_id
WHERE r.from_city=:fc AND r.to_city=:tc AND DATE(r.date_start)=:d AND r.seats_left>0";
$p=[':fc'=>$fromCity, ':tc'=>$toCity, ':d'=>$date];
if ($ecoOnly) { $sql .= " AND r.is_electric_cached=1"; }
if ($priceMax!==null) { $sql .= " AND r.price <= :pm"; $p[':pm']=$priceMax; }
if ($durationMaxMin!==null) { $sql .= " AND TIMESTAMPDIFF(MINUTE,r.date_start,r.date_end) <= :dm"; $p[':dm']=$durationMaxMin; }
if ($noteMin!==null) { $sql .= " HAVING note_moyenne >= :nm"; $p[':nm']=$noteMin; }
$sql .= " ORDER BY r.date_start ASC";
$st = self::pdo()->prepare($sql); $st->execute($p);
return $st->fetchAll(PDO::FETCH_ASSOC);
}


public static function listByDriver(int $driverId): array { return self::all("SELECT * FROM rides WHERE driver_id=:d ORDER BY date_start DESC", [':d'=>$driverId]); }


public static function setStatus(int $rideId,string $status): bool { return self::pdo()->prepare("UPDATE rides SET status=:s WHERE id=:id")->execute([':s'=>$status, ':id'=>$rideId]); }


public static function decrementSeats(int $rideId,int $n=1): bool { return self::pdo()->prepare("UPDATE rides SET seats_left=seats_left-:n WHERE id=:id AND seats_left>=:n")->execute([':n'=>$n, ':id'=>$rideId]); }


public static function incrementSeats(int $rideId,int $n=1): bool { return self::pdo()->prepare("UPDATE rides SET seats_left=seats_left+:n WHERE id=:id")->execute([':n'=>$n, ':id'=>$rideId]); }
}