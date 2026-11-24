<?php

define('BASE_PATH', '/booking');
require_once __DIR__ . '/../app/models/core/link_helpers.php';

function renderCsrfField(): void
{
    echo '<input type="hidden" name="csrf_token" value="fixture-csrf">';
}

function getCsrfToken(): string
{
    return 'fixture-csrf';
}

function formatPlainEmailBodyWithQuotes(string $body): string
{
    return nl2br(htmlspecialchars($body));
}

function formatBytes(int $bytes): string
{
    return (string) $bytes;
}

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? '✓ ' : '✗ ') . $label . "\n";
    $failures += $condition ? 0 : 1;
};

$render = static function (string $path, array $variables): string {
    $renderTemplate = static function () use ($path, $variables): void {
        extract($variables, EXTR_SKIP);
        require $path;
    };

    ob_start();
    $renderTemplate();
    return (string) ob_get_clean();
};

$contains = static function (string $html, string $expected, string $label) use (&$check): void {
    $check(str_contains($html, $expected), $label);
};

$notContains = static function (string $html, string $unexpected, string $label) use (&$check): void {
    $check(!str_contains($html, $unexpected), $label);
};

$checkSingleList = static function (string $html, string $label) use (&$check): void {
    preg_match_all('/class="([^"]*)"/', $html, $classes);
    $listClassCount = count(array_filter(
        $classes[1],
        static fn(string $class): bool => in_array('detail-link-list', preg_split('/\s+/', trim($class)) ?: [], true)
    ));
    $check($listClassCount === 1, "$label has one non-nested pill list");
};

$checkEditableModal = static function (
    string $html,
    string $type,
    int $id,
    string $searchTypes,
    string $label
) use (&$contains): void {
    $modalId = 'link-editor-' . $type . '-' . $id;
    $contains($html, 'data-link-editor-modal-id="' . $modalId . '"', "$label pencil targets the existing modal");
    $contains($html, 'data-link-editor-modal id="' . $modalId . '"', "$label includes its existing modal");
    $contains($html, 'data-source-type="' . $type . '"', "$label preserves its modal source type");
    $contains($html, 'data-source-id="' . $id . '"', "$label preserves its modal source ID");
    $contains($html, 'data-link-editor-types="' . $searchTypes . '"', "$label preserves its link search types");
};

$checkLink = static function (
    string $html,
    string $href,
    string $labelText,
    string $icon,
    string $label
) use (&$contains): void {
    $contains($html, 'href="' . $href . '" class="detail-link-pill"', "$label renders the expected destination");
    $contains($html, '>' . $labelText . '</span>', "$label renders the expected label");
    $contains($html, 'fa-solid ' . $icon, "$label renders the expected icon");
};

$contact = [
    'id' => 12,
    'firstname' => 'Ada',
    'surname' => 'Lovelace',
    'email' => 'ada@example.test',
    'website' => '',
    'phone' => '',
    'address' => '',
    'postal_code' => '',
    'city' => '',
    'country' => '',
    'notes' => '',
];
$venueLink = ['type' => 'venue', 'id' => 7, 'label' => 'Hall & Garden'];

$contactDetailPath = __DIR__ . '/../app/views/communication/contacts_detail.php';
$contactDetail = $render($contactDetailPath, [
    'activeContact' => $contact,
    'contactLinks' => [$venueLink],
    'baseUrl' => '/booking/app/controllers/communication/index.php',
    'baseQuery' => ['tab' => 'contacts'],
    'activeTeamId' => 1,
]);
$checkLink($contactDetail, '/booking/app/controllers/venues/index.php?venue_id=7&amp;filter=Hall+%26+Garden', 'Hall &amp; Garden', 'fa-location-dot', 'contact detail');
$checkSingleList($contactDetail, 'contact detail');
$checkEditableModal($contactDetail, 'contact', 12, 'contact,venue,email,task', 'contact detail');
$contactDetailEmpty = $render($contactDetailPath, [
    'activeContact' => $contact,
    'contactLinks' => [],
    'baseUrl' => '/booking/app/controllers/communication/index.php',
    'baseQuery' => ['tab' => 'contacts'],
    'activeTeamId' => 1,
]);
$contains($contactDetailEmpty, 'No links yet', 'contact detail renders the empty state');
$checkEditableModal($contactDetailEmpty, 'contact', 12, 'contact,venue,email,task', 'empty contact detail');

