<?php
require 'auth.php';  // make sure the user is logged in
require 'db.php';

$invoices = $pdo->query("
    SELECT invoices.id, invoices.period_start, invoices.period_end, invoices.total, invoices.paid_at,
           clients.name AS client_name, COUNT(trips.id) AS trip_count
    FROM invoices
    JOIN clients ON clients.id = invoices.client_id
    LEFT JOIN trips ON trips.invoice_id = invoices.id
    GROUP BY invoices.id, invoices.period_start, invoices.period_end, invoices.total, invoices.paid_at, clients.name
    ORDER BY invoices.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Past invoices</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="page">
    <a class="back-link" href="index.php">&larr; Back</a>
    <h1>Past invoices</h1>

    <section class="card">
        <?php if (count($invoices) === 0): ?>
            <p>No invoices yet. <a href="new_invoice.php">Create one</a>.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <tr><th>Invoice</th><th>For</th><th>Trips from</th><th>To</th><th>Trips</th><th class="amount">Total</th><th>Status</th></tr>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><a href="invoice.php?id=<?= $invoice['id'] ?>"><?= sprintf('INV-%04d', $invoice['id']) ?></a></td>
                            <td><?= htmlspecialchars($invoice['client_name']) ?></td>
                            <td><?= htmlspecialchars($invoice['period_start']) ?></td>
                            <td><?= htmlspecialchars($invoice['period_end']) ?></td>
                            <td><?= $invoice['trip_count'] ?></td>
                            <td class="amount">R<?= number_format($invoice['total'], 2) ?></td>
                            <td>
                                <?php if ($invoice['paid_at'] === null): ?>
                                    <span class="status unpaid">Unpaid</span>
                                <?php else: ?>
                                    <span class="status paid">Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
