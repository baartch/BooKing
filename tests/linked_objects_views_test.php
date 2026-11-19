<?php

$viewRoot = __DIR__ . '/../app/views/';
$viewSources = [
    'contacts detail' => 'communication/contacts_detail.php',
    'contacts form' => 'communication/contacts_form.php',
    'email detail' => 'communication/email_detail.php',
    'conversation note timeline' => 'communication/conversations.php',
    'show detail' => 'team/shows/detail.php',
    'task detail' => 'team/tasks/detail.php',
    'task form' => 'team/tasks/form.php',
    'venue detail' => 'venues/venues/detail.php',
];

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? '✓ ' : '✗ ') . $label . "\n";
    $failures += $condition ? 0 : 1;
};

$sources = [];
foreach ($viewSources as $label => $path) {
    $source = file_get_contents($viewRoot . $path);
    $sources[$label] = $source === false ? '' : $source;
    $check(str_contains($sources[$label], 'renderLinkedObjects('), "$label uses the shared linked-object renderer");
    $check(str_contains($sources[$label], 'detail-meta-value detail-link-list'), "$label keeps its surrounding link layout wrapper");
}

$contains = static function (string $view, string $text, string $label) use (&$check, &$sources): void {
    $check(str_contains($sources[$view], $text), $label);
};

$contains('contacts detail', "'url' => buildLinkUrl(\$link)", 'contact detail links keep the shared destination builder');
$contains('contacts detail', "'link-editor-contact-' . (int) \$activeContact['id']", 'contact detail keeps its existing modal ID');
$contains('contacts detail', "\$linkEditorSourceType = 'contact'", 'contact detail keeps its modal source type');
$contains('contacts detail', "\$linkEditorSourceId = (int) \$activeContact['id']", 'contact detail keeps its modal source ID');
$contains('contacts detail', "'contact,venue,email,task'", 'contact detail keeps its link search types');
$contains('contacts form', "'link-editor-contact-' . (int) (\$editContact['id'] ?? 0)", 'contact form keeps its edit-only modal ID');
$contains('contacts form', "\$linkEditorSourceType = 'contact'", 'contact form keeps its modal source type');
$contains('contacts form', "\$linkEditorSourceId = (int) \$editContact['id']", 'contact form keeps its modal source ID');
$contains('contacts form', "'/app/controllers/communication/index.php?tab=contacts&contact_id='", 'contact form keeps contact destinations');
$contains('contacts form', "'/app/controllers/venues/index.php?q=' . urlencode(\$link['label'])", 'contact form keeps its label-search venue destination');
$contains('contacts form', "'/app/controllers/communication/index.php?tab=email&message_id='", 'contact form keeps email destinations');
$contains('contacts form', "'/app/controllers/team/index.php?tab=tasks&task_id='", 'contact form keeps task destinations');
$contains('contacts form', "'contact,venue,email,task'", 'contact form keeps its link search types');
$contains('contacts form', '$isEdit && $editContact', 'contact form keeps link editing edit-only');

$contains('email detail', "'url' => buildLinkUrl(\$link)", 'email detail keeps the shared destination builder');
$contains('email detail', "'type' => 'conversation'", 'email detail keeps the conversation pill');
$contains('email detail', "'link-editor-email-' . (int) \$message['id']", 'email detail keeps its existing modal ID');
$contains('email detail', "\$linkEditorSourceType = 'email'", 'email detail keeps its modal source type');
$contains('email detail', "\$linkEditorSourceId = (int) \$message['id']", 'email detail keeps its modal source ID');
$contains('email detail', "\$linkEditorMailboxId = (int) \$selectedMailbox['id']", 'email detail keeps its mailbox source ID');
$contains('email detail', "?tab=conversations&conversation_id='", 'email detail keeps conversation destinations');
$contains('email detail', "'contact,venue,email,task'", 'email detail keeps its link search types');

