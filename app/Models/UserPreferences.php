<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\Sql;
use PDO;

final class UserPreferences
{
    private static function pdo(): PDO { return Sql::pdo(); }

    public static function get(int $userId): array {
        $st = self::pdo()->prepare("SELECT * FROM user_preferences WHERE user_id=:u");
        $st->execute([':u'=>$userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [
            'user_id'=>$userId,
            'smoker'=>0,
            'animals'=>0,
            'music'=>1,
            'chatty'=>1,
            'ac'=>1,
            'custom_prefs'=>null
        ];
    }

    public static function exists(int $userId): bool {
        $st = self::pdo()->prepare("SELECT 1 FROM user_preferences WHERE user_id=:u LIMIT 1");
        $st->execute([':u'=>$userId]);
        return (bool)$st->fetchColumn();
    }

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
        return $st->execute([
            ':u'=>$userId,
            ':s'=>(int)($d['smoker'] ?? 0),
            ':a'=>(int)($d['animals'] ?? 0),
            ':m'=>(int)($d['music'] ?? 1),
            ':c'=>(int)($d['chatty'] ?? 1),
            ':ac'=>(int)($d['ac'] ?? 1),
            ':cp'=>($d['custom_prefs'] ?? null),
        ]);
    }

    /* Aliases conviviaux */
    public static function save(int $userId, array $d): bool { return self::upsert($userId, $d); }
    public static function saveForUser(int $userId, array $d): bool { return self::upsert($userId, $d); }
    public static function set(int $userId, array $d): bool { return self::upsert($userId, $d); }
    public static function updateForUser(int $userId, array $d): bool { return self::upsert($userId, $d); }
}
