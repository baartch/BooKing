<?php

function getLinkIcons(): array
{
    return [];
}

function formatPlainEmailBodyWithQuotes(string $body): string
{
    return htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
}

function renderEmailDetailForTest(string $html, string $text = ''): string
{
    $selectedMailbox = ['id' => 1];
    $message = ['id' => 1, 'body_html' => $html, 'body' => $text];
    $emailDetailShowActions = false;
    $emailDetailShowLinks = false;
    $emailDetailShowAttachments = false;
    $emailDetailIncludeLinkEditor = false;
    ob_start();
    require __DIR__ . '/../app/views/communication/email_detail.php';
    return ob_get_clean();
}

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? '✓ ' : '✗ ') . $label . "\n";
    $failures += $condition ? 0 : 1;
};

$email = '<style>body { display: none }</style><p onclick="alert(1)">Hello</p><script>alert(1)</script>';
$output = renderEmailDetailForTest($email);
$check(!str_contains($output, $email), 'email HTML is not inserted into the parent document');
$check((bool) preg_match('/<iframe\b[^>]*\bsandbox=""[^>]*\bsrcdoc="([^"]*)"/s', $output, $matches), 'HTML email is rendered in a restricted sandbox');
$document = html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
$check(str_contains($document, $email), 'sandbox preserves the email HTML for display');
$policyDocument = html_entity_decode($document, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$check(str_contains($policyDocument, "default-src 'none'") && str_contains($policyDocument, "style-src 'unsafe-inline'"), 'sandbox blocks active and remote content while allowing email styling');

$plainOutput = renderEmailDetailForTest('', "Hello\n> Earlier message");
$check(!str_contains($plainOutput, '<iframe'), 'plain text remains in the normal detail body');
$check(str_contains($plainOutput, 'Hello') && str_contains($plainOutput, 'Earlier message'), 'plain text and quoted text remain visible');

exit($failures === 0 ? 0 : 1);
