<?php
// whatsapp.php: the webhook. Meta sends every message you write to the bot here.
// No auth.php: Meta can't log in. It proves who it is with a signature instead.
require 'db.php';
require 'whatsapp_bot.php';
require 'whatsapp_api.php';

// --- 1. The one-time handshake (GET), when you save the webhook in Meta ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Meta sends hub.mode, hub.verify_token and hub.challenge.
    // PHP turns the dots into underscores.
    $mode      = $_GET['hub_mode'] ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $config['wa_verify_token'] !== ''
            && hash_equals($config['wa_verify_token'], $token)) {
        header('Content-Type: text/plain');
        echo $challenge;   // repeating the challenge back says "yes, this is my webhook"
        exit;
    }
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);   // 405 means "wrong method"
    exit;
}

// --- 2. Is this really from Meta? Check the signature ---
$body     = file_get_contents('php://input');   // the raw, untouched message
$given    = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $body, $config['wa_app_secret']);

if ($config['wa_app_secret'] === '' || !hash_equals($expected, $given)) {
    error_log('WhatsApp: bad signature (check WA_APP_SECRET)');
    http_response_code(403);
    exit;
}

// --- 3. Handle one message ---
function handleIncoming(PDO $pdo, array $config, array $message): void {
    $from = $message['from'] ?? '';

    // Only you can use the bot.
    if ($from !== $config['wa_my_number']) {
        error_log("WhatsApp: ignored a message from $from");
        return;
    }

    // Meta sometimes delivers the same message twice. Remember each id, and skip repeats.
    try {
        $stmt = $pdo->prepare("INSERT INTO whatsapp_seen (message_id) VALUES (?)");
        $stmt->execute([$message['id'] ?? '']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return;      // 23000 = "that id is already in the table", so we've done this one
        }
        throw $e;        // any other database problem is a real error
    }

    if (($message['type'] ?? '') !== 'text') {
        waSendText($config, $from, 'I only understand text messages. Send help to see what I can do.');
        return;
    }

    try {
        $reply = handleMessage($pdo, $message['text']['body'] ?? '');
    } catch (Throwable $e) {
        error_log('WhatsApp bot error: ' . $e->getMessage());
        $reply = 'Something went wrong, so nothing was saved. Try again, or use the website.';
    }

    waSendText($config, $from, $reply);
}

// --- 4. Find the messages inside Meta's envelope ---
$data = json_decode($body, true) ?? [];

foreach ($data['entry'] ?? [] as $entry) {
    foreach ($entry['changes'] ?? [] as $change) {
        foreach ($change['value']['messages'] ?? [] as $message) {
            handleIncoming($pdo, $config, $message);
        }
        // Meta tells us later if a reply couldn't be delivered. Log why.
        foreach ($change['value']['statuses'] ?? [] as $status) {
            if (($status['status'] ?? '') === 'failed') {
                error_log('WhatsApp: delivery failed: ' . json_encode($status['errors'] ?? []));
            }
        }
    }
}

// --- 5. Tell Meta "got it", or it will keep re-sending ---
http_response_code(200);
echo 'OK';