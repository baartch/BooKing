<?php

function decodeHeaderValue(?string $value): string
{
    return $value ? imap_utf8($value) : '';
}

function parseEmailAddressList(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $parsed = imap_rfc822_parse_adrlist($raw, '');
    if (!is_array($parsed)) {
        return '';
    }

    $addresses = [];
    foreach ($parsed as $address) {
        if (!isset($address->mailbox, $address->host)) {
            continue;
        }
        $email = strtolower(trim($address->mailbox . '@' . $address->host));
        if ($email !== 'invalid_address@' && $email !== '') {
            $addresses[] = $email;
        }
    }

    return implode(', ', array_unique($addresses));
}

function getPartParameter(object $part, string $name): string
{
    $name = strtolower($name);
    foreach (['parameters', 'dparameters'] as $property) {
        if (!isset($part->{$property}) || !is_array($part->{$property})) {
            continue;
        }
        foreach ($part->{$property} as $param) {
            if (strtolower($param->attribute ?? '') === $name) {
                return decodeHeaderValue((string) $param->value);
            }
        }
    }

    return '';
}

function decodePartBody(string $body, int $encoding): string
{
    if ($encoding === 3) {
        return (string) base64_decode($body);
    }
    if ($encoding === 4) {
        return (string) quoted_printable_decode($body);
    }

    return $body;
}

function convertToUtf8(string $body, string $charset): string
{
    $charset = trim($charset);
    if ($charset === '') {
        return $body;
    }

    $charset = strtoupper($charset);
    if ($charset === 'UTF-8' || $charset === 'UTF8') {
        return $body;
    }

    if (function_exists('mb_convert_encoding')) {
        return (string) mb_convert_encoding($body, 'UTF-8', $charset);
    }

    return $body;
}

function fetchPartBody($imap, int $uid, string $partNumber): string
{
    if ($partNumber === '') {
        return (string) imap_body($imap, (string) $uid, FT_UID);
    }

    return (string) imap_fetchbody($imap, (string) $uid, $partNumber, FT_UID);
}

function partMimeType(object $part): string
{
    $typeMap = [
        0 => 'text',
        1 => 'multipart',
        2 => 'message',
        3 => 'application',
        4 => 'audio',
        5 => 'image',
        6 => 'video',
        7 => 'other'
    ];

    $type = $typeMap[$part->type ?? 0] ?? 'application';
    $subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));

    return $type . '/' . $subtype;
}

function htmlToText(string $html): string
{
    $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<\s*\/p\s*>/i', "\n", $html);
    $text = trim(strip_tags($html));
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function collectParts($imap, int $uid, object $structure, string $partNumber, string &$plainBody, string &$htmlBody, array &$attachments): void
{
    if ($structure->type === 1 && isset($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $nextNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber . '.' . ($index + 1);
            collectParts($imap, $uid, $part, $nextNumber, $plainBody, $htmlBody, $attachments);
        }
        return;
    }

    $filename = getPartParameter($structure, 'filename');
    if ($filename === '') {
        $filename = getPartParameter($structure, 'name');
    }
    $disposition = strtolower((string) ($structure->disposition ?? ''));
    $isAttachment = $filename !== '' || $disposition === 'attachment' || $disposition === 'inline';

    $body = fetchPartBody($imap, $uid, $partNumber === '' ? '1' : $partNumber);
    $body = decodePartBody($body, (int) ($structure->encoding ?? 0));
    $charset = getPartParameter($structure, 'charset');
    $body = convertToUtf8($body, $charset);

    if ($isAttachment && $filename !== '') {
        $attachments[] = [
            'filename' => $filename,
            'data' => $body,
            'mime_type' => partMimeType($structure),
            'size' => strlen($body)
        ];
        return;
    }

    if ($structure->type === 0) {
        $subtype = strtolower((string) ($structure->subtype ?? 'plain'));
        if ($subtype === 'plain' && $plainBody === '') {
            $plainBody = trim($body);
        }
        if ($subtype === 'html' && $htmlBody === '') {
            $htmlBody = trim($body);
        }
    }
}
