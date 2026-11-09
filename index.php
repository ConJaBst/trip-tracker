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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connor's Trip tracker</title>
    <link rel="stylesheet" href="style.css">
    <script src="app.js" defer></script>
</head>
<body>
<main class="page">
    <header class="site-header">
        <h1>Trip tracker</h1>
        <nav class="nav">
            <a href="bookmarks.php">Manage bookmarks</a>
            <a href="new_invoice.php">Create an invoice</a>
            <a href="invoices.php">Past invoices</a>
            <a href="logout.php">Log out</a>
        </nav>
    </header>

    <p id="message" class="notice" <?= $message === '' ? 'hidden' : '' ?>>
        <?= htmlspecialchars($message) ?>
    </p>

    <section class="card">
        <h2>What everyone owes</h2>
        <div class="table-wrap">
            <table>
                <tr><th>Person</th><th>Trips</th><th class="amount">Owed</th></tr>
                <?php foreach ($clients as $client): ?>
                    <tr data-client-id="<?= $client['id'] ?>">
                        <td><a href="trips.php?client=<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></a></td>
                        <td class="count"><?= $client['trip_count'] ?></td>
                        <td class="owed amount">R<?= number_format($client['total_owed'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Quick log</h2>
        <?php foreach ($bookmarks as $bookmark): ?>
            <form class="trip-form quick-row" method="post" action="add_trip.php">
                <input type="hidden" name="bookmark_id" value="<?= $bookmark['id'] ?>">
                <input type="hidden" name="trip_date" value="<?= $today ?>">
                <strong class="name">
                    <?= htmlspecialchars($bookmark['name']) ?>
                    <span class="price">R<?= number_format($bookmark['amount'], 2) ?></span>
                </strong>
                <select name="client_id" aria-label="Who is it for">
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
    </section>

    <section class="card">
        <h2>One-time trip</h2>
        <form class="trip-form stack" method="post" action="add_trip.php">
            <label>What
                <input type="text" name="description" required maxlength="255">
            </label>
            <label>Amount
                <span class="money"><span>R</span><input type="number" name="amount" required min="0.01" step="0.01"></span>
            </label>
            <label>For
                <select name="client_id">
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Date
                <input type="date" name="trip_date" value="<?= $today ?>" required>
            </label>
            <div><button type="submit">Add trip</button></div>
        </form>
    </section>
</main>
</body>
</html>
