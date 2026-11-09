<?php
// auth.php: the bouncer. Put  require 'auth.php';  at the top of every private page.

require_once __DIR__ . '/session.php';

if (empty($_SESSION['logged_in'])) {
    // JavaScript asked (it wants JSON), so answer in JSON
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        http_response_code(401);  // 401 means "you need to log in"
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'You were logged out. Refresh the page to log in again.']);
        exit;
    }
    // A normal page visit: send them to the login page
    header('Location: login.php');
    exit;
}