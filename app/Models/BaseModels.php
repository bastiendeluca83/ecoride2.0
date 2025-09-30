<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * BaseModels
 * - Classe de base (abstraite) pour mes modèles SQL.
 * - Je centralise ici des helpers minimalistes :
 *   • pdo()  : récupère l'instance PDO unique (gérée par App\Db\Sql)
 *   • all()  : exécute une requête et renvoie toutes les lignes (FETCH_ASSOC)
 *   • one()  : exécute une requête et renvoie une seule ligne (ou null)
 *
 * Objectif : éviter de répéter le même code de préparation/exécution
 * dans chaque modèle et rester propre côté MVC.
 */
abstract class BaseModels
{
    /**
     * Accès à l'instance PDO (lazy singleton via Sql::pdo()).
     * - Je garde ça protégé pour que seuls les modèles enfants l'utilisent.
     */
    protected static function pdo(): PDO
    {
        return Sql::pdo();
    }

    /**
     * Exécute une requête SELECT et renvoie toutes les lignes.
     * @param string $sql    Requête SQL avec placeholders nommés
     * @param array  $params Paramètres liés (ex: [':id' => 42])
     * @return array         Lignes sous forme de tableaux associatifs
     *
     * Note: la protection contre l'injection SQL est assurée
     * par les requêtes préparées + execute($params).
     */
    protected static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Exécute une requête SELECT et renvoie la première ligne (ou null).
     * @param string $sql    Requête SQL avec placeholders nommés
     * @param array  $params Paramètres liés
     * @return array|null    Une ligne (assoc) ou null si aucune
     */
    protected static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
