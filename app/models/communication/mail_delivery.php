<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/email_helpers.php';

function sendEmailViaMailbox(PDO $pdo, array $mailbox, array $payload): bool
{
    $smtpPassword = decryptSettingValue($mailbox['smtp_password'] ?? '');
    if ($smtpPassword === '') {
        logAction(null, 'smtp_send_error', 'SMTP password missing');
        return false;
    }

    $host = (string) ($mailbox['smtp_host'] ?? '');
    $port = (int) ($mailbox['smtp_port'] ?? 0);
    $username = (string) ($mailbox['smtp_username'] ?? '');
    $encryption = (string) ($mailbox['smtp_encryption'] ?? 'tls');

    if ($host === '' || $port <= 0 || $username === '') {
        logAction(null, 'smtp_send_error', sprintf('SMTP config invalid host=%s port=%d user=%s', $host, $port, $username));
        return false;
    }

    $fromEmail = (string) ($payload['from_email'] ?? $username);
    $fromName = trim((string) ($payload['from_name'] ?? ''));
    $toList = splitEmailList((string) ($payload['to_emails'] ?? ''));
    $ccList = splitEmailList((string) ($payload['cc_emails'] ?? ''));
    $bccList = splitEmailList((string) ($payload['bcc_emails'] ?? ''));
    $subject = (string) ($payload['subject'] ?? '');
    $body = (string) ($payload['body'] ?? '');

    $recipients = array_values(array_unique(array_merge($toList, $ccList, $bccList)));
    if (!$recipients) {
        return false;
    }

    $isHtml = $body !== strip_tags($body);
    $contentType = $isHtml ? 'text/html' : 'text/plain';

    $safeFromName = sanitizeHeaderText($fromName);
    $safeSubject = sanitizeHeaderText($subject);

    $fromHeader = $fromEmail;
    if ($safeFromName !== '') {
        $fromHeader = sprintf('%s <%s>', encodeHeaderWord($safeFromName), $fromEmail);
    }

    $headers = [
        'From: ' . $fromHeader,
        'Subject: ' . encodeHeaderWord($safeSubject),
        'Date: ' . date('r'),
        'MIME-Version: 1.0',
        'Content-Type: ' . $contentType . '; charset=UTF-8'
    ];

    if ($toList) {
        $headers[] = 'To: ' . implode(', ', $toList);
    }

    if ($ccList) {
        $headers[] = 'Cc: ' . implode(', ', $ccList);
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $message = preg_replace("/\r\n\. /", "\r\n..", $message);

    $connection = smtpOpenConnection($host, $port, $encryption);
    if (!$connection) {
        logAction(null, 'smtp_send_error', 'SMTP connection failed');
        return false;
    }

    [$socket, $capabilities] = $connection;

    if (!smtpExpect($socket, 220)) {
        smtpClose($socket);
        logAction(null, 'smtp_send_error', 'SMTP greeting failed');
        return false;
    }

    if (!smtpCommand($socket, 'EHLO ' . gethostname(), 250)) {
        smtpClose($socket);
        logAction(null, 'smtp_send_error', 'SMTP EHLO failed');
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtpCommand($socket, 'STARTTLS', 220)) {
            smtpClose($socket);
            logAction(null, 'smtp_send_error', 'SMTP STARTTLS failed');
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            smtpClose($socket);
            logAction(null, 'smtp_send_error', 'SMTP TLS negotiation failed');
            return false;
        }
        if (!smtpCommand($socket, 'EHLO ' . gethostname(), 250)) {
            smtpClose($socket);
            logAction(null, 'smtp_send_error', 'SMTP EHLO after TLS failed');
            return false;
        }
    }

    if (!smtpCommand($socket, 'AUTH LOGIN', 334)) {
        smtpClose($socket);
        logAction(null, 'smtp_send_error', 'SMTP AUTH LOGIN failed');
        return false;
    }
    if (!smtpCommand($socket, base64_encode($username), 334)) {
        smtpClose($socket);
        logAction(null, 'smtp_send_error', 'SMTP AUTH username failed');
        return false;
    }
    if (!smtpCommand($socket, base64_encode($smtpPassword), 235)) {
        smtpClose($socket);
        logAction(null, 'smtp_send_error', 'SMTP AUTH password failed');
        return false;
    }

    if (!smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250)) {
        smtpClose($socket);
        logAction(null, 'smtp_send_error', 'SMTP MAIL FROM failed');
        return false;
    }

    foreach ($recipients as $recipient) {
        if (!smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', 250)) {
            smtpClose($socket);
            logAction(null, 'smtp_send_error', 'SMTP RCPT TO failed');
            return false;
        }
    }

    if (!smtpCommand($socket, 'DATA', 354)) {
        smtpClose($socket);
        logAction(null, 'smtp_send_error', 'SMTP DATA command failed');
        return false;
    }

    fwrite($socket, $message . "\r\n.\r\n");
    if (!smtpExpect($socket, 250)) {
        smtpClose($socket);
        logAction(null, 'smtp_send_error', 'SMTP DATA send failed');
        return false;
    }

    smtpCommand($socket, 'QUIT', 221);
    smtpClose($socket);

    return true;
}

function sanitizeHeaderText(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function encodeHeaderWord(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (!preg_match('/[^\x20-\x7E]/', $value)) {
        return $value;
    }

    if (function_exists('mb_encode_mimeheader')) {
        $encoded = mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        if ($encoded !== false && $encoded !== '') {
            return $encoded;
        }
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtpOpenConnection(string $host, int $port, string $encryption): ?array
{
    $transport = $encryption === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        return null;
    }

    stream_set_timeout($socket, 10);
    return [$socket, []];
}

function smtpCommand($socket, string $command, int $expectCode): bool
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectCode);
}

function smtpExpect($socket, int $expectCode): bool
{
    $response = '';
    while (($line = fgets($socket, 512)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    if ($response === '') {
        return false;
    }

    $code = (int) substr($response, 0, 3);
    return $code === $expectCode;
}

function smtpClose($socket): void
{
    if (is_resource($socket)) {
        fclose($socket);
    }
}
