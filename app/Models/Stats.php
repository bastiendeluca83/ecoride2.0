<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

final class Stats
{
    private static function pdo(): \PDO { return Sql::pdo(); }

    public static function kpis(): array
    {
        $pdo = self::pdo();

        // === Utilisateurs ===
        $usersTotal = 0;
        try {
            $usersTotal = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        } catch (\Throwable $e) {}

        // === Trajets à venir ===
        $ridesUpcoming = 0;
        try {
            $ridesUpcoming = (int)$pdo->query("
                SELECT COUNT(*) FROM rides WHERE date_start >= NOW()
            ")->fetchColumn();
        } catch (\Throwable $e) {}

        // === Réservations confirmées (total & à venir) ===
        $bookingsTotal = 0;
        $bookingsUpcoming = 0;
        try {
            $bookingsTotal = (int)$pdo->query("
                SELECT COUNT(*) FROM bookings WHERE UPPER(status)='CONFIRMED'
            ")->fetchColumn();
        } catch (\Throwable $e) {}
        try {
            $st = $pdo->query("
                SELECT COUNT(*)
                FROM bookings b
                JOIN rides r ON r.id = b.ride_id
                WHERE UPPER(b.status)='CONFIRMED' AND r.date_start >= NOW()
            ");
            $bookingsUpcoming = (int)($st ? $st->fetchColumn() : 0);
        } catch (\Throwable $e) {}

        // === Places restantes (somme seats_left) : à venir & global ===
        $seatsLeftUpcoming = 0;
        $seatsLeftAll      = 0;
        try {
            $seatsLeftUpcoming = (int)$pdo->query("
                SELECT COALESCE(SUM(GREATEST(seats_left,0)),0)
                FROM rides
                WHERE date_start >= NOW()
            ")->fetchColumn();
        } catch (\Throwable $e) {}
        try {
            $seatsLeftAll = (int)$pdo->query("
                SELECT COALESCE(SUM(GREATEST(seats_left,0)),0)
                FROM rides
            ")->fetchColumn();
        } catch (\Throwable $e) {}
        

        // === Crédits plateforme total (transactions libellées commission) + fallback bookings*2 ===
        $platformCreditsTotal = 0;
        try {
            $q = $pdo->query("
                SELECT COALESCE(SUM(montant),0)
                FROM transactions
                WHERE LOWER(description) LIKE '%plate-forme%'
                   OR LOWER(description) LIKE '%plateforme%'
            ");
            if ($q !== false) {
                $platformCreditsTotal = (int)$q->fetchColumn();
            }
        } catch (\Throwable $e) {}
        if ($platformCreditsTotal === 0) {
            // fallback = 2 crédits par réservation confirmée (total)
            $platformCreditsTotal = $bookingsTotal * 2;
        }

        // === Super-set de clés (FR/EN + snake/camel + *_total / *_upcoming) ===
        return [
            // Utilisateurs
            'users'                 => $usersTotal,
            'active_users'          => $usersTotal,
            'users_active'          => $usersTotal,
            'usersTotal'            => $usersTotal,
            'utilisateurs_actifs'   => $usersTotal,

            // Trajets à venir
            'rides_upcoming'        => $ridesUpcoming,
            'ridesUpcoming'         => $ridesUpcoming,
            'trajets_a_venir'       => $ridesUpcoming,
            'trajetsAVenir'         => $ridesUpcoming,

            // Réservations (total)
            'bookings'              => $bookingsTotal,
            'bookings_total'        => $bookingsTotal,
            'bookingsTotal'         => $bookingsTotal,
            'reservations'          => $bookingsTotal,
            'reservations_total'    => $bookingsTotal,
            'reservationsTotal'     => $bookingsTotal,
            'reservationsConfirmees'=> $bookingsTotal,
            'confirmed_bookings'    => $bookingsTotal,
            'booking_count'         => $bookingsTotal,
            'reservations_count'    => $bookingsTotal,

            // Réservations à venir
            'bookings_upcoming'     => $bookingsUpcoming,
            'bookingsUpcoming'      => $bookingsUpcoming,
            'reservations_upcoming' => $bookingsUpcoming,
            'reservationsUpcoming'  => $bookingsUpcoming,

            // Places restantes (à venir)
            'seats_left'            => $seatsLeftUpcoming,
            'seatsLeft'             => $seatsLeftUpcoming,
            'seats_left_total'      => $seatsLeftUpcoming,
            'seatsLeftTotal'        => $seatsLeftUpcoming,
            'places_restantes'      => $seatsLeftUpcoming,
            'placesRestantes'       => $seatsLeftUpcoming,
            'places_restantes_total'=> $seatsLeftUpcoming,
            'placesRestantesTotal'  => $seatsLeftUpcoming,

            // Places restantes (tous trajets) — au cas où la vue regarde “all”
            'seats_left_all'        => $seatsLeftAll,
            'seatsLeftAll'          => $seatsLeftAll,
            'places_restantes_all'  => $seatsLeftAll,
            'placesRestantesAll'    => $seatsLeftAll,

            // Crédits plateforme total
            'platform_credits'      => $platformCreditsTotal,
            'platformCredits'       => $platformCreditsTotal,
            'platform_credits_total'=> $platformCreditsTotal,
            'platformCreditsTotal'  => $platformCreditsTotal,
            'credits_plateforme'    => $platformCreditsTotal,
            'creditsPlateforme'     => $platformCreditsTotal,
            'total_credits'         => $platformCreditsTotal,
            'credits_total'         => $platformCreditsTotal,
            'creditsGagnes'         => $platformCreditsTotal,
            'credits_gagnes'        => $platformCreditsTotal,
        ];
    }

    public static function ridesPerDay(string $from, string $to): array
    {
        $sql = "SELECT
                    DATE(date_start) AS jour,
                    DATE(date_start) AS day,
                    COUNT(*)         AS nombre,
                    COUNT(*)         AS n
                FROM rides
                WHERE DATE(date_start) BETWEEN :f AND :t
                GROUP BY DATE(date_start)
                ORDER BY jour ASC";
        $st = self::pdo()->prepare($sql);
        $st->execute([':f'=>$from, ':t'=>$to]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function platformCreditsPerDay(string $from, string $to): array
    {
        $pdo = self::pdo();

        // 1) via transactions (commission plate-forme/plateforme)
        try {
            $sql = "SELECT
                        DATE(created_at) AS jour,
                        DATE(created_at) AS day,
                        COALESCE(SUM(montant),0) AS credits
                    FROM transactions
                    WHERE (LOWER(description) LIKE '%plate-forme%'
                           OR LOWER(description) LIKE '%plateforme%')
                      AND DATE(created_at) BETWEEN :f AND :t
                    GROUP BY DATE(created_at)
                    ORDER BY jour ASC";
            $st = $pdo->prepare($sql);
            $st->execute([':f'=>$from, ':t'=>$to]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows) return $rows;
        } catch (\Throwable $e) {}

        // 2) fallback : 2 * nb bookings confirmées par jour
        $sql = "SELECT
                    DATE(b.created_at) AS jour,
                    DATE(b.created_at) AS day,
                    COUNT(*)*2         AS credits
                FROM bookings b
                WHERE UPPER(b.status)='CONFIRMED'
                  AND DATE(b.created_at) BETWEEN :f AND :t
                GROUP BY DATE(b.created_at)
                ORDER BY jour ASC";
        $st = $pdo->prepare($sql);
        $st->execute([':f'=>$from, ':t'=>$to]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  public static function totalPlatformPlace(): int
{
    $pdo = self::pdo();

    $sql  = "SELECT COALESCE(SUM(GREATEST(seats_left, 0)), 0) FROM rides"; // si la colonne est 'seats_left'

    $stmt = $pdo->query($sql);
    return (int) ($stmt ? $stmt->fetchColumn() : 0);
}

}