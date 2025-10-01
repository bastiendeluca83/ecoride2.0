<?php
/* config/app.php
   Mon fichier central de configuration.
   Principe : je lis d’abord les variables d’environnement (Docker/Heroku/.env).
   Si une variable n’est pas définie, j’utilise un fallback raisonnable pour le dev local.
*/
return [
  /* --- Base de données SQL (MySQL/MariaDB) ---
     Je récupère host/name/user/pass depuis l’environnement quand disponible.
     Charset utf8mb4 pour supporter correctement les emojis et caractères spéciaux. */
  'db' => [
    'host' => getenv('DB_HOST') ?: 'db',              // En Docker, le service s’appelle souvent "db"
    'name' => getenv('DB_NAME') ?: 'ecoride',         // Nom BDD par défaut en local
    'user' => getenv('DB_USER') ?: 'ecoride_user',    // User local de dev
    'pass' => getenv('DB_PASS') ?: 'ecoride_password',// Mot de passe local de dev
    'charset' => 'utf8mb4',
  ],

  /* --- MongoDB (optionnel) ---
     Je garde une config prête si je veux brancher Mongo pour de l’analytique/logs.
     En docker-compose, le service s’appelle généralement "mongo" et écoute 27017. */
  'mongo' => [
    /* compose exposes MONGO_HOST=mongo; default port 27017 */
    'host' => getenv('MONGO_HOST') ?: 'mongo',
    'port' => '27017',
    'db'   => 'ecoride',
  ],

  /* --- Paramètres applicatifs ---
     base_url : je l’utilise pour générer mes URLs (liens, assets, redirections).
     En prod derrière un reverse proxy, je pourrai le surcharger dans l’environnement. */
  'app' => [
    'base_url' => '/', // Exemple : '/','/ecoride/','https://app.ecoride.fr/'
  ],

  /* --- SMTP / Envoi d’emails ---
     Je branche un SMTP (par défaut Gmail) via variables d’environnement.
     - encryption: 'tls' => STARTTLS, 'ssl' => SMTPS, '' => pas de chiffrement (éviter en prod).
     - debug: 0 en prod ; 2 pour logs verbeux (debug) dans error_log. */
  'mail' => [
    'host'       => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port'       => (int)(getenv('MAIL_PORT') ?: 587),
    'username'   => getenv('MAIL_USERNAME') ?: '',        // Je ne hardcode jamais mes secrets
    'password'   => getenv('MAIL_PASSWORD') ?: '',        // à renseigner via env/secret manager
    'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@ecoride.fr',
    'from_name'  => getenv('MAIL_FROM_NAME') ?: 'EcoRide',
    /* 'tls' | 'ssl' | ''  (si 'tls' => STARTTLS, si 'ssl' => SMTPS) */
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    /* 0 = prod ; 2 = debug verbeux dans error_log */
    'debug'      => (int)(getenv('MAIL_DEBUG') ?: 0),
  ],
];
