<?php
declare(strict_types=1);

namespace App\Db;

use MongoDB\Client;
use MongoDB\Database;

/**
 * Mongo
 * - Petit wrapper statique pour obtenir une instance de base MongoDB.
 * - Je garde une instance unique (cache dans self::$db) pour éviter de recréer le client.
 * - Les variables d'env utilisées :
 *     - MONGO_URI : ex. "mongodb://localhost:27017" (je mets un timeout court)
 *     - MONGO_DB  : ex. "ecoride" (nom de la base applicative)
 *
 * Remarque : j'effectue un "ping" sur la base admin pour m'assurer que le serveur répond,
 * puis je sélectionne la base applicative. En cas d'échec, je log et je renvoie null
 * (afin de ne pas casser l'appli — à la vue de gérer l'absence de données Mongo).
 */
final class Mongo
{
    /** Instance de Database mise en cache (singleton léger) */
    private static ?Database $db = null;

    /**
     * Récupère l'instance Database MongoDB
     * - Retourne null si la connexion/ping échoue (le reste de l'app doit rester tolérant).
     */
    public static function db(): ?Database
    {
        // si déjà initialisée, je renvoie l'instance directement (pas de nouveau client)
        if (self::$db) return self::$db;

        // lecture des variables d'environnement avec des valeurs par défaut safe en dev
        $uri  = getenv('MONGO_URI') ?: 'mongodb://localhost:27017';
        $name = getenv('MONGO_DB')  ?: 'ecoride';

        try {
            // je crée un client avec un timeout de sélection de serveur court (2s)
            $client = new Client($uri, ['serverSelectionTimeoutMS' => 2000]);

            /* Ping pour s'assurer que le serveur répond (évite d'attendre sur la 1ère requête) */
            $client->selectDatabase('admin')->command(['ping' => 1]);

            // je sélectionne la base applicative et je la mets en cache
            self::$db = $client->selectDatabase($name);
            return self::$db;
        } catch (\Throwable $e) {
            // en cas de souci (URI invalide, serveur down, auth KO...), je log et je renvoie null
            error_log('[Mongo] connection failed: '.$e->getMessage());
            return null;
        }
    }
}
