<?php
require 'auth.php';  // make sure the user is logged in
require 'db.php';
require 'totals.php';

// Did JavaScript send this (wants JSON back), or a normal form (wants a page)?
function wantsJson(): bool {
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

// Stop and explain what went wrong, in whichever format the caller wants.
function fail(string $message): void {
    if (wantsJson()) {
        http_response_code(400);   // 400 = "bad request"
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }
    die(htmlspecialchars($message) . ' <a href="index.php">Back</a>');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// --- 1. Read and check the fields every trip needs ---
$clientId = (int) ($_POST['client_id'] ?? 0);
$tripDate = $_POST['trip_date'] ?? '';

$stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
$client = $stmt->fetch();
if (!$client) {
    fail('Please choose who the trip is for.');
}

$date = DateTime::createFromFormat('Y-m-d', $tripDate);
if (!$date || $date->format('Y-m-d') !== $tripDate) {
    fail('That date doesn\'t look right.');
}

// --- 2. Work out the description and amount ---
$bookmarkId = (int) ($_POST['bookmark_id'] ?? 0);

if ($bookmarkId > 0) {
    $stmt = $pdo->prepare("SELECT name, amount FROM bookmarks WHERE id = ?");
    $stmt->execute([$bookmarkId]);
    $bookmark = $stmt->fetch();
    if (!$bookmark) {
        fail('That bookmark doesn\'t exist any more.');
    }
    $description = $bookmark['name'];
    $amount      = $bookmark['amount'];
} else {
    $bookmarkId  = null;
    $description = trim($_POST['description'] ?? '');
    $amount      = $_POST['amount'] ?? '';

    if ($description === '' || mb_strlen($description) > 255) {
        fail('Please describe the trip (up to 255 characters).');
    }
    if (!is_numeric($amount) || $amount <= 0) {
        fail('The amount must be a number above 0.');
    }
    $amount = round((float) $amount, 2);
}

// --- 3. Save it ---
$stmt = $pdo->prepare("
    INSERT INTO trips (client_id, trip_date, description, amount, bookmark_id)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$clientId, $tripDate, $description, $amount, $bookmarkId]);
$tripId = (int) $pdo->lastInsertId();   // the id MySQL just gave the new trip

$message = "Added: $description, R" . number_format($amount, 2) . " for {$client['name']}";

// --- 4. Reply ---
if (wantsJson()) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'      => true,
        'message' => $message,
        'tripId'  => $tripId,
        'totals'  => getTotals($pdo),
    ]);
    exit;
}

header('Location: index.php?message=' . urlencode($message));
exit;