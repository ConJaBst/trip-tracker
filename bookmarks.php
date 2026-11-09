<?php
require 'auth.php';  // make sure the user is logged in
require 'db.php';

$error = '';

// Which bookmark is being edited? (bookmarks.php?edit=3) 0 means none: show the "Add" form.
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';   // add, update or delete
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        // Old trips keep their own copy of the name and amount, so they stay exactly as they were.
        // We just forget which bookmark they came from (set bookmark_id to NULL) before deleting it.
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE trips SET bookmark_id = NULL WHERE bookmark_id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        header('Location: bookmarks.php?message=' . urlencode('Bookmark deleted.'));
        exit;
    }

    // add and update both need a valid name and amount
    $name   = trim($_POST['name'] ?? '');
    $amount = $_POST['amount'] ?? '';
    $defaultClientId = (int) ($_POST['default_client_id'] ?? 0);

    if ($name === '' || mb_strlen($name) > 100) {
        $error = 'Please give the bookmark a name (up to 100 characters).';
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = 'The amount must be a number above 0.';
    } elseif ($action === 'update') {
        // Changing a bookmark only affects trips logged from now on. Old trips keep their old amount.
        $stmt = $pdo->prepare("UPDATE bookmarks SET name = ?, amount = ?, default_client_id = ? WHERE id = ?");
        $stmt->execute([$name, round((float) $amount, 2), $defaultClientId ?: null, $id]);
        header('Location: bookmarks.php?message=' . urlencode('Bookmark updated.'));
        exit;
    } else {
        // 0 means "No default", which we store as NULL
        $stmt = $pdo->prepare("INSERT INTO bookmarks (name, amount, default_client_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, round((float) $amount, 2), $defaultClientId ?: null]);
        header('Location: bookmarks.php?message=' . urlencode('Bookmark added.'));
        exit;
    }

    if ($action === 'update') {
        $editId = $id;   // a mistake while editing: stay on the edit form
    }
}

$clients   = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();
$bookmarks = $pdo->query("
    SELECT bookmarks.id, bookmarks.name, bookmarks.amount, bookmarks.default_client_id,
           clients.name AS default_client
    FROM bookmarks
    LEFT JOIN clients ON clients.id = bookmarks.default_client_id
    ORDER BY bookmarks.name
")->fetchAll();

// Find the bookmark being edited (if any)
$editing = null;
foreach ($bookmarks as $bookmark) {
    if ((int) $bookmark['id'] === $editId) {
        $editing = $bookmark;
    }
}

// What to show in the form: what was just typed (after an error), else the bookmark, else empty
$form = [
    'name'              => $_POST['name']   ?? ($editing['name'] ?? ''),
    'amount'            => $_POST['amount'] ?? ($editing['amount'] ?? ''),
    'default_client_id' => (int) ($_POST['default_client_id'] ?? ($editing['default_client_id'] ?? 0)),
];

$message = $_GET['message'] ?? '';
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

    <?php if ($message !== ''): ?>
        <p class="notice"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <section class="card">
        <div class="table-wrap">
            <table>
                <tr><th>Name</th><th class="amount">Amount</th><th>Usually for</th><th></th></tr>
                <?php foreach ($bookmarks as $bookmark): ?>
                    <tr>
                        <td><?= htmlspecialchars($bookmark['name']) ?></td>
                        <td class="amount">R<?= number_format($bookmark['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($bookmark['default_client'] ?? 'Ask each time') ?></td>
                        <td class="row-actions">
                            <a href="bookmarks.php?edit=<?= $bookmark['id'] ?>#bookmark-form">Edit</a>
                            <form method="post"
                                  onsubmit="return confirm('Delete this bookmark? Trips you already logged stay as they are.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $bookmark['id'] ?>">
                                <button type="submit" class="link-button danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </section>

    <section class="card" id="bookmark-form">
        <h2><?= $editing ? 'Edit bookmark' : 'Add a bookmark' ?></h2>
        <?php if ($editing): ?>
            <p class="muted">Changes apply to trips you log from now on. Trips already logged keep their old name and amount.</p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post" class="stack" action="bookmarks.php">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= $editing['id'] ?>">
            <?php endif; ?>
            <label>Name
                <input type="text" name="name" required maxlength="100"
                       value="<?= htmlspecialchars($form['name']) ?>">
            </label>
            <label>Amount
                <span class="money"><span>R</span><input type="number" name="amount" required min="0.01" step="0.01"
                       value="<?= htmlspecialchars((string) $form['amount']) ?>"></span>
            </label>
            <label>Usually for
                <select name="default_client_id">
                    <option value="0">Ask each time</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"
                            <?= (int) $client['id'] === $form['default_client_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-buttons">
                <button type="submit"><?= $editing ? 'Save changes' : 'Save bookmark' ?></button>
                <?php if ($editing): ?>
                    <a href="bookmarks.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</main>
</body>
</html>
