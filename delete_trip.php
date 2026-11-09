<?php
// delete_trip.php: removes one trip (used by the Undo button). Always replies with JSON.
require 'auth.php';  // make sure the user is logged in
require 'db.php';
require 'totals.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);   // 405 = "method not allowed"
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}

$tripId = (int) ($_POST['trip_id'] ?? 0);

// Only delete trips that haven't been invoiced. Billed trips must never disappear.
$stmt = $pdo->prepare("DELETE FROM trips WHERE id = ? AND invoice_id IS NULL");
$stmt->execute([$tripId]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);   // 404 = "not found"
    echo json_encode(['ok' => false, 'error' => 'That trip is already gone or has been invoiced.']);
    exit;
}

echo json_encode(['ok' => true, 'totals' => getTotals($pdo)]);