<?php
$detailRows = $detailRows ?? [];
$detailTitle = $detailTitle ?? null;
$detailSubtitle = $detailSubtitle ?? null;
$detailEmptyMessage = $detailEmptyMessage ?? 'Select a log entry to see details.';
$detailActionsHtml = null;
?>

<?php require __DIR__ . '/../../../partials/tables/detail.php'; ?>
