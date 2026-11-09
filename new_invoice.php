<?php
require 'auth.php';  // make sure the user is logged in
require 'db.php';
require 'invoice_functions.php';  

// Checks text is a real date in the form 2026-11-09
function isValidDate(string $text): bool {
    $date = DateTime::createFromFormat('Y-m-d', $text);
    return $date && $date->format('Y-m-d') === $text;
}

// "Show trips" sends GET (just looking). "Create invoice" sends POST (changes data).
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$clientId = (int) ($input['client'] ?? 0);
$from     = $input['from'] ?? date('Y-m-d', strtotime('monday this week'));
$to       = $input['to']   ?? date('Y-m-d', strtotime('sunday this week'));

$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

$error  = '';
$client = null;

if ($clientId > 0) {
    $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();

    if (!$client) {
        $error = 'Please choose who the invoice is for.';
    } elseif (!isValidDate($from) || !isValidDate($to)) {
        $error = 'Those dates don\'t look right.';
    } elseif ($from > $to) {
        $error = 'The "from" date must be on or before the "to" date.';
    }
}

// Which trips go on the new invoice:
//   - this person's unbilled trips in the date range, PLUS
//   - every trip on this person's UNPAID invoices (carried over, whatever the date).
// The same WHERE is used for the preview and for the real thing, so they always agree.
[$tripFilter, $filterValues] = invoiceTripFilter($clientId, $from, $to);

// --- Create the invoice ---
// --- Create the invoice ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client && $error === '') {
    $invoiceId = createInvoice($pdo, $clientId, $from, $to);

    if ($invoiceId === null) {
        $error = 'There are no trips to bill for that person in those dates.';
    } else {
        header("Location: invoice.php?id=$invoiceId");
        exit;
    }
}

// --- Preview ---
$trips = [];
$total = 0;
$olderCount = 0;

if ($client && $error === '') {
    $stmt = $pdo->prepare("
        SELECT trip_date, description, amount, invoice_id
        FROM trips
        WHERE $tripFilter
        ORDER BY trip_date, id
    ");
    $stmt->execute($filterValues);
    $trips = $stmt->fetchAll();

    foreach ($trips as $trip) {
        $total += $trip['amount'];
    }

    // Unbilled trips from BEFORE the range would be left behind, so warn about them.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM trips
        WHERE client_id = ? AND trip_date < ? AND invoice_id IS NULL
    ");
    $stmt->execute([$clientId, $from]);
    $olderCount = (int) $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New invoice</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="page">
    <a class="back-link" href="index.php">&larr; Back</a>
    <h1>New invoice</h1>

    <?php if ($error !== ''): ?>
        <p class="notice error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <section class="card">
        <form method="get" class="inline">
            <label>For
                <select name="client">
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $clientId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" required></label>
            <label>To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" required></label>
            <button type="submit" class="secondary">Show trips</button>
        </form>
    </section>

    <?php if ($client && $error === ''): ?>
        <section class="card">
            <h2>Trips to bill <?= htmlspecialchars($client['name']) ?></h2>

            <?php if ($olderCount > 0): ?>
                <p class="notice warning">
                    <?= htmlspecialchars($client['name']) ?> also has <?= $olderCount ?> unbilled
                    trip<?= $olderCount === 1 ? '' : 's' ?> from before <?= htmlspecialchars($from) ?>.
                    Move the "from" date back if they belong on this invoice.
                </p>
            <?php endif; ?>

            <?php if (count($trips) === 0): ?>
                <p class="muted">No trips to bill in these dates.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <tr><th>Date</th><th>Trip</th><th class="amount">Amount</th><th></th></tr>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><?= htmlspecialchars($trip['trip_date']) ?></td>
                                <td><?= htmlspecialchars($trip['description']) ?></td>
                                <td class="amount">R<?= number_format($trip['amount'], 2) ?></td>
                                <td class="muted">
                                    <?php if ($trip['invoice_id'] !== null): ?>
                                        Carried over from unpaid <?= sprintf('INV-%04d', $trip['invoice_id']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total"><td colspan="2">Total</td><td class="amount">R<?= number_format($total, 2) ?></td><td></td></tr>
                    </table>
                </div>

                <form method="post">
                    <input type="hidden" name="client" value="<?= $clientId ?>">
                    <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                    <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                    <p><button type="submit">Create invoice for R<?= number_format($total, 2) ?></button></p>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
