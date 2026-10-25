<?php
require_once __DIR__ . '/../../../models/core/form_helpers.php';
require_once __DIR__ . '/../../../models/team/show_songs.php';

$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$mode = (string) ($_GET['mode'] ?? '');
$selectedSongId = (int) ($_GET['song_id'] ?? 0);
$searchQuery = $searchQuery ?? '';
$activeTeamId = (int) ($activeTeamId ?? 0);
$baseQuery = array_filter([
    'tab' => 'songs',
    'team_id' => $activeTeamId > 0 ? $activeTeamId : null,
    'q' => $searchQuery !== '' ? $searchQuery : null,
], static fn($value) => $value !== null && $value !== '');
$activeSong = null;

if ($selectedSongId > 0) {
    foreach ($songs as $song) {
        if ((int) ($song['id'] ?? 0) === $selectedSongId) {
            $activeSong = $song;
            break;
        }
    }

    if (!$activeSong && isset($pdo) && $activeTeamId > 0) {
        try {
            $activeSong = fetchTeamSong($pdo, $activeTeamId, $selectedSongId);
        } catch (Throwable $error) {
            $activeSong = null;
        }
    }
}

$isEdit = $mode === 'edit' && $selectedSongId > 0 && $activeSong !== null;
$songForm = $mode === 'new' || $isEdit;
$editSong = $songForm && $isEdit ? $activeSong : null;

$formValues = [
    'name' => '',
    'duration' => '',
    'composer' => '',
    'arranger' => '',
];

if ($editSong) {
    $formValues = [
        'name' => (string) ($editSong['name'] ?? ''),
        'duration' => formatSongDuration(isset($editSong['duration_seconds']) ? (int) $editSong['duration_seconds'] : null),
        'composer' => (string) ($editSong['composer'] ?? ''),
        'arranger' => (string) ($editSong['arranger'] ?? ''),
    ];
}

// Re-populate the form with the values of a failed submission (flash).
$songFlash = takeFormFlash('team_song_form');
if ($songFlash !== null && $songForm) {
    $formValues = array_merge($formValues, [
        'name' => (string) ($songFlash['name'] ?? $formValues['name']),
        'duration' => (string) ($songFlash['duration'] ?? $formValues['duration']),
        'composer' => (string) ($songFlash['composer'] ?? $formValues['composer']),
        'arranger' => (string) ($songFlash['arranger'] ?? $formValues['arranger']),
    ]);
}

$cancelQuery = array_filter([
    'tab' => 'songs',
    'team_id' => $activeTeamId > 0 ? $activeTeamId : null,
    'song_id' => $selectedSongId > 0 ? $selectedSongId : null,
    'q' => $searchQuery !== '' ? $searchQuery : null,
], static fn($value) => $value !== null && $value !== '');
$cancelUrl = $baseUrl . '?' . http_build_query($cancelQuery);

$listTitle = 'Songs';
$listSummaryTags = [sprintf('%d songs', count($songs))];
$addSongUrl = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mode' => 'new']));
$listPrimaryActionHtml = '<a href="' . htmlspecialchars($addSongUrl) . '" class="button is-primary">Add Song</a>';

$listSearch = [
    'action' => $baseUrl,
    'inputName' => 'q',
    'inputValue' => $searchQuery,
    'placeholder' => 'Search songs…',
    'inputId' => 'song-filter',
    'hiddenFields' => [
        'tab' => 'songs',
        'team_id' => $activeTeamId,
    ],
];

$listContentPath = __DIR__ . '/list.php';
$detailContentPath = $songForm ? __DIR__ . '/form.php' : __DIR__ . '/detail.php';
$detailWrapperId = 'team-song-detail-panel';

// Setlist usage per song, for the delete confirmation in the list.
$songSetlistCounts = [];
if ($activeTeamId > 0 && isset($pdo) && $songs !== []) {
    try {
        $songSetlistCounts = fetchSetlistCountsBySongIds($pdo, array_column($songs, 'id'));
    } catch (Throwable $error) {
        $songSetlistCounts = [];
    }
}

// Shows whose setlist includes the selected song, for the detail row.
$songSetlistShows = [];
if ($activeTeamId > 0 && $activeSong !== null && isset($pdo)) {
    try {
        $songSetlistShows = fetchShowsForSong($pdo, $activeTeamId, (int) ($activeSong['id'] ?? 0));
    } catch (Throwable $error) {
        $songSetlistShows = [];
    }
}

if (HTMX::isRequest()) {
    require $detailContentPath;
    return;
}
?>

<div class="tab-panel <?php echo $activeTab === 'songs' ? '' : 'is-hidden'; ?>" data-tab-panel="songs" role="tabpanel">
  <?php if (!empty($notice['songs'])): ?>
    <div class="notification"><?php echo htmlspecialchars($notice['songs']); ?></div>
  <?php endif; ?>

  <?php foreach (($errors['songs'] ?? []) as $error): ?>
    <div class="notification"><?php echo htmlspecialchars($error); ?></div>
  <?php endforeach; ?>

  <?php if ($activeTeamId > 0 && isset($userTeams) && count($userTeams) > 1): ?>
    <div class="box">
      <div class="field">
        <label class="label">Team</label>
        <div class="control">
          <div class="select">
            <select onchange="window.location.href=this.value;">
              <?php foreach ($userTeams as $team): ?>
                <?php
                  $teamId = (int) ($team['id'] ?? 0);
                  $teamUrl = $baseUrl . '?' . http_build_query(array_filter([
                    'tab' => 'songs',
                    'team_id' => $teamId,
                    'q' => $searchQuery !== '' ? $searchQuery : null,
                  ], static fn($value) => $value !== null && $value !== ''));
                ?>
                <option value="<?php echo htmlspecialchars($teamUrl); ?>" <?php echo $teamId === (int) $activeTeamId ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string) ($team['name'] ?? ('Team #' . $teamId))); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <p class="help">Songs are scoped to the selected team.</p>
      </div>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/../../../partials/tables/two_column.php'; ?>
</div>
