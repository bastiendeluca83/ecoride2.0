<?php
declare(strict_types=1);

namespace App\Db;

use PDO;
use PDOException;

final class Sql
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            self::$pdo = self::connect();
        } else {
            try { self::$pdo->query('SELECT 1'); }
            catch (\Throwable $e) { self::$pdo = self::connect(); }
        }
        return self::$pdo;
    }

    private static function connect(): PDO
    {
        // 1) URLs Heroku (JawsDB / ClearDB / DATABASE_URL)
        $url = getenv('JAWSDB_URL') ?: getenv('CLEARDB_DATABASE_URL') ?: getenv('DATABASE_URL');
        if ($url) {
            [$dsn, $user, $pass] = self::fromUrl($url);
            return self::makePdo($dsn, $user, $pass);
        }

        // 2) Variables d’env classiques
        $host    = getenv('DB_HOST') ?: '127.0.0.1';
        $db      = getenv('DB_NAME') ?: 'ecoride';
        $user    = getenv('DB_USER') ?: 'root';
        $pass    = getenv('DB_PASS') ?: '';
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
        $dsn     = "mysql:host={$host};dbname={$db};charset={$charset}";

        // 3) Fallback config/app.php si présent
        $confFile = dirname(__DIR__, 2) . '/config/app.php';
        if (is_file($confFile)) {
            $conf = require $confFile;
            if (!empty($conf['db']['host']) && !empty($conf['db']['name'])) {
                $host    = $conf['db']['host'];
                $db      = $conf['db']['name'];
                $user    = $conf['db']['user'] ?? $user;
                $pass    = $conf['db']['pass'] ?? $pass;
                $charset = $conf['db']['charset'] ?? $charset;
                $dsn     = "mysql:host={$host};dbname={$db};charset={$charset}";
            }
        }

        return self::makePdo($dsn, $user, $pass);
    }

    private static function fromUrl(string $url): array
    {
        // Support mysql://user:pass@host:port/db?...
        $p = parse_url($url);
        $scheme = isset($p['scheme']) ? strtolower($p['scheme']) : '';
        if ($scheme !== 'mysql') {
            // Heroku fournit parfois DATABASE_URL en postgres : on refuse proprement
            throw new \RuntimeException('DATABASE_URL not MySQL.');
        }
        $host = $p['host'] ?? '127.0.0.1';
        $user = $p['user'] ?? 'root';
        $pass = $p['pass'] ?? '';
        $db   = ltrim($p['path'] ?? '/ecoride', '/');
        $port = isset($p['port']) ? (int)$p['port'] : 3306;

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        return [$dsn, $user, $pass];
    }

    private static function makePdo(string $dsn, string $user, string $pass): PDO
    {
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $opt);
            // Optionnel : timezone
            $tz = getenv('DB_TIMEZONE') ?: 'Europe/Paris';
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET time_zone = '" . addslashes($tz) . "'");
            return $pdo;
        } catch (PDOException $e) {
            // Log interne : erreur connexion (Heroku logs)
            error_log('[DB] '.$e->getMessage());
            http_response_code(500);
            exit('DB connection failed.');
        }
    }
}
