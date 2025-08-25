<?php
namespace App\Db;

use MongoDB\Client;
use MongoDB\Database;

final class Mongo
{
    private static ?Client $client = null;
    private static ?Database $db   = null;

    public static function client(): ?Client
    {
        if (self::$client instanceof Client) return self::$client;

        // 1) Si MONGO_URI est fourni on le privilégie (ex: mongodb://user:pwd@mongo:27017/ecoride?authSource=admin)
        $uri = getenv('MONGO_URI') ?: '';

        if ($uri === '') {
            // 2) Sinon on compose à partir de host/port + user/pass éventuels
            $host   = getenv('MONGO_HOST') ?: 'mongo';
            $port   = (string)(getenv('MONGO_PORT') ?: '27017');
            $dbName = getenv('MONGO_DB')   ?: 'ecoride';
            $user   = getenv('MONGO_USER') ?: '';
            $pass   = getenv('MONGO_PASS') ?: '';
            $authDb = getenv('MONGO_AUTHDB') ?: ($user && $pass ? 'admin' : $dbName);

            $uri = ($user !== '' && $pass !== '')
                ? "mongodb://".rawurlencode($user).":".rawurlencode($pass)."@{$host}:{$port}/{$dbName}?authSource={$authDb}"
                : "mongodb://{$host}:{$port}/{$dbName}";
        }

        try {
            self::$client = new Client($uri, [
                'serverSelectionTimeoutMS' => 2000,
                'connectTimeoutMS'         => 1500,
            ]);
            // petit ping pour valider
            self::$client->selectDatabase(self::extractDbName($uri))->command(['ping' => 1]);
            return self::$client;
        } catch (\Throwable $e) {
            // error_log('[Mongo] '.$e->getMessage());
            self::$client = null;
            return null;
        }
    }

    public static function db(): ?Database
    {
        if (self::$db instanceof Database) return self::$db;
        $client = self::client();
        if (!$client) return null;

        $dbName = getenv('MONGO_DB') ?: self::extractDbName(getenv('MONGO_URI') ?: '');
        if ($dbName === '') $dbName = 'ecoride';

        try {
            self::$db = $client->selectDatabase($dbName);
            return self::$db;
        } catch (\Throwable $e) {
            // error_log('[Mongo] db() '.$e->getMessage());
            self::$db = null;
            return null;
        }
    }

    public static function collection(string $name)
    {
        $db = self::db();
        return $db ? $db->selectCollection($name) : null;
    }

    /** Récupère le nom de DB dans une URI mongodb://.../<db>?... */
    private static function extractDbName(string $uri): string
    {
        if ($uri === '') return '';
        $p = parse_url($uri);
        if (!empty($p['path'])) return ltrim($p['path'], '/');
        return '';
    }
}
