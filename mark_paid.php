<?php
// mark_paid.php: marks an invoice as paid (paid=1) or back to unpaid (paid=0).
require 'auth.php';  // make sure the user is logged in
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$invoiceId = (int) ($_POST['id'] ?? 0);

if (($_POST['paid'] ?? '') === '1') {
    $stmt = $pdo->prepare("UPDATE invoices SET paid_at = CURRENT_TIMESTAMP WHERE id = ? AND paid_at IS NULL");
} else {
    $stmt = $pdo->prepare("UPDATE invoices SET paid_at = NULL WHERE id = ?");
}
$stmt->execute([$invoiceId]);

header("Location: invoice.php?id=$invoiceId");
exit;