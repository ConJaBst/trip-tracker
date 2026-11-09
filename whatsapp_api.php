<?php
// whatsapp_api.php: the bot's "mouth". Sends messages through Meta's Graph API.

function waSendText(array $config, string $to, string $text): bool {
    return waSend($config, [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'text',
        'text'              => ['body' => $text],
    ]);
}

// Sends any message to Meta. Returns true if Meta accepted it.
function waSend(array $config, array $message): bool {
    if ($config['wa_token'] === '') {
        // No token (on your PC): write what we WOULD have sent to the log instead.
        error_log('WhatsApp (not sent, no token): ' . json_encode($message));
        return false;
    }

    $url = "https://graph.facebook.com/{$config['wa_graph_version']}/{$config['wa_phone_number_id']}/messages";

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $config['wa_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($message),
        CURLOPT_RETURNTRANSFER => true,   // give us the answer instead of printing it
        CURLOPT_TIMEOUT        => 15,     // give up after 15 seconds
    ]);
    $answer = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    if ($status !== 200) {
        // Status 0 means we never reached Meta at all (no internet, wrong address...).
        $why = $status === 0 ? curl_error($curl) : $answer;
        error_log("WhatsApp send failed ($status): $why");
        return false;
    }

    // For now, also log when it worked. Remove this line once the bot is running smoothly.
    error_log("WhatsApp: Meta accepted the reply: $answer");
    return true;
}