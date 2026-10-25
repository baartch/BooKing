<?php
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$songSetlistCounts = $songSetlistCounts ?? [];

$listColumns = [
    buildListColumn('Name', 'name'),
    buildListColumn('Duration', null, static function (array $song): string {
        $duration = formatSongDuration(isset($song['duration_seconds']) ? (int) $song['duration_seconds'] : null);
        return $duration !== '' ? $duration : '—';
    }),
    buildListColumn('Composer', 'composer'),
    buildListColumn('Arranger', 'arranger'),
];

$listRows = $songs;
$listEmptyMessage = 'No songs found.';

$listRowLink = static function (array $song) use ($baseUrl, $baseQuery): array {
    $songId = (int) ($song['id'] ?? 0);
    $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['song_id' => $songId]));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#team-song-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink,
    ];
};

$listRowActions = static function (array $song) use ($baseUrl, $baseQuery, $activeTeamId, $searchQuery, $songSetlistCounts): string {
    $songId = (int) ($song['id'] ?? 0);
    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, [
        'song_id' => $songId,
        'mode' => 'edit',
    ]));

    $setlistCount = (int) ($songSetlistCounts[$songId] ?? 0);
    $confirmMessage = $setlistCount > 0
        ? sprintf('Delete this song? It appears on %d show setlist%s.', $setlistCount, $setlistCount === 1 ? '' : 's')
        : 'Delete this song?';

    ob_start();
    ?>
    <div class="buttons are-small is-justify-content-flex-end">
      <a href="<?php echo htmlspecialchars($editLink); ?>" class="button" aria-label="Edit song" title="Edit song" data-list-ignore>
        <span class="icon"><i class="fa-solid fa-pen"></i></span>
      </a>
      <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/team/songs_delete.php" onsubmit="return confirm('<?php echo htmlspecialchars($confirmMessage, ENT_QUOTES); ?>');" data-list-ignore>
        <?php renderCsrfField(); ?>
        <input type="hidden" name="song_id" value="<?php echo (int) $songId; ?>">
        <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
        <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
        <button type="submit" class="button" aria-label="Delete song" title="Delete song">
          <span class="icon"><i class="fa-solid fa-trash"></i></span>
        </button>
      </form>
    </div>
    <?php

    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../../partials/tables/table.php';
