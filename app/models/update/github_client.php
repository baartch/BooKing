<?php
/**
 * GitHub release API client.
 *
 * Reads the latest stable release of the public baartch/BooKing repository.
 * No token required. Only HTTPS.
 */

const GITHUB_REPO = 'baartch/BooKing';
const GITHUB_API_LATEST_URL = 'https://api.github.com/repos/' . GITHUB_REPO . '/releases/latest';
const HTTP_USER_AGENT = 'BooKing-Updater';
const HTTP_TIMEOUT_SECONDS = 15;

/**
 * GET a URL and return its body. curl is used when the extension is
 * available, otherwise the https stream wrapper.
 *
 * @return array{ok: bool, status: int, body: string, error: string}
 */
function httpGetJson(string $url): array
{
    if (!str_starts_with($url, 'https://')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Non-HTTPS URL rejected'];
    }

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => HTTP_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => HTTP_TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => HTTP_USER_AGENT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $error !== '' ? $error : 'Request failed'];
        }

        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => (string) $body, 'error' => ''];
    }

    if (!ini_get('allow_url_fopen')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'No HTTP transport (no curl, allow_url_fopen off)'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => HTTP_TIMEOUT_SECONDS,
            'ignore_errors' => true,
            'header' => "User-Agent: " . HTTP_USER_AGENT . "\r\nAccept: application/vnd.github+json\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);

    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Request failed'];
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
            $status = (int) $matches[1];
        }
    }

    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => (string) $body, 'error' => ''];
}

/**
 * Parse a GitHub /releases/latest response body.
 *
 * @return array{tag: string, zip_url: string, notes_url: string}|null
 */
function parseLatestReleaseResponse(string $body): ?array
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return null;
    }

    if (!empty($decoded['draft']) || !empty($decoded['prerelease'])) {
        return null;
    }

    $tag = trim((string) ($decoded['tag_name'] ?? ''));
    if ($tag === '') {
        return null;
    }

    $expectedAsset = 'BooKing-' . $tag . '.zip';
    foreach ($decoded['assets'] ?? [] as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        if (($asset['name'] ?? '') !== $expectedAsset) {
            continue;
        }
        $zipUrl = trim((string) ($asset['browser_download_url'] ?? ''));
        if ($zipUrl === '') {
            continue;
        }

        return [
            'tag' => $tag,
            'zip_url' => $zipUrl,
            'notes_url' => (string) ($decoded['html_url'] ?? ''),
        ];
    }

    return null;
}

/**
 * Fetch the latest stable release.
 *
 * @return array{ok: bool, tag: string, zip_url: string, notes_url: string, error: string}
 */
function fetchLatestStableRelease(): array
{
    $response = httpGetJson(GITHUB_API_LATEST_URL);
    if (!$response['ok']) {
        $error = $response['error'] !== '' ? $response['error'] : ('HTTP ' . $response['status']);
        return ['ok' => false, 'tag' => '', 'zip_url' => '', 'notes_url' => '', 'error' => $error];
    }

    $parsed = parseLatestReleaseResponse($response['body']);
    if ($parsed === null) {
        return ['ok' => false, 'tag' => '', 'zip_url' => '', 'notes_url' => '', 'error' => 'Unusable release response'];
    }

    return ['ok' => true, 'error' => ''] + $parsed;
}
