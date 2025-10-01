<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * Classe UserPreferences
 * ----------------------
 * Stocke et récupère les préférences d’un utilisateur (chauffeur/passager).
 * J’utilise un schéma très simple avec une ligne par user_id dans la table
 * `user_preferences`. Quand aucune ligne n’existe, je renvoie un set de
 * valeurs par défaut (pour que l’UI ait toujours quelque chose à afficher).
 */
final class UserPreferences
{
    /** Petit raccourci pour accéder au PDO exposé par ma couche Sql */
    private static function pdo(): PDO { return Sql::pdo(); }

    /**
     * Récupère les préférences d’un utilisateur.
     * Si aucune ligne n’existe, je fournis des valeurs par défaut raisonnables.
     */
    public static function get(int $userId): array {
        $st = self::pdo()->prepare("SELECT * FROM user_preferences WHERE user_id=:u");
        $st->execute([':u'=>$userId]);

        // Si pas de ligne -> je renvoie un tableau par défaut (compatible avec l’UI)
        return $st->fetch(PDO::FETCH_ASSOC) ?: [
            'user_id'      => $userId,
            'smoker'       => 0, // n’accepte pas la cigarette par défaut
            'animals'      => 0, // pas d’animaux par défaut
            'music'        => 1, // musique OK
            'chatty'       => 1, // discussion OK
            'ac'           => 1, // clim OK
            'custom_prefs' => null, // champ libre pour options perso
        ];
    }

    /**
     * Indique si des préférences existent déjà pour cet utilisateur.
     * Pratique pour afficher un état “à compléter” dans le profil.
     */
    public static function exists(int $userId): bool {
        $st = self::pdo()->prepare("SELECT 1 FROM user_preferences WHERE user_id=:u LIMIT 1");
        $st->execute([':u'=>$userId]);
        return (bool)$st->fetchColumn();
    }

    /**
     * upsert(user_id, data)
     * ---------------------
     * Insère ou met à jour en une seule requête (ON DUPLICATE KEY UPDATE).
     * Hypothèse : la colonne `user_id` est unique (ou clé primaire) dans la table.
     * Les booleans sont stockés en tinyint(1) (0/1), je caste tout proprement.
     */
    public static function upsert(int $userId, array $d): bool {
        $sql = "INSERT INTO user_preferences (user_id, smoker, animals, music, chatty, ac, custom_prefs)
                VALUES (:u,:s,:a,:m,:c,:ac,:cp)
                ON DUPLICATE KEY UPDATE
                    smoker=VALUES(smoker),
                    animals=VALUES(animals),
                    music=VALUES(music),
                    chatty=VALUES(chatty),
                    ac=VALUES(ac),
                    custom_prefs=VALUES(custom_prefs)";
        $st = self::pdo()->prepare($sql);

        // Je normalise les entrées : si absent -> je mets un défaut cohérent
        return $st->execute([
            ':u'  => $userId,
            ':s'  => (int)($d['smoker'] ?? 0),
            ':a'  => (int)($d['animals'] ?? 0),
            ':m'  => (int)($d['music'] ?? 1),
            ':c'  => (int)($d['chatty'] ?? 1),
            ':ac' => (int)($d['ac'] ?? 1),
            ':cp' => ($d['custom_prefs'] ?? null),
        ]);
    }

    /* -----------------------------
       Aliases conviviaux (même action)
       ----------------------------- */

    /** Alias de upsert : j’aime garder une API lisible côté contrôleur */
    public static function save(int $userId, array $d): bool { return self::upsert($userId, $d); }

    /** Alias de upsert (nom explicite) */
    public static function saveForUser(int $userId, array $d): bool { return self::upsert($userId, $d); }

    /** Alias de upsert (verbe court) */
    public static function set(int $userId, array $d): bool { return self::upsert($userId, $d); }

    /** Alias de upsert (verbe orienté mise à jour) */
    public static function updateForUser(int $userId, array $d): bool { return self::upsert($userId, $d); }
}
