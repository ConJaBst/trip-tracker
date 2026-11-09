<?php
require 'auth.php';  // make sure the user is logged in
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $amount = $_POST['amount'] ?? '';
    $defaultClientId = (int) ($_POST['default_client_id'] ?? 0);

    if ($name === '' || mb_strlen($name) > 100) {
        $error = 'Please give the bookmark a name (up to 100 characters).';
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = 'The amount must be a number above 0.';
    } else {
        // 0 means "No default", which we store as NULL
        $stmt = $pdo->prepare("INSERT INTO bookmarks (name, amount, default_client_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, round((float) $amount, 2), $defaultClientId ?: null]);
        header('Location: bookmarks.php');
        exit;
    }
}

$clients   = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();
$bookmarks = $pdo->query("
    SELECT bookmarks.name, bookmarks.amount, clients.name AS default_client
    FROM bookmarks
    LEFT JOIN clients ON clients.id = bookmarks.default_client_id
    ORDER BY bookmarks.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bookmarks</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="page">
    <a class="back-link" href="index.php">&larr; Back</a>
    <h1>Bookmarks</h1>
    <p class="muted">Trips you log often, ready to add with one click.</p>

    <section class="card">
        <div class="table-wrap">
            <table>
                <tr><th>Name</th><th class="amount">Amount</th><th>Usually for</th></tr>
                <?php foreach ($bookmarks as $bookmark): ?>
                    <tr>
                        <td><?= htmlspecialchars($bookmark['name']) ?></td>
                        <td class="amount">R<?= number_format($bookmark['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($bookmark['default_client'] ?? 'Ask each time') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Add a bookmark</h2>
        <?php if ($error !== ''): ?>
            <p class="notice error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post" class="stack">
            <label>Name
                <input type="text" name="name" required maxlength="100">
            </label>
            <label>Amount
                <span class="money"><span>R</span><input type="number" name="amount" required min="0.01" step="0.01"></span>
            </label>
            <label>Usually for
                <select name="default_client_id">
                    <option value="0">Ask each time</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div><button type="submit">Save bookmark</button></div>
        </form>
    </section>
</main>
</body>
</html>
