<?php
/**
 * CSRF Token Test Script
 *
 * This script tests the CSRF protection implementation.
 * Run this from the command line: php tests/csrf_test.php
 * Exits with 0 if all tests pass, 1 if any test fails.
 */

require_once __DIR__ . '/../app/models/auth/csrf.php';

// Start the session before any output so cookie headers can still be sent.
ensurePhpSessionStarted();

$failures = 0;

function check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "✓ " . $label . "\n";
    } else {
        $failures++;
        echo "✗ " . $label . " (FAILED)\n";
    }
}

echo "CSRF Token Protection Test\n";
echo "==========================\n\n";

// Test 1: Generate token
echo "Test 1: Generating CSRF token...\n";
$token1 = generateCsrfToken();
check(strlen($token1) === 64, 'Token generated with 64 characters (' . substr($token1, 0, 16) . '...)');

// Test 2: Get token (should return same token)
echo "Test 2: Getting existing token...\n";
$token2 = getCsrfToken();
check($token1 === $token2, 'Same token returned');

// Test 3: Validate correct token
echo "Test 3: Validating correct token...\n";
check(validateCsrfToken($token1), 'Token validated successfully');

// Test 4: Validate incorrect token
echo "Test 4: Validating incorrect token...\n";
$badToken = bin2hex(random_bytes(32));
check(!validateCsrfToken($badToken), 'Invalid token correctly rejected');

// Test 5: Validate empty token
echo "Test 5: Validating empty token...\n";
check(!validateCsrfToken(''), 'Empty token correctly rejected');

// Test 6: Token expiration (simulate by manipulating session time)
echo "Test 6: Testing token expiration...\n";
$_SESSION['csrf_token_time'] = time() - 7201; // Set to 2 hours + 1 second ago
check(!validateCsrfToken($token1), 'Expired token correctly rejected');

// Test 7: Generate new token after expiration
echo "Test 7: Generating new token after expiration...\n";
$token3 = getCsrfToken();
check($token3 !== $token1, 'New token generated after expiration');
check(validateCsrfToken($token3), 'New token validates successfully');

echo "\n";
echo "==========================\n";
if ($failures > 0) {
    echo $failures . " test(s) FAILED\n";
    exit(1);
}
echo "All tests passed\n";
