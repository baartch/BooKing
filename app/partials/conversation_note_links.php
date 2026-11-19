<?php
if (!empty($noteItem['links'])) {
    $noteLinkItems = [];
    foreach ($noteItem['links'] as $link) {
        $noteLinkItems[] = [
            'type' => $link['type'],
            'label' => $link['label'],
            'url' => buildLinkUrl($link),
        ];
    }
    ?>
    <div class="detail-meta-row"><span class="detail-meta-label">Links:</span><span class="detail-meta-value"><?php echo renderLinkedObjects(['items' => $noteLinkItems]); ?></span></div>
    <?php
}
