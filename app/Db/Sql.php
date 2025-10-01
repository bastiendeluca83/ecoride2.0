<?php
namespace App\Db;

use PDO;
use PDOException;

/**
 * Sql
 * - Petit wrapper pour exposer une instance PDO unique (lazy singleton).
 * - Je lis la config DB depuis config/app.php.
 * - Je construis le DSN proprement et j'applique quelques options PDO par défaut.
 *
 * Remarque:
 * - En cas d'échec de connexion, je renvoie un 500 et j'arrête l'exécution.
 *   (En prod, on pourra masquer le message exact ou logger côté serveur.)
 */
class Sql {
  /** Instance PDO mise en cache pour éviter de recréer la connexion */
  private static ?PDO $pdo = null;

  /**
   * Retourne l'instance PDO prête à l'emploi.
   * - Initialise la connexion à la première demande.
   * - Réutilise la même instance ensuite.
   */
  public static function pdo(): PDO {
    // Lazy init: si déjà connectée, je renvoie directement l'instance.
    if (self::$pdo === null) {
      // Je récupère la config (host, name, charset, user, pass)
      $config = require __DIR__ . '/../../config/app.php';

      // Je construis le DSN MySQL (charset inclus pour éviter les soucis d'encodage)
      $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['name'],
        $config['db']['charset']
      );

      try {
        // J'instancie PDO avec des options de base:
        // - ERRMODE_EXCEPTION: je préfère gérer les erreurs via exceptions
        // - FETCH_ASSOC par défaut: tableaux associatifs (lisibles en contrôleurs/vues)
        self::$pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          // Astuce (optionnel) : on peut aussi désactiver l'émulation si besoin
          // PDO::ATTR_EMULATE_PREPARES => false,
        ]);
      } catch (PDOException $e) {
        // Échec de connexion → je renvoie un 500 (backend only)
        // (En prod, on évite d'afficher le message exact pour ne rien divulguer)
        http_response_code(500);
        exit('DB connection failed: ' . $e->getMessage());
      }
    }

    // Je renvoie l’instance unique (réutilisable partout)
    return self::$pdo;
  }
}
