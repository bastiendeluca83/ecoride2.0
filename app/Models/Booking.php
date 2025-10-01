<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * Booking
 * - Modèle des réservations.
 * - Je centralise ici les opérations CRUD simples et les requêtes
 *   utilisées par les contrôleurs (dashboard user/employee, etc.).
 */
class Booking
{
    /* ===========================
       Helpers internes (privés)
       =========================== */

    /** Récupère l'instance PDO depuis mon wrapper Sql */
    private static function pdo(): \PDO { return Sql::pdo(); }

    /** Exécute une requête et renvoie une seule ligne (ou null) */
    private static function one(string $sql, array $p=[]): ?array {
        $st=self::pdo()->prepare($sql); $st->execute($p);
        $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null;
    }

    /** Exécute une requête et renvoie toutes les lignes (tableau vide si rien) */
    private static function all(string $sql, array $p=[]): array {
        $st=self::pdo()->prepare($sql); $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    /* ===========================
       Création / mise à jour
       =========================== */

    /**
     * Crée une réservation (par défaut en CONFIRMED).
     * @return int id auto-incrémenté de la réservation
     */
    public static function create(int $rideId,int $passengerId,int $creditsUsed,string $status='CONFIRMED'): int {
        $pdo = self::pdo();
        $sql = "INSERT INTO bookings(ride_id,passenger_id,status,credits_spent,created_at)
                VALUES(:r,:u,:s,:c,NOW())";
        $pdo->prepare($sql)->execute([':r'=>$rideId, ':u'=>$passengerId, ':c'=>$creditsUsed, ':s'=>$status]);
        return (int)$pdo->lastInsertId();
    }

    /** Passe une réservation en CONFIRMED (utilitaire simple) */
    public static function confirm(int $id): bool {
        return self::pdo()->prepare("UPDATE bookings SET status='CONFIRMED' WHERE id=:id")->execute([':id'=>$id]);
    }

    /** Change le statut d'une réservation (CANCELLED, PENDING, etc.) */
    public static function setStatus(int $id,string $status): bool {
        return self::pdo()->prepare("UPDATE bookings SET status=:s WHERE id=:id")->execute([':s'=>$status, ':id'=>$id]);
    }

    /* ===========================
       Sélections simples
       =========================== */

    /** Récupère une réservation par son id */
    public static function findById(int $id): ?array { return self::one("SELECT * FROM bookings WHERE id=:id", [':id'=>$id]); }

    /**
     * Récupère la réservation pour un couple (trajet, utilisateur).
     * - Sert notamment à vérifier qu’un passager a bien réservé un trajet donné.
     */
    public static function findByRideAndUser(int $rideId,int $userId): ?array {
        return self::one("SELECT * FROM bookings WHERE ride_id=:r AND passenger_id=:u LIMIT 1", [':r'=>$rideId, ':u'=>$userId]);
    }

    /* ===========================
       Pour dashboard USER
       =========================== */

    /**
     * Réservations à venir du passager connecté (CONFIRMED uniquement).
     * - Joint le trajet pour afficher les infos utiles (villes, dates, prix).
     */
    public static function forPassengerUpcoming(int $userId): array {
        $sql = "SELECT b.*, r.from_city, r.to_city, r.date_start, r.date_end, r.price
                FROM bookings b
                JOIN rides r ON r.id=b.ride_id
                WHERE b.passenger_id=:u AND r.date_start>=NOW() AND b.status='CONFIRMED'
                ORDER BY r.date_start ASC";
        return self::all($sql, [':u'=>$userId]);
    }

    /**
     * Historique des réservations passées du passager (tous statuts).
     * - Pratique pour un onglet "Historique" dans le dashboard.
     */
    public static function forPassengerPast(int $userId): array {
        $sql = "SELECT b.*, r.from_city, r.to_city, r.date_start, r.date_end, r.price
                FROM bookings b
                JOIN rides r ON r.id=b.ride_id
                WHERE b.passenger_id=:u AND r.date_start<NOW()
                ORDER BY r.date_start DESC";
        return self::all($sql, [':u'=>$userId]);
    }

    /* ===========================
       Pour dashboard EMPLOYEE
       =========================== */

    /**
     * Dernières réservations annulées (avec infos trajet + email passager).
     * - LIMIT paramétrable (par défaut 20).
     */
    public static function cancelledLast(int $limit = 20): array {
        $sql = "SELECT b.*, r.from_city, r.to_city, r.date_start, u.email AS passenger_email
                FROM bookings b
                JOIN rides r ON r.id=b.ride_id
                JOIN users u ON u.id=b.passenger_id
                WHERE b.status = 'CANCELLED'
                ORDER BY b.created_at DESC
                LIMIT :lim";
        $st = self::pdo()->prepare($sql);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Supprime une réservation (hard delete) */
    public static function delete(int $id): bool { return self::pdo()->prepare("DELETE FROM bookings WHERE id=:id")->execute([':id'=>$id]); }

    /* ===========================
       Utilitaires pour trajets
       =========================== */

    /**
     * Participants confirmés d’un trajet (nom affichable + avatar).
     * - Sert côté conducteur pour voir qui a réservé.
     */
    public static function participantsForRide(int $rideId): array
    {
        $sql = "
            SELECT
                u.id,
                TRIM(
                    COALESCE(
                        CONCAT(u.prenom, ' ', u.nom),
                        CONCAT(u.first_name, ' ', u.last_name),
                        u.pseudo,
                        u.email
                    )
                ) AS display_name,
                u.avatar_path
            FROM bookings b
            JOIN users u ON u.id = b.passenger_id
            WHERE b.ride_id = :r
              AND UPPER(b.status) = 'CONFIRMED'
            ORDER BY b.created_at ASC
        ";
        return self::all($sql, [':r' => $rideId]);
    }

    /**
     * Passagers confirmés d’un trajet (avec email).
     * - Utilisé pour envoyer l’invitation d’avis à la fin du trajet.
     * - Retour: id, email, display_name, avatar_path
     */
    public static function passengersWithEmailForRide(int $rideId): array
    {
        $sql = "
            SELECT
                u.id,
                u.email,
                TRIM(
                    COALESCE(
                        CONCAT(u.prenom, ' ', u.nom),
                        CONCAT(u.first_name, ' ', u.last_name),
                        u.pseudo,
                        u.email
                    )
                ) AS display_name,
                u.avatar_path
            FROM bookings b
            JOIN users u ON u.id = b.passenger_id
            WHERE b.ride_id = :r
              AND UPPER(b.status) = 'CONFIRMED'
            ORDER BY b.created_at ASC
        ";
        return self::all($sql, [':r' => $rideId]);
    }

    /**
     * Compte des trajets terminés pour un utilisateur (en tant que passager confirmé).
     * - Pratique pour des stats profil / badges / calcul CO₂.
     */
    public static function countCompletedByPassenger(int $userId): int
    {
        $sql = "SELECT COUNT(*)
                FROM bookings b
                JOIN rides r ON r.id = b.ride_id
                WHERE b.passenger_id = :u
                  AND UPPER(b.status) = 'CONFIRMED'
                  AND r.date_end IS NOT NULL
                  AND r.date_end < NOW()";
        $st = self::pdo()->prepare($sql);
        $st->execute([':u'=>$userId]);
        return (int)$st->fetchColumn();
    }
}
