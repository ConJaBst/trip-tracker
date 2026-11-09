<?php   
require 'auth.php';  // make sure the user is logged in
require 'db.php';   // runs db.php, so $pdo now exists here

$client_id = $_GET['client'] ?? 0;

$stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if(!$client) {
    die('Client not found<a href="index.php">Back</a>');
}

$stmt = $pdo->prepare("
    SELECT trip_date, amount, description
    FROM trips
    WHERE client_id = ? AND (invoice_id IS NULL OR invoice_id IN (SELECT id FROM invoices WHERE paid_at IS NULL))
    ORDER BY trip_date DESC, id DESC
");
$stmt->execute([$client_id]);
$trips = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trips for <?= htmlspecialchars($client['name']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="page">
    <a class="back-link" href="index.php">&larr; Back</a>
    <h1>Unpaid trips for <?= htmlspecialchars($client['name']) ?></h1>

    <section class="card">
        <?php if (count($trips) === 0): ?>
            <p class="muted">No unpaid trips.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <tr><th>Date</th><th>Trip</th><th class="amount">Amount</th></tr>
                    <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td><?= $trip['trip_date'] ?></td>
                            <td><?= htmlspecialchars($trip['description']) ?></td>
                            <td class="amount">R<?= number_format($trip['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