$contactValues = [
    'firstname' => 'Ada', 'surname' => 'Lovelace', 'address' => '', 'postal_code' => '',
    'city' => '', 'country' => '', 'email' => '', 'phone' => '', 'website' => '', 'notes' => '',
];
$contactFormPath = __DIR__ . '/../app/views/communication/contacts_form.php';
$contactForm = $render($contactFormPath, [
    'formValues' => $contactValues,
    'countryOptions' => [],
    'isEdit' => true,
    'editContact' => $contact,
    'contactLinks' => [$venueLink],
    'activeTeamId' => 1,
    'cancelUrl' => '/cancel',
    'searchQuery' => '',
]);
$checkLink($contactForm, '/booking/app/controllers/venues/index.php?q=Hall+%26+Garden', 'Hall &amp; Garden', 'fa-location-dot', 'contact form');
$checkSingleList($contactForm, 'contact form');
$checkEditableModal($contactForm, 'contact', 12, 'contact,venue,email,task', 'contact form');
$contactFormEmpty = $render($contactFormPath, [
    'formValues' => $contactValues,
    'countryOptions' => [],
    'isEdit' => true,
    'editContact' => $contact,
    'contactLinks' => [],
    'activeTeamId' => 1,
    'cancelUrl' => '/cancel',
    'searchQuery' => '',
]);
$contains($contactFormEmpty, 'No links yet', 'contact form renders the empty state');
$contactCreateForm = $render($contactFormPath, [
    'formValues' => $contactValues,
    'countryOptions' => [],
    'isEdit' => false,
    'editContact' => null,
    'contactLinks' => [],
    'activeTeamId' => 1,
    'cancelUrl' => '/cancel',
    'searchQuery' => '',
]);
$notContains($contactCreateForm, 'data-link-editor-trigger', 'new contact form does not expose link editing');

$emailDetailPath = __DIR__ . '/../app/views/communication/email_detail.php';
$emailMessage = [
    'id' => 42,
    'conversation_id' => 30,
    'from_name' => 'Sender',
    'from_email' => 'sender@example.test',
    'to_emails' => 'recipient@example.test',
    'received_at' => '2026-01-01 12:00:00',
    'subject' => 'Subject',
    'body' => 'Body',
    'body_html' => '',
];
$emailPdo = new class {
    public function prepare(string $query): object
    {
        return new class {
            public function execute(array $params): void {}
            public function fetch(): array { return ['subject' => 'Email thread']; }
        };
    }
};
$emailDetail = $render($emailDetailPath, [
    'message' => $emailMessage,
    'messageLinks' => [['type' => 'contact', 'id' => 9, 'label' => 'Grace']],
    'selectedMailbox' => ['id' => 4],
    'pdo' => $emailPdo,
    'baseEmailUrl' => '/booking/app/controllers/communication/index.php',
    'baseQuery' => [],
    'folder' => 'inbox',
    'sortKey' => 'received_desc',
    'filter' => '',
    'page' => 1,
    'emailDetailShowActions' => false,
    'emailDetailShowAttachments' => false,
]);
$contains($emailDetail, 'href="/booking/app/controllers/communication/index.php?tab=conversations&amp;conversation_id=30" class="detail-link-pill"', 'email conversation link renders the expected destination');
$contains($emailDetail, 'Email thread', 'email conversation link renders its existing label');
$contains($emailDetail, 'fa-solid fa-comments', 'email conversation link renders the expected icon');
$checkLink($emailDetail, '/booking/app/controllers/communication/index.php?tab=contacts&amp;contact_id=9', 'Grace', 'fa-user', 'email linked contact');
$checkSingleList($emailDetail, 'email detail');
$checkEditableModal($emailDetail, 'email', 42, 'contact,venue,email,task', 'email detail');
$contains($emailDetail, 'data-mailbox-id="4"', 'email detail preserves its mailbox source ID');
$emailDetailEmpty = $render($emailDetailPath, [
    'message' => array_merge($emailMessage, ['conversation_id' => null]),
    'messageLinks' => [],
    'selectedMailbox' => ['id' => 4],
    'baseEmailUrl' => '/booking/app/controllers/communication/index.php',
    'baseQuery' => [],
    'folder' => 'inbox',
    'sortKey' => 'received_desc',
    'filter' => '',
    'page' => 1,
    'emailDetailShowActions' => false,
    'emailDetailShowAttachments' => false,
]);
$contains($emailDetailEmpty, 'No links yet', 'email detail renders the empty state');
$checkEditableModal($emailDetailEmpty, 'email', 42, 'contact,venue,email,task', 'empty email detail');

