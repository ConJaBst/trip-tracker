<?php
// whatsapp_bot.php: the bot's "brain".
// It turns the text of a message into an action and a reply.
// It knows nothing about WhatsApp, so you can test it from the command line.
require_once __DIR__ . '/totals.php';

// The one function the rest of the app calls. Text in, reply out.
function handleMessage(PDO $pdo, string $text): string {
    // Tidy up: trim the ends and squash repeated spaces, then split into words.
    $text  = trim(preg_replace('/\s+/', ' ', $text));
    $words = $text === '' ? [] : explode(' ', $text);
    $first = mb_strtolower($words[0] ?? '');

    if ($first === '' || $first === 'help' || $first === '?') {
        return botHelp($pdo);
    }
    if ($first === 'total' || $first === 'owed') {
        return botTotals($pdo);
    }
    if ($first === 'undo') {
        return botUndo($pdo);
    }
    if ($first === 'add') {
        return botAddOneTime($pdo, array_slice($words, 1));
    }

    // Anything else might be the name of a bookmark, like "school".
    return botBookmark($pdo, $words);
}

// ---------- The commands ----------

function botHelp(PDO $pdo): string {
    $lines = ["Here's what I understand:", ''];

    foreach ($pdo->query("SELECT name, amount FROM bookmarks ORDER BY name") as $bookmark) {
        $lines[] = '• ' . mb_strtolower($bookmark['name']) . ' (' . money($bookmark['amount']) . ')';
    }
    $lines[] = '• add 250 airport dad  (a one-time trip)';
    $lines[] = '• total  (what everyone owes)';
    $lines[] = '• undo  (delete the last trip)';
    $lines[] = '';
    $lines[] = 'Add a name to the end to choose who pays, like: school dad';

    return implode("\n", $lines);
}

function botTotals(PDO $pdo): string {
    $lines = ['What everyone owes:'];
    foreach (getTotals($pdo) as $row) {
        $lines[] = "{$row['name']}: " . money($row['total_owed']) . " ({$row['trip_count']} trips)";
    }
    return implode("\n", $lines);
}

function botBookmark(PDO $pdo, array $words): string {
    [$client, $words] = takeClientFromEnd($pdo, $words);

    // "school 2": if the last word is a whole number, that's how many trips.
    $count = 1;
    if (count($words) > 1 && ctype_digit(end($words))) {
        $count = (int) array_pop($words);
    }
    if ($count < 1 || $count > 10) {
        return 'I can log 1 to 10 trips at a time.';
    }

    $name = mb_strtolower(implode(' ', $words));

    // "school" matches "School run": find bookmarks whose name STARTS with what was sent.
    $stmt = $pdo->prepare("
        SELECT id, name, amount, default_client_id FROM bookmarks
        WHERE LOWER(name) LIKE ? ORDER BY name
    ");
    $stmt->execute([$name . '%']);
    $matches = $stmt->fetchAll();

    if ($name === '' || count($matches) === 0) {
        return "Sorry, I don't know \"" . implode(' ', $words) . "\". Send help to see what I understand.";
    }
    if (count($matches) > 1) {
        $names = array_map(fn($b) => mb_strtolower($b['name']), $matches);
        return 'Which one? ' . implode(', ', $names);
    }

    $bookmark = $matches[0];

    // No name in the message? Use the bookmark's usual person.
    if ($client === null && $bookmark['default_client_id'] !== null) {
        $client = findClientById($pdo, (int) $bookmark['default_client_id']);
    }
    if ($client === null) {
        return "Who's it for? Put a name at the end, like: " . mb_strtolower($bookmark['name']) . ' ' . exampleClientName($pdo);
    }

    for ($i = 0; $i < $count; $i++) {
        saveTrip($pdo, (int) $client['id'], $bookmark['name'], (float) $bookmark['amount'], (int) $bookmark['id']);
    }

    return "Logged: {$count} × {$bookmark['name']}, " . money($bookmark['amount'] * $count) . " for {$client['name']}.\n"
         . owedLine($pdo, (int) $client['id']);
}

function botAddOneTime(PDO $pdo, array $words): string {
    $example = 'add 250 airport ' . exampleClientName($pdo);

    [$client, $words] = takeClientFromEnd($pdo, $words);

    // The first word is the amount. Allow "250", "R250" and "250,50".
    $amountText = str_replace(',', '.', ltrim(array_shift($words) ?? '', 'Rr'));
    if (!is_numeric($amountText) || $amountText <= 0) {
        return "I couldn't find the amount. Try: $example";
    }
    $amount = round((float) $amountText, 2);

    // Whatever's left is the description.
    $description = ucfirst(implode(' ', $words));
    if ($description === '' || mb_strlen($description) > 255) {
        return "What was the trip? Try: $example";
    }

    if ($client === null) {
        return "Who's it for? Put a name at the end. Try: $example";
    }

    saveTrip($pdo, (int) $client['id'], $description, $amount, null);

    return "Added: $description, " . money($amount) . " for {$client['name']}.\n"
         . owedLine($pdo, (int) $client['id']);
}

function botUndo(PDO $pdo): string {
    // The most recent trip that isn't on an invoice yet.
    $trip = $pdo->query("
        SELECT trips.id, trips.description, trips.amount, clients.name
        FROM trips
        JOIN clients ON clients.id = trips.client_id
        WHERE trips.invoice_id IS NULL
        ORDER BY trips.id DESC
        LIMIT 1
    ")->fetch();

    if (!$trip) {
        return "There's nothing to undo. Trips that are already on an invoice can't be undone.";
    }

    $stmt = $pdo->prepare("DELETE FROM trips WHERE id = ? AND invoice_id IS NULL");
    $stmt->execute([$trip['id']]);

    return "Deleted: {$trip['description']}, " . money($trip['amount']) . " for {$trip['name']}.";
}

// ---------- Small helpers ----------

// If the LAST word is a client's name ("school dad"), take it off and return that client.
// Also drops a "for" in front of it ("add 250 airport for dad").
// Returns two things: [the client or null, the words that are left].
function takeClientFromEnd(PDO $pdo, array $words): array {
    if (count($words) < 2) {
        return [null, $words];   // need at least one word before the name
    }

    $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE LOWER(name) = ?");
    $stmt->execute([mb_strtolower(end($words))]);
    $client = $stmt->fetch();

    if (!$client) {
        return [null, $words];
    }

    array_pop($words);                                   // remove the name
    if (mb_strtolower(end($words) ?: '') === 'for') {
        array_pop($words);                               // remove "for"
    }
    return [$client, $words];
}

function findClientById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function exampleClientName(PDO $pdo): string {
    $name = $pdo->query("SELECT name FROM clients ORDER BY name LIMIT 1")->fetchColumn();
    return mb_strtolower($name ?: 'mom');
}

function saveTrip(PDO $pdo, int $clientId, string $description, float $amount, ?int $bookmarkId): void {
    $stmt = $pdo->prepare("
        INSERT INTO trips (client_id, trip_date, description, amount, bookmark_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$clientId, date('Y-m-d'), $description, $amount, $bookmarkId]);
}

function owedLine(PDO $pdo, int $clientId): string {
    foreach (getTotals($pdo) as $row) {
        if ((int) $row['id'] === $clientId) {
            return "{$row['name']} now owes " . money($row['total_owed']) . '.';
        }
    }
    return '';
}

function money($amount): string {
    return 'R' . number_format((float) $amount, 2);
}