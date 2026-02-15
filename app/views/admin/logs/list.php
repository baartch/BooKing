<?php
$listRows = $listRows ?? [];
$listColumns = $listColumns ?? [];
$listEmptyMessage = $listEmptyMessage ?? 'No logs found.';
$listRowLink = $listRowLink ?? null;
$listRowActions = null;
$listActionsLabel = '';
$listRowClass = static function (array $row) use ($selectedLogId): string {
    return (int) ($row['id'] ?? 0) === $selectedLogId ? 'is-active' : '';
};
?>

<?php require __DIR__ . '/../../../partials/tables/list.php'; ?>