$notePartialPath = __DIR__ . '/../app/partials/conversation_note_links.php';
$conversationViewSource = file_get_contents(__DIR__ . '/../app/views/communication/conversations.php');
$check(
    $conversationViewSource !== false
        && str_contains($conversationViewSource, "require __DIR__ . '/../../partials/conversation_note_links.php';"),
    'conversation timeline includes the rendered note-links partial'
);
$check(
    $conversationViewSource !== false
        && str_contains($conversationViewSource, '$emailDetailShowLinks = true;'),
    'conversation email details display their linked objects'
);
$noteLinks = is_file($notePartialPath)
    ? $render($notePartialPath, ['noteItem' => ['links' => [$venueLink]]])
    : '';
$check(is_file($notePartialPath), 'conversation timeline renders links through the tested note-links partial');
$checkLink($noteLinks, '/booking/app/controllers/venues/index.php?venue_id=7&amp;filter=Hall+%26+Garden', 'Hall &amp; Garden', 'fa-location-dot', 'read-only conversation note');
$checkSingleList($noteLinks, 'read-only conversation note');
$notContains($noteLinks, 'data-link-editor-trigger', 'read-only conversation note has no pencil');
$noteLinksEmpty = is_file($notePartialPath)
    ? $render($notePartialPath, ['noteItem' => ['links' => []]])
    : '';
$notContains($noteLinksEmpty, 'No links yet', 'empty read-only note links remain hidden');
$notContains($noteLinksEmpty, 'detail-link-list', 'empty read-only note links render no list');

$show = ['id' => 21, 'name' => 'Show', 'show_date' => '', 'show_time' => '', 'venue_text' => '', 'artist_fee' => '', 'entrance_fee' => '', 'notes' => '', 'created_at' => '', 'updated_at' => ''];
$showPath = __DIR__ . '/../app/views/team/shows/detail.php';
$showVars = [
    'activeShow' => $show,
    'baseUrl' => '/booking/app/controllers/team/index.php',
    'baseQuery' => ['tab' => 'shows'],
    'activeTeamId' => 1,
    'showLinks' => [$venueLink],
    'activeShowSongs' => [],
    'searchQuery' => '',
];
$showHtml = $render($showPath, $showVars);
$checkLink($showHtml, '/booking/app/controllers/venues/index.php?venue_id=7&amp;filter=Hall+%26+Garden', 'Hall &amp; Garden', 'fa-location-dot', 'show detail');
$checkSingleList($showHtml, 'show detail');
$checkEditableModal($showHtml, 'show', 21, 'contact,venue,email,task,show', 'show detail');
$showEmpty = $render($showPath, array_merge($showVars, ['showLinks' => []]));
$contains($showEmpty, 'No links yet', 'show detail renders the empty state');

