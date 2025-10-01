<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * BookingModel
 * -------------
 * Ce modèle centralise les accès aux réservations (bookings) côté passager.
 * Toute la logique SQL liée aux réservations est regroupée ici.
 *
 * Points importants :
 * - Requêtes préparées pour éviter les injections SQL.
 * - Retour en tableaux associatifs (FETCH_ASSOC) → plus pratique pour les vues.
 */
class BookingModel
{
    /**
     * upcomingByPassenger
     * -------------------
     * Récupère les réservations à venir pour un passager donné.
     *
     * Conditions :
     * - La réservation doit être confirmée (b.status = 'confirmed').
     * - Le trajet doit être encore actif (r.status = 'scheduled' ou 'started').
     * - Tri par date de départ croissante (le plus proche en premier).
     *
     * @param int $userId ID du passager
     * @return array      Liste des réservations à venir
     */
    public static function upcomingByPassenger(int $userId): array
    {
        $sql = "
            SELECT 
                b.*, 
                r.from_city, 
                r.to_city, 
                r.start_at, 
                r.price, 
                r.status
            FROM bookings b
            JOIN rides r ON r.id = b.ride_id
            WHERE b.passenger_id = :u
              AND b.status = 'confirmed'
              AND r.status IN ('scheduled','started')
            ORDER BY r.start_at ASC
        ";

        $q = Sql::pdo()->prepare($sql);
        $q->execute([':u' => $userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * historyByPassenger
     * ------------------
     * Récupère l'historique des réservations d'un passager.
     *
     * Conditions :
     * - La réservation doit être terminée ou annulée (b.status IN ('completed','canceled')).
     * - Tri par date de départ décroissante (le plus récent en premier).
     *
     * @param int $userId ID du passager
     * @return array      Liste des réservations passées
     */
    public static function historyByPassenger(int $userId): array
    {
        $sql = "
            SELECT
                b.*, 
                r.from_city, 
                r.to_city, 
                r.start_at, 
                r.price, 
                r.status
            FROM bookings b
            JOIN rides r ON r.id = b.ride_id
            WHERE b.passenger_id = :u
              AND b.status IN ('completed','canceled')
            ORDER BY r.start_at DESC
        ";

        $q = Sql::pdo()->prepare($sql);
        $q->execute([':u' => $userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
