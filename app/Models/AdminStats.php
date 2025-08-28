<?php
// ============================================================
// FILE: app/models/AdminStats.php
// (Statistiques pour l'admin)
// ============================================================
namespace App\Models;

use PDO;

class AdminStats extends BaseModels
{
    /** Nombre de covoiturages par jour (SQL) */
    public static function ridesPerDay(string $fromDate, string $toDate): array
    {
        $sql = "SELECT DATE(date_start) AS jour, COUNT(*) AS nb
                FROM rides
                WHERE DATE(date_start) BETWEEN :a AND :b
                GROUP BY DATE(date_start)
                ORDER BY jour";
        return self::all($sql, [':a'=>$fromDate, ':b'=>$toDate]);
    }

    /**
     * Crédits plateforme / jour via **bookings** confirmées.
     * On compte 2 crédits par réservation confirmée.
     * (Compat majuscules/minuscules sur status).
     */
    public static function platformCreditsPerDay(
        string $fromDate,
        string $toDate,
        int $platformFee = 2
    ): array {
        $sql = "
            SELECT
                DATE(b.created_at) AS jour,
                (COUNT(b.id) * :fee) AS credits
            FROM bookings b
            WHERE DATE(b.created_at) BETWEEN :a AND :b
              AND (
                    b.status IN ('CONFIRMED','PAID','APPROVED')
                 OR b.status IN ('confirmed','paid','approved')
              )
            GROUP BY DATE(b.created_at)
            ORDER BY jour ASC
        ";
        return self::all($sql, [':a'=>$fromDate, ':b'=>$toDate, ':fee'=>$platformFee]);
    }

    /** Total crédits gagnés (2 par réservation confirmée) */
    public static function totalCreditsEarned(int $platformFee = 2): int
    {
        $row = self::one("
            SELECT COUNT(*) * :fee AS total
            FROM bookings b
            WHERE (
                    b.status IN ('CONFIRMED','PAID','APPROVED')
                 OR b.status IN ('confirmed','paid','approved')
            )
        ", [':fee'=>$platformFee]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Historique détaillé: crédits / jour + liste des ride_id du jour.
     * Source: bookings (création/confirmation d'une résa) -> 2 crédits/booking.
     * La date d'agrégat = DATE(bookings.created_at)
     */
    public static function platformCreditsHistoryDetailed(
        string $fromDate,
        string $toDate,
        int $platformFee = 2
    ): array {
        $sql = "
            SELECT
                DATE(b.created_at) AS jour,
                (COUNT(b.id) * :fee) AS credits,
                GROUP_CONCAT(DISTINCT b.ride_id ORDER BY b.ride_id SEPARATOR ',') AS ride_ids
            FROM bookings b
            WHERE DATE(b.created_at) BETWEEN :a AND :b
              AND (
                    b.status IN ('CONFIRMED','PAID','APPROVED')
                 OR b.status IN ('confirmed','paid','approved')
              )
            GROUP BY DATE(b.created_at)
            ORDER BY jour ASC
        ";
        return self::all($sql, [':a'=>$fromDate, ':b'=>$toDate, ':fee'=>$platformFee]);
    }
}