$task = ['id' => 31, 'title' => 'Task', 'priority' => 'B', 'due_date' => '', 'description' => '', 'created_at' => '', 'updated_at' => ''];
$taskDetailPath = __DIR__ . '/../app/views/team/tasks/detail.php';
$taskDetailVars = [
    'activeTask' => $task,
    'baseUrl' => '/booking/app/controllers/team/index.php',
    'baseQuery' => ['tab' => 'tasks'],
    'activeTeamId' => 1,
    'taskLinks' => [['type' => 'email', 'id' => 9, 'label' => 'Message']],
];
$taskDetail = $render($taskDetailPath, $taskDetailVars);
$checkLink($taskDetail, '/booking/app/controllers/communication/index.php?tab=email&amp;message_id=9', 'Message', 'fa-envelope', 'task detail');
$checkSingleList($taskDetail, 'task detail');
$checkEditableModal($taskDetail, 'task', 31, 'contact,venue,email,task', 'task detail');
$taskDetailEmpty = $render($taskDetailPath, array_merge($taskDetailVars, ['taskLinks' => []]));
$contains($taskDetailEmpty, 'No links yet', 'task detail renders the empty state');

$taskFormPath = __DIR__ . '/../app/views/team/tasks/form.php';
$taskFormValues = ['title' => 'Task', 'priority' => 'B', 'due_date' => '', 'description' => ''];
$taskFormVars = [
    'isEdit' => true,
    'editTask' => $task,
    'formValues' => $taskFormValues,
    'cancelUrl' => '/cancel',
    'searchQuery' => '',
    'taskLinks' => [$venueLink, ['type' => 'show', 'id' => 22, 'label' => 'Show link']],
    'activeTeamId' => 1,
];
$taskForm = $render($taskFormPath, $taskFormVars);
$checkLink($taskForm, '/booking/app/controllers/venues/index.php?q=Hall+%26+Garden', 'Hall &amp; Garden', 'fa-location-dot', 'task form');
$checkSingleList($taskForm, 'task form');
$checkEditableModal($taskForm, 'task', 31, 'contact,venue,email,task', 'task form');
$contains($taskForm, 'data-link-editor-collector', 'task form retains its link collector');
$contains($taskForm, 'name="link_items[]"', 'task form retains its hidden link inputs');
$contains($taskForm, 'fa-solid fa-link', 'task form preserves the generic icon for show links');
$notContains($taskForm, 'fa-solid fa-music', 'task form does not substitute the show-specific icon for linked shows');
$taskFormEmpty = $render($taskFormPath, array_merge($taskFormVars, ['taskLinks' => []]));
$contains($taskFormEmpty, 'No links yet', 'task form renders the empty state');
$taskCreateForm = $render($taskFormPath, array_merge($taskFormVars, ['isEdit' => false, 'editTask' => null]));
$notContains($taskCreateForm, 'data-link-editor-trigger', 'new task form does not expose link editing');

$venuePath = __DIR__ . '/../app/views/venues/venues/detail.php';
$venue = ['id' => 55, 'name' => 'Venue', 'city' => '', 'contact_person' => '', 'contact_email' => '', 'contact_phone' => '', 'website' => '', 'latitude' => null, 'longitude' => null, 'address' => '', 'state' => '', 'postal_code' => '', 'country' => '', 'notes' => '', 'created_at' => '', 'updated_at' => ''];
$venueVars = [
    'activeVenue' => $venue,
    'activeTeamId' => 0,
    'venueLinks' => [['type' => 'task', 'id' => 9, 'label' => 'Trigger task']],
    'venueTaskTriggers' => [['id' => 9, 'title' => 'Trigger task', 'priority' => 'B']],
    'selectedVenueRating' => null,
    'page' => 1,
    'pageSize' => 25,
    'filter' => '',
    'triggerNotice' => '',
];
$venueHtml = $render($venuePath, $venueVars);
$checkLink($venueHtml, '/booking/app/controllers/team/index.php?tab=tasks&amp;task_id=9', 'Trigger task', 'fa-circle-dot', 'venue detail trigger task');
$checkSingleList($venueHtml, 'venue detail');
$checkEditableModal($venueHtml, 'venue', 55, 'contact,venue,email,task', 'venue detail');
$venueEmpty = $render($venuePath, array_merge($venueVars, ['venueLinks' => [], 'venueTaskTriggers' => []]));
$contains($venueEmpty, 'No links yet', 'venue detail renders the empty state');

exit($failures === 0 ? 0 : 1);
