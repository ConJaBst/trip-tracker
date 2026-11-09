<?php
// try_bot.php: talk to the bot from the command line, without WhatsApp.
// Usage (in the XAMPP Shell):   php try_bot.php school
//
// This file gets uploaded with everything else, so it refuses to run from a browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require 'db.php';
require 'whatsapp_bot.php';

$text = implode(' ', array_slice($argv, 1));   // everything typed after "try_bot.php"
echo handleMessage($pdo, $text), "\n";
