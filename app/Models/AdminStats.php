<?php

/* (Statistiques pour l'admin) */

declare(strict_types=1);

namespace App\Models;

/**
 * AdminStats
 * - Modèle "lecture seule" pour sortir des agrégats/statistiques côté Admin.
 * - Je reste strict MVC : aucune logique de présentation, je renvoie des tableaux simples.
 * - Je m'appuie sur BaseModels::all() et ::one() pour exécuter les requêtes SQL.
 */
class AdminStats extends BaseModels
{
    /*
     * Nombre de covoiturages par jour (dans l'intervalle [fromDate, toDate]).
     * - Je regroupe par DATE(date_start) pour compter les trajets publiés/départ ce jour-là.
     * - Retour: lignes { jour: 'YYYY-MM-DD', nb: int } prêtes pour un graphique.
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

    /*
     * Crédits plateforme / jour.
     * - Chemin principal: je somme les transactions dont la description contient
     *   'plate-forme' / 'plateforme' (insensible à la casse).
     * - Fallback: si la table transactions n'existe pas ou qu'il n'y a rien,
     *   je calcule 2 crédits par réservation CONFIRMED (paramétrable via $platformFee).
     *
     * Retour: lignes { jour: 'YYYY-MM-DD', credits: int }.
     */
    public static function platformCreditsPerDay(
        string $fromDate,
        string $toDate,
        int $platformFee = 2
    ): array {
        /* 1) via transactions (commission réelle enregistrée) */
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
            if ($rows) return $rows; // si j'ai des données, je sors direct
        } catch (\Throwable $e) {
            /* ignore -> je passe au fallback si la table n'existe pas / autre souci */
        }

        /* 2) fallback via bookings confirmées (approximation: X réservations * fee) */
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

    /*
     * Total crédits gagnés par la plateforme.
     * - Chemin principal: somme des transactions dont la description indique la commission.
     * - Fallback: nb de réservations confirmées * fee (par défaut 2).
     */
    public static function totalCreditsEarned(int $platformFee = 2): int
    {
        /* 1) transactions (source de vérité si disponible) */
        try {
            $row = self::one("
                SELECT COALESCE(SUM(montant),0) AS total
                FROM transactions
                WHERE LOWER(description) LIKE '%plate-forme%'
                   OR LOWER(description) LIKE '%plateforme%'
            ");
            if ($row && isset($row['total'])) return (int)$row['total'];
        } catch (\Throwable $e) {
            /* ignore: je tombe sur le fallback */
        }

        /* 2) fallback (approximation) */
        $row = self::one("
            SELECT COUNT(*) * :fee AS total
            FROM bookings b
            WHERE UPPER(b.status)='CONFIRMED'
        ", [':fee' => $platformFee]);

        return (int)($row['total'] ?? 0);
    }

    /*
     * Historique détaillé: crédits / jour + liste des ride_id du jour.
     * - Chemin principal: je pars des transactions de commission (plate-forme).
     * - Fallback: bookings confirmées (2 crédits par réservation) + concat des ride_id.
     *
     * Retour: lignes { jour: 'YYYY-MM-DD', credits: int, ride_ids: '7,12,15' }.
     * - Utile pour afficher à l’admin un récap avec liens vers les trajets concernés.
     */
    public static function platformCreditsHistoryDetailed(
        string $fromDate,
        string $toDate,
        int $platformFee = 2
    ): array {
        /* 1) transactions (données réelles) */
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
            /* ignore: je passe au fallback si besoin */
        }

        /* 2) fallback via bookings confirmées (approximation + ride_ids) */
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
