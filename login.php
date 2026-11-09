<?php
require_once 'config.php';
require_once 'session.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if ($config['password_hash'] !== '' && password_verify($password, $config['password_hash'])) {
        session_regenerate_id(true);    // a fresh session id after logging in
        $_SESSION['logged_in'] = true;  // remember: this browser is allowed in
        header('Location: index.php');
        exit;
    }

    sleep(1);  // slow down anyone trying thousands of guesses
    $error = 'Wrong password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Trip tracker</title>
</head>
<body>
    <h1>Trip tracker</h1>

    <?php if ($error): ?>
        <p style="color: crimson"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Password: <input type="password" name="password" required autofocus></label>
        <button>Log in</button>
    </form>
</body>
</html>