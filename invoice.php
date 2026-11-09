<?php
require 'auth.php';  // make sure the user is logged in
require 'db.php';

$myName = 'Connor';   // who the invoice is from

$invoiceId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT invoices.*, clients.name AS client_name
    FROM invoices
    JOIN clients ON clients.id = invoices.client_id
    WHERE invoices.id = ?
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('No such invoice. <a href="index.php">Back</a>');
}

$stmt = $pdo->prepare("
    SELECT trip_date, description, amount
    FROM trips
    WHERE invoice_id = ?
    ORDER BY trip_date, id
");
$stmt->execute([$invoiceId]);
$trips = $stmt->fetchAll();

// 7 -> "INV-0007"
$number = sprintf('INV-%04d', $invoice['id']);

// "2026-11-09" -> "9 Nov 2026"
function niceDate(string $date): string {
    return date('j M Y', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $number ?> for <?= htmlspecialchars($invoice['client_name']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="page">
    <!-- Everything with class "no-print" is hidden when you print or save as PDF -->
    <div class="toolbar no-print">
        <a class="back-link" href="index.php">&larr; Dashboard</a>
        <button class="secondary" onclick="window.print()">Print or save as PDF</button>
        <form method="post" action="mark_paid.php">
            <input type="hidden" name="id" value="<?= $invoice['id'] ?>">
            <?php if ($invoice['paid_at'] === null): ?>
                <input type="hidden" name="paid" value="1">
                <button type="submit">Mark as paid</button>
            <?php else: ?>
                <input type="hidden" name="paid" value="0">
                <button type="submit" class="secondary">Mark as unpaid</button>
            <?php endif; ?>
        </form>
    </div>

    <article class="card invoice">
        <div class="invoice-top">
            <div>
                <h1>INVOICE</h1>
                <p class="muted"><?= $number ?></p>
                <?php if ($invoice['paid_at'] === null): ?>
                    <span class="status unpaid">UNPAID</span>
                <?php else: ?>
                    <span class="status paid">PAID <?= niceDate($invoice['paid_at']) ?></span>
                <?php endif; ?>
            </div>
            <div class="right">
                <p><strong>From:</strong> <?= htmlspecialchars($myName) ?><br>
                   <strong>To:</strong> <?= htmlspecialchars($invoice['client_name']) ?></p>
                <p class="muted">Issued <?= niceDate($invoice['created_at']) ?></p>
            </div>
        </div>

        <p>Trips from <strong><?= niceDate($invoice['period_start']) ?></strong>
           to <strong><?= niceDate($invoice['period_end']) ?></strong></p>

        <table>
            <tr><th>Date</th><th>Trip</th><th class="amount">Amount</th></tr>
            <?php foreach ($trips as $trip): ?>
                <tr>
                    <td><?= niceDate($trip['trip_date']) ?></td>
                    <td><?= htmlspecialchars($trip['description']) ?></td>
                    <td class="amount">R<?= number_format($trip['amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total">
                <td colspan="2"><?= count($trips) ?> trip<?= count($trips) === 1 ? '' : 's' ?></td>
                <td class="amount">R<?= number_format($invoice['total'], 2) ?></td>
            </tr>
        </table>
    </article>
</main>
</body>
</html>
