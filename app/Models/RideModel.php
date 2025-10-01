<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * Classe RideModel
 * -------------------------
 * Ce modèle gère toutes les opérations liées aux trajets (rides).
 * On y retrouve la création d’un trajet, la récupération des trajets
 * à venir et passés d’un conducteur, ainsi que des vérifications simples.
 */
class RideModel
{
    /**
     * Créer un trajet
     * -------------------------
     * Insère un trajet dans la base de données à partir d’un tableau associatif.
     * Le statut est fixé à "PREVU" par défaut.
     * On calcule également si le véhicule est électrique (is_electric_cached).
     *
     * @param array $r Données du trajet (driver_id, vehicle_id, from_city, to_city, date_start, date_end, price, seats_left)
     * @return int|false Retourne l’ID du trajet créé ou false si échec
     */
    public static function create(array $r)
    {
        $sql = 'INSERT INTO rides (
                    driver_id, vehicle_id, from_city, to_city,
                    date_start, date_end, price, seats_left, status, is_electric_cached, created_at
                ) VALUES (
                    :driver, :vehicle, :fromc, :toc,
                    :ds, :de, :price, :seats, "PREVU",
                    (SELECT (energy="ELECTRIC") FROM vehicles WHERE id=:vehicle), NOW()
                )';

        $ok = Sql::pdo()->prepare($sql)->execute([
            ':driver'  => $r['driver_id'],
            ':vehicle' => $r['vehicle_id'],
            ':fromc'   => $r['from_city'],
            ':toc'     => $r['to_city'],
            ':ds'      => $r['date_start'],
            ':de'      => $r['date_end'] ?? null,
            ':price'   => (int)$r['price'],
            ':seats'   => (int)$r['seats_left'],
        ]);

        // Si l’insertion réussit, on retourne l’ID généré
        return $ok ? (int)Sql::pdo()->lastInsertId() : false;
    }

    /**
     * Récupérer les trajets à venir d’un conducteur
     * -------------------------
     * Retourne uniquement les trajets dont la date de départ est >= à maintenant.
     *
     * @param int $driverId ID du conducteur
     * @return array Liste des trajets futurs
     */
    public static function upcomingByDriver(int $driverId): array
    {
        $q = Sql::pdo()->prepare('
            SELECT * FROM rides
            WHERE driver_id=:d AND date_start >= NOW()
            ORDER BY date_start ASC
        ');
        $q->execute([':d'=>$driverId]);

        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer l’historique des trajets d’un conducteur
     * -------------------------
     * Retourne uniquement les trajets dont la date de départ est < à maintenant.
     *
     * @param int $driverId ID du conducteur
     * @return array Liste des trajets passés
     */
    public static function historyByDriver(int $driverId): array
    {
        $q = Sql::pdo()->prepare('
            SELECT * FROM rides
            WHERE driver_id=:d AND date_start < NOW()
            ORDER BY date_start DESC
        ');
        $q->execute([':d'=>$driverId]);

        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un trajet appartient bien à un utilisateur
     * -------------------------
     * Permet par exemple de sécuriser les actions de modification/suppression.
     *
     * @param int $rideId ID du trajet
     * @param int $userId ID de l’utilisateur (conducteur)
     * @return bool True si l’utilisateur est bien propriétaire du trajet
     */
    public static function isOwnedBy(int $rideId, int $userId): bool
    {
        $q = Sql::pdo()->prepare('
            SELECT COUNT(*) FROM rides
            WHERE id=:id AND driver_id=:u
        ');
        $q->execute([':id'=>$rideId, ':u'=>$userId]);

        return (bool)$q->fetchColumn();
    }
}
