<?php

require_once __DIR__ . '/../app/models/core/link_helpers.php';

$rendererPath = __DIR__ . '/../app/partials/linked_objects.php';
if (is_file($rendererPath)) {
    require_once $rendererPath;
}

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? '✓ ' : '✗ ') . $label . "\n";
    $failures += $condition ? 0 : 1;
};

$check(function_exists('renderLinkedObjects'), 'renderLinkedObjects renderer exists');

if (function_exists('renderLinkedObjects')) {
    $empty = renderLinkedObjects([]);
    $check(str_contains($empty, 'No links yet'), 'empty list renders the default empty text');
    $check(!str_contains($empty, 'detail-link-edit'), 'null modal ID omits the pencil');

    $customEmpty = renderLinkedObjects(['emptyText' => 'Nothing linked']);
    $check(str_contains($customEmpty, 'Nothing linked'), 'empty list supports caller-provided empty text');

    $one = renderLinkedObjects([
        'items' => [
            ['type' => 'contact', 'id' => 7, 'label' => 'Ada', 'url' => '/contact.php?id=7']
        ]
    ]);
    $check(str_contains($one, '<a href="/contact.php?id=7" class="detail-link-pill">'), 'nonempty URL renders a clickable pill');
    $check(str_contains($one, 'fa-solid fa-user') && str_contains($one, '>Ada</span>'), 'pill renders default type icon and label');

    $displayOnly = renderLinkedObjects([
        'items' => [
            ['type' => 'contact', 'id' => 8, 'label' => 'Display only']
        ]
    ]);
    $check(str_contains($displayOnly, '<span class="detail-link-pill">') && !str_contains($displayOnly, '<a '), 'missing URL renders a display-only pill');

    $multiple = renderLinkedObjects([
        'items' => [
            ['type' => 'contact', 'id' => 1, 'label' => 'First'],
            ['type' => 'venue', 'id' => 2, 'label' => 'Second']
        ]
    ]);
    $check(substr_count($multiple, 'class="detail-link-pill"') === 2, 'multiple items render as separate pills');
    $check(strpos($multiple, 'First') < strpos($multiple, 'Second'), 'multiple items preserve input order');

    $withoutIcons = renderLinkedObjects([
        'items' => [
            ['type' => 'contact', 'id' => 9, 'label' => 'No icon']
        ],
        'showIcons' => false
    ]);
    $check(!str_contains($withoutIcons, 'fa-solid') && str_contains($withoutIcons, 'No icon'), 'showIcons false omits icons while preserving labels');

    $unknown = renderLinkedObjects([
        'items' => [
            ['type' => 'other', 'id' => 10, 'label' => 'Other']
        ]
    ]);
    $check(str_contains($unknown, 'fa-solid fa-link'), 'unknown type uses the fallback link icon');

    $override = renderLinkedObjects([
        'items' => [
            ['type' => 'task', 'id' => 11, 'label' => 'Trigger task', 'icon' => 'fa-circle-dot']
        ]
    ]);
    $check(str_contains($override, 'fa-solid fa-circle-dot') && !str_contains($override, 'fa-list-check'), 'item icon override replaces the default task icon');

    $editable = renderLinkedObjects([
        'items' => [],
        'modalId' => 'link-editor-contact-12',
        'listId' => 'contact-link-pills',
        'listAttributes' => [
            'data-link-editor-pills-selector' => '#contact-link-pills&x',
            'onclick' => 'alert(1)'
        ]
    ]);
    $check(str_contains($editable, 'id="contact-link-pills"') && str_contains($editable, 'data-link-editor-pills-selector="#contact-link-pills&amp;x"'), 'list ID and escaped fixed data attributes render on the list');
    $check(!str_contains($editable, 'onclick'), 'unapproved list attributes are omitted');
    $check(str_contains($editable, 'data-link-editor-trigger') && str_contains($editable, 'data-link-editor-modal-id="link-editor-contact-12"'), 'pencil targets the configured modal');
    $check(str_contains($editable, 'title="Edit links"') && str_contains($editable, 'aria-label="Edit links"'), 'pencil has accessible title and name');

    $emailList = renderLinkedObjects([
        'listAttributes' => [
            'data-team-id' => '7',
            'data-contact-url-base' => '/communication.php?x=1&y=2',
            'data-venue-url-base' => '/venues.php',
            'data-email-url-base' => '/email.php',
            'data-task-url-base' => '/team.php',
            'data-conversation-url-base' => '/conversations.php',
            'data-untrusted-url' => 'javascript:alert(1)',
        ],
    ]);
    $check(
        str_contains($emailList, 'data-team-id="7"')
            && str_contains($emailList, 'data-contact-url-base="/communication.php?x=1&amp;y=2"')
            && str_contains($emailList, 'data-venue-url-base="/venues.php"')
            && str_contains($emailList, 'data-email-url-base="/email.php"')
            && str_contains($emailList, 'data-task-url-base="/team.php"')
            && str_contains($emailList, 'data-conversation-url-base="/conversations.php"'),
        'email list preserves the fixed URL and team data attributes with escaping'
    );
    $check(!str_contains($emailList, 'data-untrusted-url'), 'unapproved URL data attributes are omitted');
    $check(!str_contains($emailList, '&amp;amp;'), 'email list attributes are not double-escaped');

    $escaped = renderLinkedObjects([
        'items' => [
            ['type' => 'contact', 'id' => 13, 'label' => 'A <B> & "C"', 'url' => '/search.php?q=a&other=b']
        ]
    ]);
    $check(str_contains($escaped, 'A &lt;B&gt; &amp; &quot;C&quot;'), 'label HTML characters are escaped exactly once');
    $check(str_contains($escaped, 'href="/search.php?q=a&amp;other=b"'), 'URL query ampersand is escaped exactly once');
    $check(!str_contains($escaped, '&amp;lt;') && !str_contains($escaped, '&amp;amp;'), 'escaped label and URL are not double-escaped');
}

exit($failures === 0 ? 0 : 1);
