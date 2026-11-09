<?php
require 'auth.php';  // make sure the user is logged in
require 'db.php';

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
$tripFilter = "
    client_id = :client
    AND (
        (invoice_id IS NULL AND trip_date BETWEEN :from AND :to)
        OR invoice_id IN (SELECT id FROM invoices WHERE client_id = :client2 AND paid_at IS NULL)
    )
";
$filterValues = ['client' => $clientId, 'client2' => $clientId, 'from' => $from, 'to' => $to];

// --- Create the invoice ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client && $error === '') {
    $pdo->beginTransaction();
    try {
        // 1. Remember which unpaid invoices we're replacing.
        $stmt = $pdo->prepare("SELECT id FROM invoices WHERE client_id = ? AND paid_at IS NULL");
        $stmt->execute([$clientId]);
        $oldInvoiceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Make the new invoice (total filled in at step 5).
        $stmt = $pdo->prepare("
            INSERT INTO invoices (client_id, period_start, period_end, total)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$clientId, $from, $to]);
        $invoiceId = (int) $pdo->lastInsertId();

        // 3. Move the trips onto it: new ones in the range, and everything from unpaid invoices.
        $stmt = $pdo->prepare("UPDATE trips SET invoice_id = :invoice WHERE $tripFilter");
        $stmt->execute(['invoice' => $invoiceId] + $filterValues);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            $error = 'There are no trips to bill for that person in those dates.';
        } else {
            // 4. The old unpaid invoices are now empty, so delete them.
            if (count($oldInvoiceIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($oldInvoiceIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM invoices WHERE id IN ($placeholders) AND paid_at IS NULL");
                $stmt->execute($oldInvoiceIds);
            }

            // 5. Total, and stretch the start date back if carried-over trips are older.
            $stmt = $pdo->prepare("SELECT SUM(amount) AS total, MIN(trip_date) AS first_trip FROM trips WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $sums = $stmt->fetch();
            $start = min($from, $sums['first_trip']);   // dates as YYYY-MM-DD compare correctly as text

            $stmt = $pdo->prepare("UPDATE invoices SET total = ?, period_start = ? WHERE id = ?");
            $stmt->execute([$sums['total'], $start, $invoiceId]);

            $pdo->commit();
            header("Location: invoice.php?id=$invoiceId");
            exit;
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
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
<html>
<head>
    <meta charset="utf-8">
    <title>New invoice</title>
</head>
<body>
    <p><a href="index.php">&larr; Back</a></p>
    <h1>New invoice</h1>

    <?php if ($error !== ''): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="get">
        <label>For:
            <select name="client">
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $clientId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>From: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" required></label>
        <label>To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" required></label>
        <button type="submit">Show trips</button>
    </form>

    <?php if ($client && $error === ''): ?>
        <h2>Trips to bill <?= htmlspecialchars($client['name']) ?></h2>

        <?php if ($olderCount > 0): ?>
            <p style="background:#fff3cd; padding:8px;">
                <?= htmlspecialchars($client['name']) ?> also has <?= $olderCount ?> unbilled
                trip<?= $olderCount === 1 ? '' : 's' ?> from before <?= htmlspecialchars($from) ?>.
                Move the "from" date back if they belong on this invoice.
            </p>
        <?php endif; ?>

        <?php if (count($trips) === 0): ?>
            <p>No trips to bill in these dates.</p>
        <?php else: ?>
            <table border="1" cellpadding="6">
                <tr><th>Date</th><th>Trip</th><th>Amount</th><th></th></tr>
                <?php foreach ($trips as $trip): ?>
                    <tr>
                        <td><?= htmlspecialchars($trip['trip_date']) ?></td>
                        <td><?= htmlspecialchars($trip['description']) ?></td>
                        <td>R<?= number_format($trip['amount'], 2) ?></td>
                        <td>
                            <?php if ($trip['invoice_id'] !== null): ?>
                                Carried over from unpaid <?= sprintf('INV-%04d', $trip['invoice_id']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr><th colspan="2">Total</th><th>R<?= number_format($total, 2) ?></th><th></th></tr>
            </table>

            <form method="post">
                <input type="hidden" name="client" value="<?= $clientId ?>">
                <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                <p><button type="submit">Create invoice for R<?= number_format($total, 2) ?></button></p>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>