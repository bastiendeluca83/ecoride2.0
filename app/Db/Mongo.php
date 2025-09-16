<?php
declare(strict_types=1);

namespace App\Db;

use MongoDB\Client;
use MongoDB\Database;

final class Mongo
{
    private static ?Database $db = null;

    public static function db(): ?Database
    {
        if (self::$db) return self::$db;

        $uri  = getenv('MONGO_URI') ?: 'mongodb://localhost:27017';
        $name = getenv('MONGO_DB')  ?: 'ecoride';

        try {
            $client = new Client($uri, ['serverSelectionTimeoutMS' => 2000]);
            /* Ping pour s'assurer que le serveur répond */
            $client->selectDatabase('admin')->command(['ping' => 1]);
            self::$db = $client->selectDatabase($name);
            return self::$db;
        } catch (\Throwable $e) {
            error_log('[Mongo] connection failed: '.$e->getMessage());
            return null;
        }
    }
}
