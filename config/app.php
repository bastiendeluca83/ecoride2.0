<?php
// config/app.php - extended with DB + Mongo using your docker-compose env var names
return [
  'db' => [
    'host' => getenv('DB_HOST') ?: 'db',
    'name' => getenv('DB_NAME') ?: 'ecoride',
    'user' => getenv('DB_USER') ?: 'ecoride_user',
    'pass' => getenv('DB_PASS') ?: 'ecoride_password',
    'charset' => 'utf8mb4',
  ],
  'mongo' => [
    // compose exposes MONGO_HOST=mongo; default port 27017
    'host' => getenv('MONGO_HOST') ?: 'mongo',
    'port' => '27017',
    'db'   => 'ecoride',
  ],
  'app' => [
    'base_url' => '/',
  ],
    'mail' => [
    'host'       => getenv('MAIL_HOST') ?: 'localhost',
    'port'       => (int)(getenv('MAIL_PORT') ?: 587),
    'username'   => getenv('MAIL_USERNAME') ?: '',
    'password'   => getenv('MAIL_PASSWORD') ?: '',
    'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@ecoride.fr',
    'from_name'  => getenv('MAIL_FROM_NAME') ?: 'EcoRide',
    // 'tls' | 'ssl' | ''  (si 'tls' => STARTTLS, si 'ssl' => SMTPS)
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    // 0 = prod ; 2 = debug verbeux dans error_log
    'debug'      => (int)(getenv('MAIL_DEBUG') ?: 0),
  ],

];
