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
<html>
<head>
    <meta charset="utf-8">
    <title><?= $number ?> for <?= htmlspecialchars($invoice['client_name']) ?></title>
    <style>
        body    { font-family: Arial, sans-serif; max-width: 700px; margin: 30px auto; padding: 0 16px; color: #222; }
        .top    { display: flex; justify-content: space-between; align-items: flex-start; }
        h1      { margin: 0; font-size: 32px; letter-spacing: 2px; }
        .muted  { color: #666; }
        table   { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td  { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        .amount { text-align: right; }
        .total td { font-weight: bold; font-size: 18px; border-top: 2px solid #222; border-bottom: none; }
        .status { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: bold; }
        .paid   { background: #e6ffe6; color: #1a6b1a; }
        .unpaid { background: #ffe6e6; color: #a11; }

        /* When printing (or saving as PDF), hide the buttons and links */
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:24px;">
        <a href="index.php">&larr; Dashboard</a>
        <button onclick="window.print()">Print or save as PDF</button>
        <form method="post" action="mark_paid.php" style="display:inline;">
            <input type="hidden" name="id" value="<?= $invoice['id'] ?>">
            <?php if ($invoice['paid_at'] === null): ?>
                <input type="hidden" name="paid" value="1">
                <button type="submit">Mark as paid</button>
            <?php else: ?>
                <input type="hidden" name="paid" value="0">
                <button type="submit">Mark as unpaid</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="top">
        <div>
            <h1>INVOICE</h1>
            <p class="muted"><?= $number ?></p>
            <?php if ($invoice['paid_at'] === null): ?>
                <span class="status unpaid">UNPAID</span>
            <?php else: ?>
                <span class="status paid">PAID <?= niceDate($invoice['paid_at']) ?></span>
            <?php endif; ?>
        </div>
        <div style="text-align:right;">
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
</body>
</html>