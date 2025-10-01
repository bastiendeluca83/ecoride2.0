<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\Sql; 
use PDO;

/**
 * Classe Vehicle
 * --------------
 * Ce modèle gère les véhicules des utilisateurs (CRUD complet).
 * Chaque véhicule est lié à un utilisateur (via user_id).
 */
final class Vehicle
{
    /** Raccourci : retourne l’instance PDO */
    private static function pdo(): PDO { return Sql::pdo(); }

    /**
     * Liste tous les véhicules d’un utilisateur.
     * @param int $userId  ID de l’utilisateur
     * @return array       Liste des véhicules (même vide si aucun trouvé)
     */
    public static function forUser(int $userId): array
    {
        $st = self::pdo()->prepare(
          "SELECT id, user_id, brand, model, color, energy, plate, first_reg_date, seats
             FROM vehicles WHERE user_id=:uid ORDER BY id DESC"
        );
        $st->execute([':uid'=>$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Récupère un véhicule uniquement si l’utilisateur en est propriétaire.
     * @param int $id      ID du véhicule
     * @param int $userId  ID de l’utilisateur (propriétaire attendu)
     * @return array|null  Détails du véhicule ou null si non trouvé/pas à lui
     */
    public static function findOwned(int $id, int $userId): ?array
    {
        $st = self::pdo()->prepare("SELECT * FROM vehicles WHERE id=:id AND user_id=:uid");
        $st->execute([':id'=>$id, ':uid'=>$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crée un nouveau véhicule pour un utilisateur.
     * Les champs manquants sont mis à NULL par défaut.
     */
    public static function create(int $userId, array $d): bool
    {
        $st = self::pdo()->prepare(
          "INSERT INTO vehicles (user_id, brand, model, color, energy, plate, first_reg_date, seats)
           VALUES (:uid, :brand, :model, :color, :energy, :plate, :first_reg_date, :seats)"
        );
        return $st->execute([
            ':uid'=>$userId,
            ':brand'=>$d['brand'] ?? null,
            ':model'=>$d['model'] ?? null,
            ':color'=>$d['color'] ?? null,
            ':energy'=>$d['energy'] ?? null,
            ':plate'=>$d['plate'] ?? null,
            ':first_reg_date'=>($d['first_reg_date'] ?: null),
            ':seats'=>(int)($d['seats'] ?? 0),
        ]);
    }

    /**
     * Met à jour un véhicule (si l’utilisateur en est bien le propriétaire).
     * Retourne true uniquement si une ligne a été modifiée.
     */
    public static function update(int $id, int $userId, array $d): bool
    {
        $st = self::pdo()->prepare(
          "UPDATE vehicles
              SET brand=:brand, model=:model, color=:color, energy=:energy, plate=:plate,
                  first_reg_date=:first_reg_date, seats=:seats
            WHERE id=:id AND user_id=:uid"
        );
        $st->execute([
            ':brand'=>$d['brand'] ?? null,
            ':model'=>$d['model'] ?? null,
            ':color'=>$d['color'] ?? null,
            ':energy'=>$d['energy'] ?? null,
            ':plate'=>$d['plate'] ?? null,
            ':first_reg_date'=>($d['first_reg_date'] ?: null),
            ':seats'=>(int)($d['seats'] ?? 0),
            ':id'=>$id, ':uid'=>$userId,
        ]);
        return $st->rowCount()>0;
    }

    /**
     * Supprime un véhicule uniquement s’il appartient à l’utilisateur.
     * Retourne true si la suppression a bien eu lieu.
     */
    public static function delete(int $id, int $userId): bool
    {
        $st = self::pdo()->prepare("DELETE FROM vehicles WHERE id=:id AND user_id=:uid");
        $st->execute([':id'=>$id, ':uid'=>$userId]);
        return $st->rowCount()>0;
    }
}
