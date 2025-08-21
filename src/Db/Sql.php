<?php
namespace App\Db;

use PDO;
use PDOException;

class Sql {
  private static ?PDO $pdo = null;

  public static function pdo(): PDO {
    if (self::$pdo === null) {
      $config = require __DIR__ . '/../../config/app.php';
      $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['name'],
        $config['db']['charset']
      );
      try {
        self::$pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
      } catch (PDOException $e) {
        http_response_code(500);
        exit('DB connection failed: ' . $e->getMessage());
      }
    }
    return self::$pdo;
  }
}