$noteCallStart = strpos($sources['conversation note timeline'], 'renderLinkedObjects(');
$noteCallEnd = $noteCallStart === false ? false : strpos($sources['conversation note timeline'], ']);', $noteCallStart);
$noteCall = $noteCallStart !== false && $noteCallEnd !== false
    ? substr($sources['conversation note timeline'], $noteCallStart, $noteCallEnd - $noteCallStart)
    : '';
$check(str_contains($sources['conversation note timeline'], "if (!empty(\$noteItem['links']))"), 'read-only note links remain hidden when empty');
$check(str_contains($sources['conversation note timeline'], 'buildLinkUrl($link)'), 'read-only note links keep their existing destinations');
$check(!str_contains($noteCall, 'modalId'), 'read-only note pills do not get an edit pencil');

$contains('show detail', "'url' => buildLinkUrl(\$link)", 'show detail keeps the shared destination builder');
$contains('show detail', "'link-editor-show-' . (int) \$activeShow['id']", 'show detail keeps its existing modal ID');
$contains('show detail', "\$linkEditorSourceType = 'show'", 'show detail keeps its modal source type');
$contains('show detail', "\$linkEditorSourceId = (int) \$activeShow['id']", 'show detail keeps its modal source ID');
$contains('show detail', "'contact,venue,email,task,show'", 'show detail keeps its link search types');

$contains('task detail', "'url' => buildLinkUrl(\$link)", 'task detail keeps the shared destination builder');
$contains('task detail', "'link-editor-task-' . (int) \$activeTask['id']", 'task detail keeps its existing modal ID');
$contains('task detail', "\$linkEditorSourceType = 'task'", 'task detail keeps its modal source type');
$contains('task detail', "\$linkEditorSourceId = (int) \$activeTask['id']", 'task detail keeps its modal source ID');
$contains('task detail', "'contact,venue,email,task'", 'task detail keeps its link search types');

$contains('task form', "'/app/controllers/communication/index.php?tab=contacts&contact_id='", 'task form keeps contact destinations');
$contains('task form', "'/app/controllers/venues/index.php?q=' . urlencode(\$link['label'])", 'task form keeps its label-search venue destination');
$contains('task form', "'/app/controllers/communication/index.php?tab=email&message_id='", 'task form keeps email destinations');
$contains('task form', "'/app/controllers/team/index.php?tab=tasks&task_id='", 'task form keeps task destinations');
$contains('task form', "'link-editor-task-' . (int) (\$editTask['id'] ?? 0)", 'task form keeps its edit-only modal ID');
$contains('task form', "\$linkEditorSourceType = 'task'", 'task form keeps its modal source type');
$contains('task form', "\$linkEditorSourceId = (int) \$editTask['id']", 'task form keeps its modal source ID');
$contains('task form', "'contact,venue,email,task'", 'task form keeps its link search types');
$contains('task form', '$isEdit && $editTask', 'task form keeps link editing edit-only');
$contains('task form', 'data-link-editor-collector', 'task form keeps its form link collector');

$contains('venue detail', "'url' => buildLinkUrl(\$link)", 'venue detail keeps the shared destination builder');
$contains('venue detail', "'link-editor-venue-' . (int) \$activeVenue['id']", 'venue detail keeps its existing modal ID');
$contains('venue detail', "\$linkEditorSourceType = 'venue'", 'venue detail keeps its modal source type');
$contains('venue detail', "\$linkEditorSourceId = (int) \$activeVenue['id']", 'venue detail keeps its modal source ID');
$contains('venue detail', "'contact,venue,email,task'", 'venue detail keeps its link search types');
$contains('venue detail', "if (\$linkItem['type'] === 'task' && in_array(\$linkItem['id'], \$triggerTaskIds, true))", 'venue trigger icon applies only to linked trigger tasks');
$contains('venue detail', "\$linkItem['icon'] = 'fa-circle-dot'", 'venue task triggers keep their special icon');
$contains('venue detail', "buildDetailRow('Links'", 'venue links remain embedded in the existing detail row');

exit($failures === 0 ? 0 : 1);
