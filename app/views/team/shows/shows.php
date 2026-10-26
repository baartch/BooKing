<?php
require_once __DIR__ . '/../../../models/core/form_helpers.php';
require_once __DIR__ . '/../../../models/team/show_songs.php';

$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$mode = (string) ($_GET['mode'] ?? '');
$selectedShowId = (int) ($_GET['show_id'] ?? 0);
$searchQuery = $searchQuery ?? '';
$activeTeamId = (int) ($activeTeamId ?? 0);
$showLinks = $showLinks ?? [];
$baseQuery = array_filter([
    'tab' => 'shows',
    'team_id' => $activeTeamId > 0 ? $activeTeamId : null,
    'q' => $searchQuery !== '' ? $searchQuery : null,
], static fn($value) => $value !== null && $value !== '');
$activeShow = null;

if ($selectedShowId > 0) {
    foreach ($shows as $show) {
        if ((int) ($show['id'] ?? 0) === $selectedShowId) {
            $activeShow = $show;
            break;
        }
    }

    if (!$activeShow && isset($pdo) && $activeTeamId > 0) {
        try {
            $activeShow = fetchTeamShow($pdo, $activeTeamId, $selectedShowId);
        } catch (Throwable $error) {
            $activeShow = null;
        }
    }
}

$isEdit = $mode === 'edit' && $selectedShowId > 0 && $activeShow !== null;
$showForm = $mode === 'new' || $isEdit;
$editShow = $showForm && $isEdit ? $activeShow : null;

$formValues = [
    'name' => '',
    'show_date' => '',
    'show_time' => '',
    'venue_text' => '',
    'artist_fee' => '',
    'entrance_fee' => '',
    'notes' => '',
];

if ($editShow) {
    $formValues = [
        'name' => (string) ($editShow['name'] ?? ''),
        'show_date' => (string) ($editShow['show_date'] ?? ''),
        'show_time' => (string) ($editShow['show_time'] ?? ''),
        'venue_text' => (string) ($editShow['venue_text'] ?? ''),
        'artist_fee' => (string) ($editShow['artist_fee'] ?? ''),
        'entrance_fee' => (string) ($editShow['entrance_fee'] ?? ''),
        'notes' => (string) ($editShow['notes'] ?? ''),
    ];
}

// Re-populate the form with the values of a failed submission (flash).
// Unsaved link-editor links are not retained; saved links still show.
$showFlash = takeFormFlash('team_show_form');
if ($showFlash !== null && $showForm) {
    $formValues = array_merge($formValues, [
        'name' => (string) ($showFlash['name'] ?? $formValues['name']),
        'show_date' => (string) ($showFlash['show_date'] ?? $formValues['show_date']),
        'show_time' => (string) ($showFlash['show_time'] ?? $formValues['show_time']),
        'venue_text' => (string) ($showFlash['venue_text'] ?? $formValues['venue_text']),
        'artist_fee' => (string) ($showFlash['artist_fee'] ?? $formValues['artist_fee']),
        'entrance_fee' => (string) ($showFlash['entrance_fee'] ?? $formValues['entrance_fee']),
        'notes' => (string) ($showFlash['notes'] ?? $formValues['notes']),
    ]);
}

// Songs for the setlist editor: flash (failed save) wins, then the saved setlist.
$teamSongOptions = [];
$selectedSongIds = [];
if ($activeTeamId > 0 && isset($pdo) && $showForm) {
    try {
        $teamSongOptions = fetchTeamSongOptions($pdo, $activeTeamId);
    } catch (Throwable $error) {
        $teamSongOptions = [];
    }

    if ($showFlash !== null && is_array($showFlash['show_songs'] ?? null)) {
        $selectedSongIds = array_map('intval', $showFlash['show_songs']);
    } elseif ($isEdit) {
        try {
            $selectedSongIds = array_map(
                static fn (array $row): int => (int) $row['id'],
                fetchShowSongs($pdo, $selectedShowId)
            );
        } catch (Throwable $error) {
            $selectedSongIds = [];
        }
    }
}

// Setlist for the detail panel (not shown in form mode).
$activeShowSongs = [];
if (!$showForm && $activeShow && isset($pdo)) {
    try {
        $activeShowSongs = fetchShowSongs($pdo, (int) $activeShow['id']);
    } catch (Throwable $error) {
        $activeShowSongs = [];
    }
}

$cancelQuery = array_filter([
    'tab' => 'shows',
    'team_id' => $activeTeamId > 0 ? $activeTeamId : null,
    'show_id' => $selectedShowId > 0 ? $selectedShowId : null,
    'q' => $searchQuery !== '' ? $searchQuery : null,
], static fn($value) => $value !== null && $value !== '');
$cancelUrl = $baseUrl . '?' . http_build_query($cancelQuery);

$listTitle = 'Shows';
$listSummaryTags = [sprintf('%d shows', count($shows))];
$addShowUrl = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mode' => 'new']));
$listPrimaryActionHtml = '<a href="' . htmlspecialchars($addShowUrl) . '" class="button is-primary">Add Show</a>';

$listSearch = [
    'action' => $baseUrl,
    'inputName' => 'q',
    'inputValue' => $searchQuery,
    'placeholder' => 'Search shows…',
    'inputId' => 'show-filter',
    'hiddenFields' => [
        'tab' => 'shows',
        'team_id' => $activeTeamId,
    ],
];

$listContentPath = __DIR__ . '/list.php';
$detailContentPath = $showForm ? __DIR__ . '/form.php' : __DIR__ . '/detail.php';
$detailWrapperId = 'team-show-detail-panel';

if (HTMX::isRequest()) {
    require $detailContentPath;
    return;
}
?>

<div class="tab-panel <?php echo $activeTab === 'shows' ? '' : 'is-hidden'; ?>" data-tab-panel="shows" role="tabpanel">
  <?php if (!empty($notice['shows'])): ?>
    <div class="notification"><?php echo htmlspecialchars($notice['shows']); ?></div>
  <?php endif; ?>

  <?php foreach (($errors['shows'] ?? []) as $error): ?>
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
                    'tab' => 'shows',
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
        <p class="help">Shows are scoped to the selected team.</p>
      </div>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/../../../partials/tables/two_column.php'; ?>
</div>
