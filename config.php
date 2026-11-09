<?php
// config.php: every setting the app needs, in one place.
// On Azure, the values come from "App settings" (environment variables).
// On your PC, they come from config.local.php, which never goes to GitHub.

$config = [
    'db_host'       => getenv('DB_HOST') ?: 'localhost',
    'db_name'       => getenv('DB_NAME') ?: 'trip_app',
    'db_user'       => getenv('DB_USER') ?: 'root',
    'db_pass'       => getenv('DB_PASS') ?: '',
    'db_ssl_ca'     => getenv('DB_SSL_CA') ?: '',          // only needed on Azure
    'password_hash' => getenv('APP_PASSWORD_HASH') ?: '',  // the login password, scrambled
    'cron_key'      => getenv('CRON_KEY') ?: '',           // the weekly job's secret key
];

// If config.local.php exists, its values replace the ones above.
if (file_exists(__DIR__ . '/config.local.php')) {
    $config = array_merge($config, require __DIR__ . '/config.local.php');
}

// Use South African time for every date() call, on any computer.
date_default_timezone_set('Africa/Johannesburg');