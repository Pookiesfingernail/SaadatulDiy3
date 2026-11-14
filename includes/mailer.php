<?php
/*
 * SIMPLE SMTP MAILER - SA'ADATUL D.I.Y
 * Sends order notification emails through Gmail SMTP (or any
 * SMTP server) using PHP's fsockopen - no external libraries
 * needed. Used by api/checkout.php for order notifications.
 *
 * Returns true only when the SMTP server accepts the message.
 * Returns false (and logs the reason) when it cannot be sent,
 * e.g. when SMTP credentials are not configured yet.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';

/**
 * Send an email with optional file attachments.
 *
 * @param string   $to           Recipient address
 * @param string   $subject      Email subject
 * @param string   $htmlBody     HTML message body
 * @param string[] $attachments  Absolute file paths to attach
 * @return array{ok: bool, error: string}
 */
function send_smtp_mail(string $to, string $subject, string $htmlBody, array $attachments = []): array
{
    if (SMTP_USER === '' || SMTP_PASS === '') {
        return ['ok' => false, 'error' => 'SMTP is not configured (set SMTP_USER and SMTP_PASS in config/mail.local.php).'];
    }

    $remote = SMTP_SECURE === 'ssl'
        ? 'ssl://' . SMTP_HOST . ':' . SMTP_PORT
        : SMTP_HOST . ':' . SMTP_PORT;

    $socket = @fsockopen($remote, null, $errno, $errstr, 20);

    if (!$socket) {
        return ['ok' => false, 'error' => "Cannot connect to {$remote}: {$errstr} ({$errno})"];
    }

    stream_set_timeout($socket, 20);

    $read = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            // SMTP replies can span multiple lines ("250-..." then "250 ...").
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $send = function (string $command) use ($socket, $read): bool {
        fwrite($socket, $command . "\r\n");
        $reply = $read();
        return str_starts_with($reply, '2') || str_starts_with($reply, '3');
    };

    $fail = function (string $step, string $reply) use ($socket): array {
        if (is_resource($socket)) fclose($socket);
        return ['ok' => false, 'error' => "SMTP {$step} failed: " . trim($reply)];
    };

    $banner = $read();
    if (!str_starts_with($banner, '220')) {
        return $fail('greeting', $banner);
    }

    if (!$send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'))) {
        return $fail('EHLO', $read());
    }

    if (SMTP_SECURE === 'tls') {
        if (!$send('STARTTLS')) return $fail('STARTTLS', $read());
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['ok' => false, 'error' => 'TLS encryption could not be started.'];
        }
        if (!$send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'))) {
            return $fail('EHLO after TLS', $read());
        }
    }

    if (!$send('AUTH LOGIN')) return $fail('AUTH LOGIN', $read());
    if (!$send(base64_encode(SMTP_USER))) return $fail('SMTP username', $read());
    if (!$send(base64_encode(SMTP_PASS))) return $fail('SMTP password', $read());

    if (!$send('MAIL FROM:<' . MAIL_FROM . '>')) return $fail('MAIL FROM', $read());
    if (!$send('RCPT TO:<' . $to . '>')) return $fail('RCPT TO', $read());
    if (!$send('DATA')) return $fail('DATA', $read());

    // ---------- Build the MIME message ----------
    $mixedBoundary = 'sdiy_mixed_' . bin2hex(random_bytes(8));
    $altBoundary  = 'sdiy_alt_' . bin2hex(random_bytes(8));

    $headers = [
        'From: ' . mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8') . ' <' . MAIL_FROM . '>',
        'To: <' . $to . '>',
        'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
        'MIME-Version: 1.0',
        'Date: ' . date(DATE_RFC2822),
        'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"',
    ];

    $body = '';
    $body .= "--{$mixedBoundary}\r\n";
    $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
    $body .= "--{$altBoundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= "This is an HTML email. Please use an email client that supports HTML.\r\n\r\n";
    $body .= "--{$altBoundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $htmlBody . "\r\n";
    $body .= "--{$altBoundary}--\r\n";

    // Attach files (e.g. custom reference photos, payment proof).
    foreach ($attachments as $path) {
        if (!is_file($path)) continue;

        $fileName = basename($path);
        $mime = 'application/octet-stream';
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf',
        ];
        if (isset($mimeMap[$ext])) $mime = $mimeMap[$ext];

        $body .= "--{$mixedBoundary}\r\n";
        $body .= 'Content-Type: ' . $mime . '; name="' . $fileName . '"' . "\r\n";
        $body .= 'Content-Disposition: attachment; filename="' . $fileName . '"' . "\r\n";
        $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
        $body .= chunk_split(base64_encode((string) file_get_contents($path))) . "\r\n";
    }

    $body .= "--{$mixedBoundary}--\r\n";

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";

    // Dot-stuffing: lines starting with a dot must be escaped.
    $message = preg_replace('/^\./m', '..', $message);

    fwrite($socket, $message);
    $reply = $read();

    if (!str_starts_with($reply, '250')) {
        return $fail('message body', $reply);
    }

    $send('QUIT');
    fclose($socket);

    return ['ok' => true, 'error' => ''];
}
