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
    'port' => 27017,
    'db'   => 'ecoride',
  ],
  'app' => [
    'base_url' => '/',
  ],
];
