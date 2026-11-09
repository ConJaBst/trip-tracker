<?php
// cron_invoices.php: called by GitHub Actions every Sunday evening.
// No login here (a robot can't log in). It checks a secret key instead.
require 'db.php';
require 'invoice_functions.php';

header('Content-Type: application/json');

// 1. Only a POST with the right secret key gets in.
$expectedKey = $config['cron_key'];
$givenKey    = $_SERVER['HTTP_X_CRON_KEY'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $expectedKey === '' || !hash_equals($expectedKey, $givenKey)) {
    http_response_code(403);   // 403 means "not allowed"
    echo json_encode(['ok' => false, 'error' => 'Not allowed.']);
    exit;
}

// 2. One invoice per person who has new, unbilled trips up to today.
$today   = date('Y-m-d');
$created = [];

$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

foreach ($clients as $client) {
    // Their oldest unbilled trip (null if they have none)
    $stmt = $pdo->prepare("
        SELECT MIN(trip_date) FROM trips
        WHERE client_id = ? AND invoice_id IS NULL AND trip_date <= ?
    ");
    $stmt->execute([$client['id'], $today]);
    $oldest = $stmt->fetchColumn();

    if ($oldest === null) {
        continue;   // nothing new for this person, so skip them
    }

    $invoiceId = createInvoice($pdo, (int) $client['id'], $oldest, $today);

    if ($invoiceId !== null) {
        $created[] = ['client' => $client['name'], 'invoice' => sprintf('INV-%04d', $invoiceId)];
    }
}

// 3. Report back (GitHub shows this in the run's log)
echo json_encode(['ok' => true, 'created' => $created]);