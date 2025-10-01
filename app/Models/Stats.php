<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * Classe Stats
 * ------------
 * Centralise toutes les requêtes "métriques" utiles pour les dashboards (admin / employé / utilisateur).
 * - KPIs globaux (comptes, trajets, réservations, crédits de la plateforme…)
 * - Agrégations par jour (trajets / crédits)
 *
 * NB : je garde le code très défensif (try/catch) pour éviter qu'un petit souci SQL
 *      casse tout le tableau de bord : en cas d'erreur ponctuelle, on renvoie 0.
 */
final class Stats
{
    /** Raccourci : récupère l'instance PDO exposée par ma couche d'accès Sql */
    private static function pdo(): \PDO { return Sql::pdo(); }

    /**
     * KPIs globaux
     * ------------
     * Fournit un "super-set" de clés pour que la vue puisse piocher avec différents alias
     * (FR/EN + snake/camel). Comme ça, j'évite d'avoir à toucher la vue si je change un libellé.
     */
    public static function kpis(): array
    {
        $pdo = self::pdo();

        /* -----------------------------
           Utilisateurs (total)
        ------------------------------ */
        $usersTotal = 0;
        try {
            $usersTotal = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        } catch (\Throwable $e) {
            // Je garde silencieux : le dashboard doit rester affichable même si une requête tombe
        }

        /* -----------------------------
           Trajets à venir (tous statuts non filtrés ici)
        ------------------------------ */
        $ridesUpcoming = 0;
        try {
            $ridesUpcoming = (int)$pdo->query("
                SELECT COUNT(*) FROM rides WHERE date_start >= NOW()
            ")->fetchColumn();
        } catch (\Throwable $e) {}

        /* -----------------------------
           Trajets à venir avec places (seats_left > 0)
        ------------------------------ */
        $ridesUpcomingAvailable = 0;
        try {
            $ridesUpcomingAvailable = (int)$pdo->query("
                SELECT COUNT(*) FROM rides
                WHERE date_start >= NOW() AND GREATEST(seats_left,0) > 0
            ")->fetchColumn();
        } catch (\Throwable $e) {}

        /* -----------------------------
           Réservations confirmées : total + à venir (via la date du trajet)
        ------------------------------ */
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
                WHERE UPPER(b.status)='CONFIRMED'
                  AND r.date_start >= NOW()
            ");
            $bookingsUpcoming = (int)($st ? $st->fetchColumn() : 0);
        } catch (\Throwable $e) {}

        /* -----------------------------
           Places restantes (somme des seats_left)
           - à venir
           - global (tous trajets)
        ------------------------------ */
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

        /* -----------------------------
           Crédits gagnés par la plateforme (total)
           - 1ère source : table transactions (libellé "plate-forme" ou "plateforme")
           - fallback si 0 : 2 crédits * nb de réservations confirmées (hypothèse métier)
        ------------------------------ */
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
            // Hypothèse commune EcoRide : 2 crédits par réservation confirmée
            $platformCreditsTotal = $bookingsTotal * 2;
        }

        /* -----------------------------
           Je renvoie un set généreux de clés (FR/EN + snake/camel)
           => ça me libère des contraintes de nommage côté vues.
        ------------------------------ */
        return [
            /* Utilisateurs */
            'users'                 => $usersTotal,
            'active_users'          => $usersTotal,
            'users_active'          => $usersTotal,
            'usersTotal'            => $usersTotal,
            'utilisateurs_actifs'   => $usersTotal,

            /* Trajets à venir (tous) */
            'rides_upcoming'        => $ridesUpcoming,
            'ridesUpcoming'         => $ridesUpcoming,
            'trajets_a_venir'       => $ridesUpcoming,
            'trajetsAVenir'         => $ridesUpcoming,

            /* Trajets disponibles à venir (avec places) */
            'rides_upcoming_available'  => $ridesUpcomingAvailable,
            'ridesUpcomingAvailable'    => $ridesUpcomingAvailable,
            'trajets_disponibles'       => $ridesUpcomingAvailable,
            'trajetsDisponibles'        => $ridesUpcomingAvailable,

            /* Réservations (total) */
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

            /* Réservations à venir */
            'bookings_upcoming'     => $bookingsUpcoming,
            'bookingsUpcoming'      => $bookingsUpcoming,
            'reservations_upcoming' => $bookingsUpcoming,
            'reservationsUpcoming'  => $bookingsUpcoming,

            /* Places restantes (à venir) */
            'seats_left'            => $seatsLeftUpcoming,
            'seatsLeft'             => $seatsLeftUpcoming,
            'seats_left_total'      => $seatsLeftUpcoming,
            'seatsLeftTotal'        => $seatsLeftUpcoming,
            'seats_left_upcoming'   => $seatsLeftUpcoming,
            'places_restantes'      => $seatsLeftUpcoming,
            'placesRestantes'       => $seatsLeftUpcoming,
            'places_restantes_total'=> $seatsLeftUpcoming,
            'placesRestantesTotal'  => $seatsLeftUpcoming,

            /* Places restantes (tous trajets) */
            'seats_left_all'        => $seatsLeftAll,
            'seatsLeftAll'          => $seatsLeftAll,
            'places_restantes_all'  => $seatsLeftAll,
            'placesRestantesAll'    => $seatsLeftAll,

            /* Crédits plateforme (total) */
            'platform_credits'      => $platformCreditsTotal,
            'platformCredits'       => $platformCreditsTotal,
            'platform_credits_total'=> $platformCreditsTotal,
            'platformCreditsTotal'  => $platformCreditsTotal,
            'platform_total'        => $platformCreditsTotal, 
            'credits_plateforme'    => $platformCreditsTotal,
            'creditsPlateforme'     => $platformCreditsTotal,
            'total_credits'         => $platformCreditsTotal,
            'credits_total'         => $platformCreditsTotal,
            'creditsGagnes'         => $platformCreditsTotal,
            'credits_gagnes'        => $platformCreditsTotal,
        ];
    }

    /**
     * Nombre de trajets par jour sur une période [from; to]
     * -----------------------------------------------------
     * @param string $from  YYYY-MM-DD (inclus)
     * @param string $to    YYYY-MM-DD (inclus)
     * @return array  [{ jour: 'YYYY-MM-DD', day: 'YYYY-MM-DD', nombre: int, n: int }]
     */
    public static function ridesPerDay(string $from, string $to): array
    {
        // Historique brut : je ne filtre pas par places restantes ici (c'est volontaire)
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

    /**
     * Crédits plateforme par jour
     * ---------------------------
     * Priorité à la table `transactions` (via libellé), sinon fallback logique :
     * 2 crédits par réservation confirmée, groupé par jour de création.
     *
     * @return array [{ jour: 'YYYY-MM-DD', day: 'YYYY-MM-DD', credits: int }]
     */
    public static function platformCreditsPerDay(string $from, string $to): array
    {
        $pdo = self::pdo();

        // 1) Source officielle : transactions libellées "plate-forme/plateforme"
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
            if ($rows) return $rows; // si on a des données, je sors ici
        } catch (\Throwable $e) {
            // en cas d'erreur je bascule sur le fallback
        }

        // 2) Fallback : 2 * nb de bookings confirmées (jour = date création de la résa)
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

    /**
     * Somme globale des places restantes (tous trajets confondus)
     * -----------------------------------------------------------
     * Méthode conservée pour compat éventuelle.
     */
    public static function totalPlatformPlace(): int
    {
        $pdo = self::pdo();
        $sql  = "SELECT COALESCE(SUM(GREATEST(seats_left, 0)), 0) FROM rides";
        $stmt = $pdo->query($sql);
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }
}
