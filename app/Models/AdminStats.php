<?php
// ============================================================
// FILE: app/models/AdminStats.php
// (Statistiques pour l'admin)
// ============================================================
declare(strict_types=1);

namespace App\Models;

class AdminStats extends BaseModels
{
    /**
     * Nombre de covoiturages par jour (dans l'intervalle [fromDate, toDate]).
     * Renvoie des lignes { jour: YYYY-MM-DD, nb: int }.
     */
    public static function ridesPerDay(string $fromDate, string $toDate): array
    {
        $sql = "SELECT DATE(date_start) AS jour, COUNT(*) AS nb
                FROM rides
                WHERE DATE(date_start) BETWEEN :a AND :b
                GROUP BY DATE(date_start)
                ORDER BY jour";
        return self::all($sql, [':a' => $fromDate, ':b' => $toDate]);
    }

    /**
     * Crédits plateforme / jour.
     * 1) Si la table 'transactions' est présente: on somme les lignes dont la description
     *    contient 'plate-forme/plateforme' (insensible à la casse).
     * 2) Fallback: 2 crédits par réservation confirmée (sur bookings).
     *
     * Renvoie des lignes { jour: YYYY-MM-DD, credits: int }.
     */
    public static function platformCreditsPerDay(
        string $fromDate,
        string $toDate,
        int $platformFee = 2
    ): array {
        // 1) via transactions
        try {
            $sql = "
                SELECT DATE(created_at) AS jour, COALESCE(SUM(montant),0) AS credits
                FROM transactions
                WHERE (LOWER(description) LIKE '%plate-forme%'
                       OR LOWER(description) LIKE '%plateforme%')
                  AND DATE(created_at) BETWEEN :a AND :b
                GROUP BY DATE(created_at)
                ORDER BY jour ASC
            ";
            $rows = self::all($sql, [':a' => $fromDate, ':b' => $toDate]);
            if ($rows) return $rows;
        } catch (\Throwable $e) {
            // ignore -> fallback
        }

        // 2) fallback via bookings confirmées
        $sql = "
            SELECT DATE(b.created_at) AS jour, (COUNT(b.id) * :fee) AS credits
            FROM bookings b
            WHERE DATE(b.created_at) BETWEEN :a AND :b
              AND UPPER(b.status)='CONFIRMED'
            GROUP BY DATE(b.created_at)
            ORDER BY jour ASC
        ";
        return self::all($sql, [':a' => $fromDate, ':b' => $toDate, ':fee' => $platformFee]);
    }

    /**
     * Total crédits gagnés par la plateforme.
     * - Chemin principal: somme des transactions de commission (description contient 'plate-forme/plateforme')
     * - Fallback: nb de réservations confirmées * 2
     */
    public static function totalCreditsEarned(int $platformFee = 2): int
    {
        // 1) transactions
        try {
            $row = self::one("
                SELECT COALESCE(SUM(montant),0) AS total
                FROM transactions
                WHERE LOWER(description) LIKE '%plate-forme%'
                   OR LOWER(description) LIKE '%plateforme%'
            ");
            if ($row && isset($row['total'])) return (int)$row['total'];
        } catch (\Throwable $e) {}

        // 2) fallback
        $row = self::one("
            SELECT COUNT(*) * :fee AS total
            FROM bookings b
            WHERE UPPER(b.status)='CONFIRMED'
        ", [':fee' => $platformFee]);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Historique détaillé: crédits / jour + liste des ride_id du jour.
     * - Chemin principal: transactions (commission plate-forme)
     * - Fallback: bookings confirmées (2 crédits par réservation)
     *
     * Renvoie des lignes { jour: YYYY-MM-DD, credits: int, ride_ids: '7,12,15' }.
     */
    public static function platformCreditsHistoryDetailed(
        string $fromDate,
        string $toDate,
        int $platformFee = 2
    ): array {
        // 1) transactions
        try {
            $sql = "
                SELECT
                    DATE(created_at) AS jour,
                    COALESCE(SUM(montant),0) AS credits,
                    GROUP_CONCAT(DISTINCT ride_id ORDER BY ride_id SEPARATOR ',') AS ride_ids
                FROM transactions
                WHERE (LOWER(description) LIKE '%plate-forme%'
                       OR LOWER(description) LIKE '%plateforme%')
                  AND DATE(created_at) BETWEEN :a AND :b
                GROUP BY DATE(created_at)
                ORDER BY jour ASC
            ";
            $rows = self::all($sql, [':a' => $fromDate, ':b' => $toDate]);
            if ($rows) return $rows;
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) fallback via bookings confirmées
        $sql = "
            SELECT
                DATE(b.created_at) AS jour,
                (COUNT(b.id) * :fee) AS credits,
                GROUP_CONCAT(DISTINCT b.ride_id ORDER BY b.ride_id SEPARATOR ',') AS ride_ids
            FROM bookings b
            WHERE DATE(b.created_at) BETWEEN :a AND :b
              AND UPPER(b.status)='CONFIRMED'
            GROUP BY DATE(b.created_at)
            ORDER BY jour ASC
        ";
        return self::all($sql, [':a' => $fromDate, ':b' => $toDate, ':fee' => $platformFee]);
    }
}
