<?php
namespace App\Db;

use MongoDB\Client;
use MongoDB\Database;

final class Mongo
{
    /** Conserve une seule instance (évite de recréer un client à chaque appel) */
    private static ?Client $client = null;
    private static ?Database $db   = null;

    /**
     * Retourne un Client MongoDB initialisé, ou null si indisponible.
     * - Gère l’authentification via variables d’env (facultatives).
     * - Timeouts courts pour ne pas bloquer l’app si Mongo ne répond pas.
     */
    public static function client(): ?Client
    {
        if (self::$client instanceof Client) {
            return self::$client;
        }

        $host   = getenv('MONGO_HOST') ?: 'mongo';
        $port   = getenv('MONGO_PORT') ?: '27017';
        $dbName = getenv('MONGO_DB')   ?: 'ecoride';

        $user   = getenv('MONGO_USER') ?: '';
        $pass   = getenv('MONGO_PASS') ?: '';
        $authDb = getenv('MONGO_AUTHDB') ?: $dbName;

        // URI avec/sans auth
        $uri = ($user !== '' && $pass !== '')
            ? "mongodb://" . rawurlencode($user) . ":" . rawurlencode($pass) . "@{$host}:{$port}/?authSource={$authDb}"
            : "mongodb://{$host}:{$port}/";

        try {
            self::$client = new Client($uri, [
                'serverSelectionTimeoutMS' => 2000, // évite d’attendre trop
                'connectTimeoutMS'         => 1500,
            ]);

            // Ping rapide pour valider la connexion (optionnel mais utile)
            self::$client->selectDatabase($dbName)->command(['ping' => 1]);

            return self::$client;
        } catch (\Throwable $e) {
            self::$client = null;
            return null;
        }
    }

    /**
     * Retourne la Database (ex: 'ecoride') ou null si indisponible.
     */
    public static function db(): ?Database
    {
        if (self::$db instanceof Database) {
            return self::$db;
        }
        $client = self::client();
        if (!$client) return null;

        $dbName = getenv('MONGO_DB') ?: 'ecoride';
        try {
            self::$db = $client->selectDatabase($dbName);
            return self::$db;
        } catch (\Throwable $e) {
            self::$db = null;
            return null;
        }
    }

    /**
     * Raccourci pratique : retourne une collection ou null si Mongo indisponible.
     * Exemple : $coll = Mongo::collection('reviews');
     */
    public static function collection(string $name)
    {
        $db = self::db();
        return $db ? $db->selectCollection($name) : null;
    }
}
