<?php
// session.php: starts PHP's "session" so the server can remember you between pages.

$thirtyDays = 60 * 60 * 24 * 30;

// Is this request using https? (Azure tells us through a header.)
$isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

ini_set('session.gc_maxlifetime', $thirtyDays);  // keep session data for 30 days
session_set_cookie_params([
    'lifetime' => $thirtyDays,  // the browser keeps the cookie for 30 days
    'path'     => '/',
    'httponly' => true,         // JavaScript can't read it
    'secure'   => $isHttps,     // only sent over https (when we have https)
    'samesite' => 'Lax',        // other websites can't submit forms as you
]);
session_start();