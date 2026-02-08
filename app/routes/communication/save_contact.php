<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/form_helpers.php';
require_once __DIR__ . '/../../src-php/communication/contacts_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/pages/communication/index.php';
$searchQuery = trim((string) ($_POST['q'] ?? ''));

$redirectParams = ['tab' => 'contacts'];
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

$action = (string) ($_POST['action'] ?? '');
$contactId = (int) ($_POST['contact_id'] ?? 0);

$countryOptions = ['DE', 'CH', 'AT', 'IT', 'FR'];

$payload = [
    'firstname' => trim((string) ($_POST['firstname'] ?? '')),
    'surname' => trim((string) ($_POST['surname'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'address' => trim((string) ($_POST['address'] ?? '')),
    'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'country' => trim((string) ($_POST['country'] ?? '')),
    'website' => trim((string) ($_POST['website'] ?? '')),
    'notes' => trim((string) ($_POST['notes'] ?? ''))
];

$errors = [];

if ($payload['firstname'] === '') {
    $errors[] = 'Firstname is required.';
}

if ($payload['email'] !== '' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email is invalid.';
}

if ($payload['country'] !== '' && !in_array($payload['country'], $countryOptions, true)) {
    $errors[] = 'Country must be a valid 2-letter code.';
}

if ($action !== 'create_contact' && $action !== 'update_contact') {
    $errors[] = 'Unknown action.';
}

if ($errors) {
    // We do not have a flash-message mechanism, so we redirect with a generic error notice.
    // Keep selection when updating.
    if ($action === 'update_contact' && $contactId > 0) {
        $redirectParams['contact_id'] = $contactId;
    }
    $redirectParams['notice'] = 'contact_error';

    header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($action === 'create_contact') {
        $stmt = $pdo->prepare(
            'INSERT INTO contacts
                (firstname, surname, email, phone, address, postal_code, city, country, website, notes)
             VALUES
                (:firstname, :surname, :email, :phone, :address, :postal_code, :city, :country, :website, :notes)'
        );
        $stmt->execute([
            ':firstname' => normalizeOptionalString($payload['firstname']),
            ':surname' => $payload['surname'],
            ':email' => normalizeOptionalString($payload['email']),
            ':phone' => normalizeOptionalString($payload['phone']),
            ':address' => normalizeOptionalString($payload['address']),
            ':postal_code' => normalizeOptionalString($payload['postal_code']),
            ':city' => normalizeOptionalString($payload['city']),
            ':country' => normalizeOptionalString($payload['country']),
            ':website' => normalizeOptionalString($payload['website']),
            ':notes' => normalizeOptionalString($payload['notes'])
        ]);

        $newId = (int) $pdo->lastInsertId();
        logAction($userId, 'contact_created', sprintf('Created contact %d', $newId));

        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, [
            'notice' => 'contact_created'
        ])));
        exit;
    }

    if ($action === 'update_contact') {
        if ($contactId <= 0) {
            header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'contact_error'])));
            exit;
        }

        $existing = fetchContact($pdo, $contactId);
        if (!$existing) {
            header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'contact_error'])));
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE contacts
             SET firstname = :firstname,
                 surname = :surname,
                 email = :email,
                 phone = :phone,
                 address = :address,
                 postal_code = :postal_code,
                 city = :city,
                 country = :country,
                 website = :website,
                 notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            ':firstname' => normalizeOptionalString($payload['firstname']),
            ':surname' => $payload['surname'],
            ':email' => normalizeOptionalString($payload['email']),
            ':phone' => normalizeOptionalString($payload['phone']),
            ':address' => normalizeOptionalString($payload['address']),
            ':postal_code' => normalizeOptionalString($payload['postal_code']),
            ':city' => normalizeOptionalString($payload['city']),
            ':country' => normalizeOptionalString($payload['country']),
            ':website' => normalizeOptionalString($payload['website']),
            ':notes' => normalizeOptionalString($payload['notes']),
            ':id' => (int) $existing['id']
        ]);

        logAction($userId, 'contact_updated', sprintf('Updated contact %d', (int) $existing['id']));

        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, [
            'notice' => 'contact_updated'
        ])));
        exit;
    }
} catch (Throwable $error) {
    logAction($userId, 'contact_save_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'contact_error'])));
    exit;
}
