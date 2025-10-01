<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * Classe VehicleModel
 * -------------------
 * Deuxième version du modèle véhicule (parallèle à Vehicle).
 * Ici, j’ai un CRUD complet + quelques helpers (liste, appartenance).
 * Différence : ce modèle stocke plus d’infos (preferences, smoker, animals).
 */
class VehicleModel
{
    /**
     * Récupère tous les véhicules d’un utilisateur.
     * Triés par ID décroissant (dernier ajouté en premier).
     */
    public static function listByUser(int $userId): array
    {
        $q = Sql::pdo()->prepare('SELECT * FROM vehicles WHERE user_id=:u ORDER BY id DESC');
        $q->execute([':u'=>$userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si un véhicule appartient bien à un utilisateur.
     * @param int $vehicleId  ID du véhicule
     * @param int $userId     ID du propriétaire attendu
     * @return bool           True si l’utilisateur est bien propriétaire
     */
    public static function belongsTo(int $vehicleId, int $userId): bool
    {
        $q = Sql::pdo()->prepare('SELECT COUNT(*) FROM vehicles WHERE id=:id AND user_id=:u');
        $q->execute([':id'=>$vehicleId, ':u'=>$userId]);
        return (bool)$q->fetchColumn();
    }

    /**
     * Crée un véhicule pour un utilisateur.
     * Ici, on stocke des infos supplémentaires par rapport à Vehicle.php :
     *  - preferences : préférences du conducteur
     *  - smoker : accepte fumeurs
     *  - animals : accepte animaux
     */
    public static function create(int $userId, array $v): bool
    {
        $sql = 'INSERT INTO vehicles (user_id, plate, first_registration, brand, model, color, energy, seats, preferences, smoker, animals)
                VALUES (:u, :plate, :first_reg, :brand, :model, :color, :energy, :seats, :prefs, :smoker, :animals)';
        return Sql::pdo()->prepare($sql)->execute([
            ':u'        => $userId,
            ':plate'    => $v['plate'],
            ':first_reg'=> $v['first_registration'],
            ':brand'    => $v['brand'],
            ':model'    => $v['model'],
            ':color'    => $v['color'],
            ':energy'   => $v['energy'],
            ':seats'    => $v['seats'],
            ':prefs'    => $v['preferences'],
            ':smoker'   => $v['smoker'],
            ':animals'  => $v['animals'],
        ]);
    }

    /**
     * Met à jour un véhicule existant.
     * Même logique que create(), mais avec un WHERE id.
     */
    public static function update(int $vehicleId, array $v): bool
    {
        $sql = 'UPDATE vehicles SET plate=:plate, first_registration=:first_reg, brand=:brand, model=:model, color=:color,
                energy=:energy, seats=:seats, preferences=:prefs, smoker=:smoker, animals=:animals WHERE id=:id';
        return Sql::pdo()->prepare($sql)->execute([
            ':id'       => $vehicleId,
            ':plate'    => $v['plate'],
            ':first_reg'=> $v['first_registration'],
            ':brand'    => $v['brand'],
            ':model'    => $v['model'],
            ':color'    => $v['color'],
            ':energy'   => $v['energy'],
            ':seats'    => $v['seats'],
            ':prefs'    => $v['preferences'],
            ':smoker'   => $v['smoker'],
            ':animals'  => $v['animals'],
        ]);
    }

    /**
     * Supprime un véhicule (peu importe le user).
     * ⚠ À utiliser avec précaution : pas de contrôle propriétaire ici.
     */
    public static function delete(int $vehicleId): bool
    {
        return Sql::pdo()->prepare('DELETE FROM vehicles WHERE id=:id')->execute([':id'=>$vehicleId]);
    }
}
