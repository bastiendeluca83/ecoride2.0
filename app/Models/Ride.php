<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * Classe Ride
 * ----------------------
 * Elle gère tout ce qui concerne les trajets (covoiturages).
 * On centralise ici toutes les requêtes SQL liées à la table `rides`.
 */
class Ride
{
    /* ================================================================
       Helpers internes pour factoriser le code
    ================================================================= */

    /** Retourne une instance PDO (connexion à la base) */
    private static function pdo(): \PDO {
        return Sql::pdo();
    }

    /** Récupère une seule ligne (ou null si vide) */
    private static function one(string $sql, array $p=[]): ?array {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Récupère toutes les lignes (ou tableau vide si rien) */
    private static function all(string $sql, array $p=[]): array {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ================================================================
       Création et insertion de trajets
    ================================================================= */

    /**
     * Crée un nouveau trajet en BDD
     * On insère bien le statut (par défaut PREVU).
     */
    public static function create(
        int $driverId, int $vehicleId, string $fromCity, string $toCity,
        string $dateStart, string $dateEnd, int $price, int $seats, string $status='PREVU'
    ): int {
        $pdo = self::pdo();

        $sql = "INSERT INTO rides(
                    driver_id, vehicle_id, from_city, to_city,
                    date_start, date_end, price, seats_left,
                    is_electric_cached, status, created_at
                )
                SELECT
                    :d, :v, :fc, :tc,
                    :ds, :de, :pr, :sl,
                    (SELECT (energy='ELECTRIC') FROM vehicles WHERE id=:v),
                    :st, NOW()";

        $pdo->prepare($sql)->execute([
            ':d'=>$driverId, ':v'=>$vehicleId, ':fc'=>$fromCity, ':tc'=>$toCity,
            ':ds'=>$dateStart, ':de'=>$dateEnd, ':pr'=>$price, ':sl'=>$seats, ':st'=>$status
        ]);

        return (int)$pdo->lastInsertId(); // On retourne l'id généré
    }

    /**
     * Variante pratique : création d’un trajet via un tableau (payload).
     * Utilisé par le contrôleur pour éviter de gérer manuellement tous les paramètres.
     */
    public static function createForDriver(int $driverId, int $vehicleId, array $payload) {
        $from  = trim((string)($payload['from_city']  ?? ''));
        $to    = trim((string)($payload['to_city']    ?? ''));
        $ds    = trim((string)($payload['date_start'] ?? ''));
        $de    = trim((string)($payload['date_end']   ?? ''));
        $price = (int)($payload['price'] ?? 0);
        $seats = (int)($payload['seats'] ?? 0);

        // On vérifie que les infos de base sont bien présentes
        if ($from==='' || $to==='' || $ds==='' || $de==='' || $seats<=0) return false;

        $id = self::create($driverId, $vehicleId, $from, $to, $ds, $de, $price, $seats, 'PREVU');
        return $id > 0 ? $id : false;
    }

    /**
     * Vérifie si un trajet existe déjà (anti-doublon simple).
     * Si oui -> on retourne son ID, sinon on le crée.
     */
    public static function ensureRide(
        int $driverId, int $vehicleId, string $fromCity, string $toCity,
        string $dateStart, string $dateEnd, int $price, ?int $seatsLeft = null
    ): ?int {
        $exists = self::one(
            "SELECT id FROM rides
             WHERE driver_id=:d AND vehicle_id=:v AND from_city=:fc AND to_city=:tc AND date_start=:ds
             LIMIT 1",
            [':d'=>$driverId, ':v'=>$vehicleId, ':fc'=>$fromCity, ':tc'=>$toCity, ':ds'=>$dateStart]
        );

        if ($exists) return (int)$exists['id'];

        // Si aucune valeur passée, on récupère la capacité du véhicule
        if ($seatsLeft === null) {
            $row = self::one("SELECT seats FROM vehicles WHERE id=:v", [':v'=>$vehicleId]);
            $seatsLeft = (int)($row['seats'] ?? 0);
            if ($seatsLeft <= 0) $seatsLeft = 1; // Sécurité
        }

        $id = self::create($driverId, $vehicleId, $fromCity, $toCity, $dateStart, $dateEnd, $price, $seatsLeft, 'PREVU');
        return $id > 0 ? $id : null;
    }

    /* ================================================================
       Recherche et consultation
    ================================================================= */

    /** Récupère un trajet par son ID */
    public static function findById(int $id): ?array {
        $sql = "SELECT r.*, u.email AS driver_email, v.brand, v.model, v.energy, v.seats
                FROM rides r
                JOIN users u ON u.id=r.driver_id
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.id=:id";
        return self::one($sql, [':id'=>$id]);
    }

    /**
     * Recherche des trajets avec filtres :
     * - ville départ/arrivée
     * - date
     * - voyage éco uniquement (si demandé)
     * - prix maximum
     * - durée max
     */
    public static function search(string $fromCity,string $toCity,string $date,
        bool $ecoOnly=false,?int $priceMax=null,?int $durationMaxMin=null
    ): array {
        $sql = "SELECT r.*, v.energy
                FROM rides r
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.from_city=:fc AND r.to_city=:tc AND DATE(r.date_start)=:d
                  AND r.seats_left>0 AND r.status IN ('PREVU','STARTED')";
        $p=[':fc'=>$fromCity, ':tc'=>$toCity, ':d'=>$date];

        if ($ecoOnly) { $sql .= " AND r.is_electric_cached=1"; }
        if ($priceMax!==null) { $sql .= " AND r.price <= :pm"; $p[':pm']=$priceMax; }
        if ($durationMaxMin!==null) { 
            $sql .= " AND TIMESTAMPDIFF(MINUTE,r.date_start,r.date_end) <= :dm"; 
            $p[':dm']=$durationMaxMin; 
        }

        $sql .= " ORDER BY r.date_start ASC";
        return self::all($sql, $p);
    }

    /** Liste des trajets d’un conducteur */
    public static function listByDriver(int $driverId): array {
        return self::all("SELECT * FROM rides WHERE driver_id=:d ORDER BY date_start DESC", [':d'=>$driverId]);
    }

    /* ================================================================
       Gestion du statut des trajets
    ================================================================= */

    /** Changer le statut d’un trajet */
    public static function setStatus(int $rideId,string $status, ?int $driverId=null): bool {
        $sql = "UPDATE rides SET status=:s WHERE id=:id";
        $params = [':s'=>$status, ':id'=>$rideId];
        if ($driverId !== null) { $sql .= " AND driver_id=:d"; $params[':d']=$driverId; }
        return self::pdo()->prepare($sql)->execute($params);
    }

    /** Marque un trajet comme démarré */
    public static function markStarted(int $rideId, int $driverId): bool {
        $sql = "UPDATE rides
                SET status='STARTED', started_at = IFNULL(started_at, NOW())
                WHERE id=:id AND driver_id=:d AND status='PREVU'";
        return self::pdo()->prepare($sql)->execute([':id'=>$rideId, ':d'=>$driverId]);
    }

    /** Marque un trajet comme terminé */
    public static function markFinished(int $rideId, int $driverId): bool {
        $sql = "UPDATE rides
                SET status='FINISHED', date_end = NOW()
                WHERE id=:id AND driver_id=:d AND status='STARTED'";
        return self::pdo()->prepare($sql)->execute([':id'=>$rideId, ':d'=>$driverId]);
    }

    /** Marque un trajet comme annulé */
    public static function markCancelled(int $rideId, int $driverId): bool {
        $sql = "UPDATE rides
                SET status='CANCELLED'
                WHERE id=:id AND driver_id=:d AND status IN ('PREVU','STARTED')";
        return self::pdo()->prepare($sql)->execute([':id'=>$rideId, ':d'=>$driverId]);
    }

    /* ================================================================
       Gestion des places
    ================================================================= */

    /** Décrémente le nombre de places disponibles */
    public static function decrementSeats(int $rideId,int $n=1): bool {
        return self::pdo()->prepare(
            "UPDATE rides SET seats_left=seats_left-:n WHERE id=:id AND seats_left>=:n"
        )->execute([':n'=>$n, ':id'=>$rideId]);
    }

    /** Incrémente le nombre de places disponibles */
    public static function incrementSeats(int $rideId,int $n=1): bool {
        return self::pdo()->prepare(
            "UPDATE rides SET seats_left=seats_left+:n WHERE id=:id"
        )->execute([':n'=>$n, ':id'=>$rideId]);
    }

    /* ================================================================
       Sélections utiles pour tableau de bord
    ================================================================= */

    /** Trajets à venir pour le conducteur */
    public static function forDriverUpcoming(int $driverId): array {
        $sql = "SELECT r.*, v.brand, v.model, v.energy, v.seats
                FROM rides r
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.driver_id=:d AND r.date_start>=NOW()
                  AND r.status IN ('PREVU','STARTED')
                ORDER BY r.date_start ASC";
        return self::all($sql, [':d'=>$driverId]);
    }

    /** Trajets passés du conducteur */
    public static function forDriverPast(int $driverId): array {
        $sql = "SELECT r.*, v.brand, v.model, v.energy, v.seats
                FROM rides r
                LEFT JOIN vehicles v ON v.id=r.vehicle_id
                WHERE r.driver_id=:d AND r.date_start<NOW()
                ORDER BY r.date_start DESC";
        return self::all($sql, [':d'=>$driverId]);
    }

    /* ================================================================
       Participants / Conducteur
    ================================================================= */

    /** Récupère les passagers confirmés (id, avatar, nom) */
    public static function passengersForRide(int $rideId): array {
        $sql = "SELECT 
                    u.id,
                    u.avatar_path,
                    TRIM(CONCAT_WS(' ', NULLIF(u.prenom, ''), NULLIF(u.nom, ''))) AS display_name
                FROM bookings b
                JOIN users u ON u.id = b.passenger_id
                WHERE b.ride_id = :r AND b.status = 'CONFIRMED'
                ORDER BY b.created_at ASC";
        return self::all($sql, [':r'=>$rideId]);
    }

    /** Récupère uniquement les e-mails des passagers confirmés */
    public static function passengersEmails(int $rideId): array {
        $rows = self::all("SELECT u.email
                           FROM bookings b JOIN users u ON u.id=b.passenger_id
                           WHERE b.ride_id=:r AND b.status='CONFIRMED'", [':r'=>$rideId]);
        return array_values(array_filter(array_map(fn($r)=>$r['email']??null, $rows)));
    }

    /** Variante : retourne id + email + display_name */
    public static function passengersWithEmailForRide(int $rideId): array {
        $sql = "SELECT 
                    u.id,
                    u.email,
                    TRIM(CONCAT_WS(' ', NULLIF(u.prenom,''), NULLIF(u.nom,''))) AS display_name
                FROM bookings b
                JOIN users u ON u.id = b.passenger_id
                WHERE b.ride_id=:r AND b.status='CONFIRMED'
                ORDER BY b.created_at ASC";
        return self::all($sql, [':r'=>$rideId]);
    }

    /** Infos du conducteur d’un trajet */
    public static function driverInfo(int $rideId): ?array {
        $sql = "SELECT 
                    u.id,
                    u.avatar_path,
                    TRIM(CONCAT_WS(' ', NULLIF(u.prenom,''), NULLIF(u.nom,''))) AS display_name
                FROM rides r
                JOIN users u ON u.id = r.driver_id
                WHERE r.id = :r
                LIMIT 1";
        return self::one($sql, [':r'=>$rideId]);
    }

    /** Nombre de trajets terminés par un conducteur */
    public static function countCompletedByDriver(int $userId): int {
        $st = self::pdo()->prepare("SELECT COUNT(*) FROM rides r
                                    WHERE r.driver_id=:u AND r.status='FINISHED'");
        $st->execute([':u'=>$userId]);
        return (int)$st->fetchColumn();
    }
}
