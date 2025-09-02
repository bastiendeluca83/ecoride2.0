<?php
namespace App\Models;


use App\Db\Sql;
use PDO;


abstract class BaseModels
{
protected static function pdo(): PDO { return Sql::pdo(); }


protected static function all(string $sql, array $params = []): array {
$st = self::pdo()->prepare($sql);
$st->execute($params);
return $st->fetchAll(PDO::FETCH_ASSOC);
}


protected static function one(string $sql, array $params = []): ?array {
$st = self::pdo()->prepare($sql);
$st->execute($params);
$row = $st->fetch(PDO::FETCH_ASSOC);
return $row ?: null;
}
}
