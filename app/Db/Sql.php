<?php
declare(strict_types=1);

namespace App\Db;

use PDO;
use PDOException;

/**
 * Sql
 * - Fournit une instance PDO unique (lazy singleton).
 * - Priorités de configuration :
 *   1) JAWSDB_URL (Heroku JawsDB)
 *   2) CLEARDB_DATABASE_URL (Heroku ClearDB)
 *   3) DATABASE_URL de type mysql://user:pass@host/db (fallback générique)
 *   4) Variables d'env classiques (DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET)
 *   5) config/app.php (legacy local)
 *
 * - Par défaut, charset utf8mb4 et timezone Europe/Paris.
 */
final class Sql
{
    private static ?PDO $pdo = null;

    /**
     * Retourne une instance PDO prête à l'emploi (et reconnecte si besoin).
     */
    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            self::$pdo = self::connect();
        } else {
            // Ping léger : si la connexion a été coupée par le provider, on reconnecte
            try {
                self::$pdo->query('SELECT 1');
            } catch (\Throwable $e) {
                self::$pdo = self::connect();
            }
        }
        return self::$pdo;
    }

    /**
     * Etablit une nouvelle connexion PDO en fonction de l'environnement.
     */
    private static function connect(): PDO
    {
        // 1) Heroku (JawsDB / ClearDB / DATABASE_URL)
        $url = getenv('JAWSDB_URL') ?: (getenv('CLEARDB_DATABASE_URL') ?: getenv('DATABASE_URL'));

        if ($url && str_starts_with($url, 'mysql://')) {
            [$dsn, $user, $pass] = self::fromUrl($url);
            return self::makePdo($dsn, $user, $pass);
        }

        // 2) Variables d'env classiques (Docker / .env)
        $host    = getenv('DB_HOST') ?: '127.0.0.1';
        $db      = getenv('DB_NAME') ?: 'ecoride';
        $user    = getenv('DB_USER') ?: 'root';
        $pass    = getenv('DB_PASS') ?: '';
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
        $dsn     = "mysql:host={$host};dbname={$db};charset={$charset}";

        // 3) Fallback fichier de config local (optionnel)
        $confFile = dirname(__DIR__, 2) . '/config/app.php';
        if (is_file($confFile)) {
            $conf = require $confFile;
            if (isset($conf['db']['host'], $conf['db']['name'])) {
                $host    = $conf['db']['host']    ?: $host;
                $db      = $conf['db']['name']    ?: $db;
                $user    = $conf['db']['user']    ?? $user;
                $pass    = $conf['db']['pass']    ?? $pass;
                $charset = $conf['db']['charset'] ?? $charset;
                $dsn     = "mysql:host={$host};dbname={$db};charset={$charset}";
            }
        }

        return self::makePdo($dsn, $user, $pass);
    }

    /**
     * Construit DSN, user, pass à partir d'une URL mysql://user:pass@host/db?...
     */
    private static function fromUrl(string $url): array
    {
        $parts = parse_url($url);
        $host  = $parts['host'] ?? '127.0.0.1';
        $user  = $parts['user'] ?? 'root';
        $pass  = $parts['pass'] ?? '';
        $db    = ltrim($parts['path'] ?? '/ecoride', '/');
        $port  = isset($parts['port']) ? (int)$parts['port'] : 3306;

        // utf8mb4 par défaut
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        return [$dsn, $user, $pass];
    }

    /**
     * Crée l'instance PDO avec des options sécurisées et cohérentes.
     */
    private static function makePdo(string $dsn, string $user, string $pass): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // vrais prepared statements
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);

            // Paramétrage session SQL : charset/collo, timezone (optionnel)
            $tz = getenv('DB_TIMEZONE') ?: 'Europe/Paris';
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET time_zone = '" . addslashes(self::mysqlTz($tz)) . "'");

            return $pdo;
        } catch (PDOException $e) {
            http_response_code(500);
            // En prod, évite d'afficher le détail ; les logs applicatifs captureront le message.
            exit('DB connection failed.');
        }
    }

    /**
     * Convertit un identifiant de timezone PHP en timezone MySQL utilisable.
     * (MySQL accepte généralement la même valeur ; on garde la méthode au cas où)
     */
    private static function mysqlTz(string $tz): string
    {
        return $tz; // simple mapping par défaut
    }
}
