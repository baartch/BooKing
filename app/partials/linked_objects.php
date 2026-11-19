<?php

function renderLinkedObjects(array $options): string
{
    $items = isset($options['items']) && is_array($options['items']) ? $options['items'] : [];
    $listId = isset($options['listId']) ? (string) $options['listId'] : '';
    $listAttributes = isset($options['listAttributes']) && is_array($options['listAttributes'])
        ? $options['listAttributes']
        : [];
    $emptyText = (string) ($options['emptyText'] ?? 'No links yet');
    $showIcons = (bool) ($options['showIcons'] ?? true);
    $modalId = isset($options['modalId']) ? (string) $options['modalId'] : '';
    $icons = function_exists('getLinkIcons') ? getLinkIcons() : [];
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html = '<span class="detail-link-list"';
    if ($listId !== '') {
        $html .= ' id="' . $escape($listId) . '"';
    }

    $allowedListAttributes = ['data-link-editor-pills-selector'];
    foreach ($allowedListAttributes as $attribute) {
        if (array_key_exists($attribute, $listAttributes)) {
            $html .= ' ' . $attribute . '="' . $escape((string) $listAttributes[$attribute]) . '"';
        }
    }
    $html .= '>';

    if (!$items) {
        $html .= '<span class="has-text-grey is-size-7">' . $escape($emptyText) . '</span>';
    } else {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');
            $label = (string) ($item['label'] ?? '');
            $url = (string) ($item['url'] ?? '');
            $tag = $url !== '' ? 'a' : 'span';
            $html .= '<' . $tag;
            if ($tag === 'a') {
                $html .= ' href="' . $escape($url) . '"';
            }
            $html .= ' class="detail-link-pill">';

            if ($showIcons) {
                $icon = isset($item['icon']) && (string) $item['icon'] !== ''
                    ? (string) $item['icon']
                    : (string) ($icons[$type] ?? 'fa-link');
                $html .= '<span class="icon is-small"><i class="fa-solid ' . $escape($icon) . '"></i></span>';
            }

            $html .= '<span>' . $escape($label) . '</span></' . $tag . '>';
        }
    }

    $html .= '</span>';

    if ($modalId !== '') {
        $escapedModalId = $escape($modalId);
        $html .= '<a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="'
            . $escapedModalId
            . '" title="Edit links" aria-label="Edit links"><span class="icon is-small"><i class="fa-solid fa-pen fa-2xs"></i></span></a>';
    }

    return $html;
}
