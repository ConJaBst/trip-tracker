<?php
require 'auth.php';  // make sure the user is logged in
require 'db.php';
require 'totals.php';

$clients   = getTotals($pdo);
$bookmarks = $pdo->query("SELECT id, name, amount, default_client_id FROM bookmarks ORDER BY name")->fetchAll();

$today   = date('Y-m-d');
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Trip tracker</title>
    <script src="app.js" defer></script>
</head>
<body>
    <h1>Trip tracker</h1>
   <p>
    <a href="bookmarks.php">Manage bookmarks</a> ·
    <a href="new_invoice.php">Create an invoice</a> ·
    <a href="invoices.php">Past invoices</a> ·
    <a href="logout.php">Log out</a>
</p>
    <p id="message" style="background:#e6ffe6; padding:8px;" <?= $message === '' ? 'hidden' : '' ?>>
        <?= htmlspecialchars($message) ?>
    </p>

    <h2>What everyone owes</h2>
    <table border="1" cellpadding="6">
        <tr><th>Person</th><th>Trips</th><th>Owed</th></tr>
        <?php foreach ($clients as $client): ?>
            <tr data-client-id="<?= $client['id'] ?>">
                <td><a href="trips.php?client=<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></a></td>
                <td class="count"><?= $client['trip_count'] ?></td>
                <td class="owed">R<?= number_format($client['total_owed'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Quick log</h2>
    <?php foreach ($bookmarks as $bookmark): ?>
        <form class="trip-form" method="post" action="add_trip.php">
            <input type="hidden" name="bookmark_id" value="<?= $bookmark['id'] ?>">
            <input type="hidden" name="trip_date" value="<?= $today ?>">
            <strong><?= htmlspecialchars($bookmark['name']) ?></strong>
            (R<?= number_format($bookmark['amount'], 2) ?>) for
            <select name="client_id">
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['id'] ?>"
                        <?= $client['id'] == $bookmark['default_client_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Log it</button>
        </form>
    <?php endforeach; ?>

    <h2>One-time trip</h2>
    <form class="trip-form" method="post" action="add_trip.php">
        <p>
            <label>What: <input type="text" name="description" required maxlength="255"></label>
        </p>
        <p>
            <label>Amount: R<input type="number" name="amount" required min="0.01" step="0.01"></label>
        </p>
        <p>
            <label>For:
                <select name="client_id">
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <p>
            <label>Date: <input type="date" name="trip_date" value="<?= $today ?>" required></label>
        </p>
        <button type="submit">Add trip</button>
    </form>
</body>
</html>