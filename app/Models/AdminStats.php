<?php
// ============================================================
// FILE: app/models/AdminStats.php
// (Statistiques pour l'admin: SQL + optionnel Mongo)
// ============================================================
namespace App\Models;


use PDO;


class AdminStats extends BaseModels
{
/** Nombre de covoiturages par jour (SQL) */
public static function ridesPerDay(string $fromDate, string $toDate): array
{
$sql = "SELECT DATE(date_start) AS jour, COUNT(*) AS nb
FROM rides WHERE DATE(date_start) BETWEEN :a AND :b GROUP BY DATE(date_start) ORDER BY jour";
return self::all($sql, [':a'=>$fromDate, ':b'=>$toDate]);
}


/** Crédits gagnés par la plateforme par jour (SQL) via réservations confirmées */
public static function platformCreditsPerDay(string $fromDate, string $toDate, int $platformFee=2): array
{
$sql = "SELECT DATE(r.date_start) AS jour, (COUNT(res.id)*:fee) AS credits
FROM rides r
JOIN reservations res ON res.ride_id=r.id AND res.confirmed=1
WHERE DATE(r.date_start) BETWEEN :a AND :b
GROUP BY DATE(r.date_start) ORDER BY jour";
return self::all($sql, [':a'=>$fromDate, ':b'=>$toDate, ':fee'=>$platformFee]);
}


/** Total crédits gagnés (SQL) */
public static function totalCreditsEarned(int $platformFee=2): int
{
$row = self::one("SELECT COUNT(*)* :fee AS total FROM reservations WHERE confirmed=1", [':fee'=>$platformFee]);
return (int)($row['total'] ?? 0);
}
}
