# Show Setlist (Songs on Shows) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give team shows an ordered, team-scoped setlist of songs, edited in the show form via growing selects with × removal and drag-to-reorder, displayed as a numbered list on the show detail view.

**Architecture:** Dedicated junction table `team_show_songs` with DB-enforced order, uniqueness, same-team composite FKs, and cascades. The form posts an ordered `show_songs[]` array; the controller validates against the team's songs and replaces the setlist inside the show's existing save transaction. A new TypeScript module (`src/show-songs.ts`) drives the form UI (grow-on-pick, duplicate disabling, × removal, native HTML5 drag reorder), following the existing `src/email.ts` drag-and-drop and `src/link-editor.ts` HTMX-rebind patterns.

**Tech Stack:** PHP 8.0+, MariaDB (InnoDB, utf8mb4_unicode_ci), Bulma, HTMX, TypeScript (strict) built via `bun run build` (tsc under the hood).

**Spec:** `docs/superpowers/specs/2026-10-25-show-songs-setlist-design.md` — the plan argues from the spec; executors read both.

## Global Constraints

- PHP 8.0+; MariaDB/InnoDB, `utf8mb4_unicode_ci` collation on every new table.
- No new third-party dependencies (drag-drop is native HTML5, as in `src/email.ts`).
- Never edit files under `app/public/js/` — all JS behavior lives in `src/*.ts`, built with `bun run build` (outputs to `app/public/js/`, `strict: true`).
- No inline `<script>` in PHP views; the one exception to inline JS that the repo already uses is the `onsubmit="return confirm(...)"` delete pattern in list views.
- All POST flows use existing CSRF helpers (`verifyCsrfToken()`, `renderCsrfField()`).
- Escape all view output with `htmlspecialchars()`; no logging of sensitive data; use existing `logAction()` for show/song mutations.
- **Commits:** repo rule is "do NOT commit unless Andy says so." Run each task's Commit step only if Andy has authorized commits for this session; otherwise skip it and note the skip.
- When a step says "follow the pattern in file X", read X first and mirror its conventions exactly.

## Review Focus

The five inputs/failure modes most likely to bite, each pinned by a test in the owning task:

1. **Duplicate song IDs posted** (duplicate-prevention UI bypassed): server must reject with a friendly validation error, not an `uniq_show_song` SQL exception. → Task 2 tests `normalizeShowSongInput` duplicate case; Task 3 shows the error flash.
2. **Empty / non-numeric entries in `show_songs[]`** (the trailing empty select posts `""`): must be silently filtered so a normal save never fails. → Task 2 tests filter case.
3. **Cross-team song ID posted** (crafted POST): must be rejected with "songs must belong to the selected team", not a FK error. → Task 3 explicit diff check against `fetchTeamSongOptions()`; Task 8 e2e.
4. **Removing every song from an existing show** (empty setlist on update): must actually delete the old rows, not skip the write. → Task 3 `replaceShowSongs` with empty array; Task 8 e2e.
5. **Stale disabled options after × removal**: a song disabled in the other selects must become selectable again once its row is removed. → Task 5 recompute-after-removal step; Task 8 e2e.

---

### Task 1: Database — `team_show_songs` junction table

**Files:**
- Modify: `sql/schema.sql` (add table ~line 284, after `team_songs`; replace two redundant indexes)
- Create: `sql/2026-10-25_team_show_songs.sql` (standalone migration for the live DB)

**Interfaces:**
- Produces: table `team_show_songs(show_id, team_id, song_id, position)` with `UNIQUE(show_id, song_id)`, `UNIQUE(show_id, position)`, cascading composite FKs to `(team_shows.team_id, id)` and `(team_songs.team_id, id)`.

- [ ] **Step 1: Add the junction table to `sql/schema.sql`** (after the `team_songs` table)

```sql
CREATE TABLE team_show_songs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    show_id INT NOT NULL,
    team_id INT NOT NULL,
    song_id INT NOT NULL,
    position INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_team_show_songs_show_song (show_id, song_id),
    UNIQUE KEY uniq_team_show_songs_show_position (show_id, position),
    INDEX idx_team_show_songs_song (song_id),
    INDEX idx_team_show_songs_team (team_id),
    FOREIGN KEY (team_id, show_id) REFERENCES team_shows(team_id, id) ON DELETE CASCADE,
    FOREIGN KEY (team_id, song_id) REFERENCES team_songs(team_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Make the parent composite keys FK-able in `sql/schema.sql`**

In `team_shows`: replace `INDEX idx_team_shows_team (team_id)` with `UNIQUE KEY uniq_team_shows_team (team_id, id)` (the unique key covers everything the old index covered). Same in `team_songs`: replace `INDEX idx_team_songs_team (team_id)` with `UNIQUE KEY uniq_team_songs_team (team_id, id)`. This is what lets the composite FKs enforce same-team membership at the DB level.

- [ ] **Step 3: Create the live-DB migration `sql/2026-10-25_team_show_songs.sql`**

Same `CREATE TABLE` as Step 1, preceded by the two parent `ALTER`s:

```sql
ALTER TABLE team_shows
    DROP INDEX idx_team_shows_team,
    ADD UNIQUE KEY uniq_team_shows_team (team_id, id);
ALTER TABLE team_songs
    DROP INDEX idx_team_songs_team,
    ADD UNIQUE KEY uniq_team_songs_team (team_id, id);
```

- [ ] **Step 4: Apply and verify**

Read DB credentials from `config/config.php`, then run the migration against the local dev DB (e.g. `mariadb <db> < sql/2026-10-25_team_show_songs.sql`). Expected: no errors, and `SHOW CREATE TABLE team_show_songs;` shows both composite FKs. If no local DB is reachable, mark this step deferred and say so in the task summary — Andy applies it on the live DB.

- [ ] **Step 5: Commit** (only if Andy authorized commits)

```bash
git add sql/schema.sql sql/2026-10-25_team_show_songs.sql
git commit -m "feat(shows): add team_show_songs setlist junction table"
```

---

### Task 2: Model — `app/models/team/show_songs.php` + tests

**Files:**
- Create: `app/models/team/show_songs.php`
- Test: `tests/show_songs_test.php`

**Interfaces:**
- Produces (later tasks call exactly these):
  - `normalizeShowSongInput(array $post): array` → `['song_ids' => int[], 'errors' => string[]]` — pure; reads `$post['show_songs']`.
  - `fetchShowSongs(PDO $pdo, int $showId): array` — ordered rows `['id','name','composer','position']` (JOIN `team_songs`, `ORDER BY position`).
  - `fetchTeamSongOptions(PDO $pdo, int $teamId): array` — rows `['id','name','composer']`, `ORDER BY name`.
  - `replaceShowSongs(PDO $pdo, int $showId, int $teamId, array $songIds): void` — DELETE then INSERT positions 1..n; runs inside the caller's transaction (no own begin/commit).
  - `fetchSetlistCountsBySongIds(PDO $pdo, array $songIds): array` — map `songId => COUNT(DISTINCT show_id)`.
- Consumes: Task 1's table.

- [ ] **Step 1: Write the failing test file `tests/show_songs_test.php`**

Follow the conventions of `tests/team_songs_test.php` exactly (plain PHP, `check()` helper, `✓/✗` output, exit code 1 on failure, header comment "Run from CLI: php tests/show_songs_test.php"). Cover `normalizeShowSongInput` (DB functions need a DB; note that in the header like `team_songs_test.php` does):

- `['show_songs' => ['3','7','']]` → `song_ids === [3,7]`, no errors (empty string filtered, order preserved)
- `['show_songs' => ['3','abc','7']]` → non-numeric dropped, `song_ids === [3,7]`, no errors
- `['show_songs' => ['3','3']]` → `song_ids === []` and an error mentioning "once" (e.g. "Each song may only appear once on a show.")
- `['show_songs' => []]` → `song_ids === []`, no errors (empty setlist is valid)
- missing key entirely → same as empty (no PHP notice)
- `['show_songs' => ['0']]` → `song_ids === []`, no errors
- float-like junk `'3.5'` → dropped, no error

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/show_songs_test.php`
Expected: FAIL (function not defined).

- [ ] **Step 3: Implement `app/models/team/show_songs.php`**

Plain function file in the style of `app/models/team/songs.php` (no class, `require_once`-safe). Implement `normalizeShowSongInput` first (pure: cast via `(int)` after an is-whole-number check — accept numeric strings like `'3'`, reject `'3.5'`, `'abc'`, `''`, `'0'`; on duplicates, return empty `song_ids` and one error). Then the four DB functions with the exact signatures above.

- [ ] **Step 4: Run to verify it passes**

Run: `php tests/show_songs_test.php`
Expected: "All tests passed".

- [ ] **Step 5: Commit** (only if Andy authorized commits)

```bash
git add app/models/team/show_songs.php tests/show_songs_test.php
git commit -m "feat(shows): show-songs model with input normalization and setlist queries"
```

---

### Task 3: Controller — setlist save in `shows_save.php`

**Files:**
- Modify: `app/controllers/team/shows_save.php`

**Interfaces:**
- Consumes: `normalizeShowSongInput()`, `fetchTeamSongOptions()`, `replaceShowSongs()` (Task 2).
- Produces: POST contract `show_songs[]` (ordered array of song ID strings, trailing `""` allowed); flash key `team_show_form` now carries `show_songs` (int list) on validation failure.

- [ ] **Step 1: Read the file, then wire the input in**

After the existing `$payload` build, add `$songInput = normalizeShowSongInput($_POST);` and merge `$songInput['errors']` into `$errors`. (This gives duplicate-ID protection and junk filtering; Review Focus #1, #2.)

- [ ] **Step 2: Add the cross-team check** (Review Focus #3)

Inside the `try` (after team access check, before the transaction), diff `normalizeShowSongInput()['song_ids']` against `array_column(fetchTeamSongOptions($pdo, $teamId), 'id')`. On mismatch, treat like the other validation errors: flash + redirect with `notice=show_error&error=All songs must belong to the selected team.` — reuse the file's existing error-redirect block, adding `'show_songs' => $songIds` to the `setFormFlash('team_show_form', ...)` payload. Also add `'show_songs' => $songInput['song_ids']` to the *existing* early error path (date/time/fee validation) so picked songs survive any failed save (spec: preserve on error path; Review Focus #3/#4 test paths rely on it).

- [ ] **Step 3: Persist in both save paths**

In `create_show`: after the link loop inside the existing transaction, call `replaceShowSongs($pdo, $newId, $teamId, $songIds)`. In `update_show`: same call inside its transaction after the UPDATE, with `['show_id'=>$showId,'mode'=>'edit']` error redirects unchanged. `$songIds = $songInput['song_ids']`. With an empty array this must still run (it deletes all rows — Review Focus #4).

- [ ] **Step 4: Verify syntax + tests still pass**

Run: `php -l app/controllers/team/shows_save.php && php tests/show_songs_test.php`
Expected: "No syntax errors" and "All tests passed".

- [ ] **Step 5: Commit** (only if Andy authorized commits)

```bash
git add app/controllers/team/shows_save.php
git commit -m "feat(shows): save ordered setlist with team-scope validation and error-path preservation"
```

---

### Task 4: Views — Songs section in the show form + tab wiring

**Files:**
- Modify: `app/views/team/shows/shows.php` (tab controller-view)
- Modify: `app/views/team/shows/form.php`

**Interfaces:**
- Consumes: `fetchTeamSongOptions()`, `fetchShowSongs()` (Task 2); flash `team_show_form['show_songs']` (Task 3).
- Produces (markup contract consumed by Task 5, exact names):
  - container `<div id="show-songs-editor" data-show-songs-editor>`
  - rows `<div class="show-song-row" data-show-song-row>` each containing: drag handle `<span class="icon show-song-handle">`, `<select name="show_songs[]" class="show-song-select">`, remove `<button type="button" class="show-song-remove">`
  - POST field `show_songs[]` in DOM order.

- [ ] **Step 1: Wire data in `shows.php` tab view**

After `$editShow`/`$formValues` logic: when `$activeTeamId > 0 && isset($pdo)`, compute `$teamSongOptions = fetchTeamSongOptions($pdo, $activeTeamId)`. Compute the prefill list, in priority order: flash `show_songs` if present, else `$isEdit ? fetchShowSongs($pdo, $selectedShowId)` rows, else `[]`; store as `$selectedSongIds` (int list). Merge `show_songs` into the existing `$showFlash` `array_merge` block so a failed save re-renders picks. `form.php` receives `$teamSongOptions`, `$selectedSongIds` (`$selectedSongIds ?? []` guards at top, matching file style).

- [ ] **Step 2: Add the Songs section to `form.php`**

After the existing table-container block, before the Notes field: a `field` with `<label class="label">Songs</label>` and the contract markup above. Server renders one row per `$selectedSongIds` entry plus one trailing empty row (always exactly one trailing empty row; create mode gets just that one). Each select contains `<option value="">Select a song…</option>` plus one option per `$teamSongOptions` — label `Name — Composer`, `htmlspecialchars()`-escaped, `selected` for the row's song. Handle icon: `<i class="fa-solid fa-grip-vertical"></i>`; remove button: Bulma `delete`-style. No `<script>` here — behavior is Task 5.

- [ ] **Step 3: Verify rendering**

Load the page in the dev flow (Task 8's server command) and check: create mode shows one empty select; edit mode shows one select per saved song, each preselected, in setlist order, plus one trailing empty select. View source shows the exact contract attribute names.

- [ ] **Step 4: Commit** (only if Andy authorized commits)

```bash
git add app/views/team/shows/shows.php app/views/team/shows/form.php
git commit -m "feat(shows): songs section in show form with prefill and flash merge"
```

---

### Task 5: TypeScript — `src/show-songs.ts` editor module

**Files:**
- Create: `src/show-songs.ts`
- Modify: `app/views/team/index.php` (add module script include next to the existing `list-panel.js`/`link-editor.js` lines)

**Interfaces:**
- Consumes: Task 4's markup contract (exact attribute/class names listed there).
- Produces: `app/public/js/show-songs.js` via build.

- [ ] **Step 1: Write `src/show-songs.ts`**

Exported init function (e.g. `initShowSongsEditor(): void`) that finds every `[data-show-songs-editor]` and is idempotent (skip already-bound containers via a `data-show-songs-bound` flag, like `src/email.ts`'s `data-email-drag-bound`). Read `src/email.ts` `initEmailDragAndDrop` (lines ~379-440) for the native drag pattern (`draggable`, `dragstart`/`dragover`/`drop`/`dragend`, `is-selected` cue) and `src/link-editor.ts` for the `htmx:afterSwap` re-bind pattern — mirror both. Behavior:

- **Recompute** (call on init and after every mutation): for each `select.show-song-select`, disable every option whose value is selected in a *different* row's select; options selected by that select stay enabled. (Review Focus #5.)
- **Grow**: on change of the last row where a song was picked, append a new empty row (clone the trailing row's structure, clear its select) so there's always exactly one trailing empty row; recompute.
- **Remove**: clicking `.show-song-remove` drops that row; if no rows remain, append one empty row; recompute.
- **Drag**: filled rows get `draggable="true"` (trailing empty row never draggable); `drop` on a row inserts the dragged row at the target's position (insertBefore); `dragend` clears the visual cue; recompute after drop. Desktop mouse only (spec confirms no touch/keyboard support).
- No form submit changes — `show_songs[]` in DOM order is the post order.

`strict: true` passes; DOM types only, no dependencies.

- [ ] **Step 2: Build**

Run: `bun run build`
Expected: compiles clean; `app/public/js/show-songs.js` exists (never hand-edit it).

- [ ] **Step 3: Include the module**

In `app/views/team/index.php`, add the `<script type="module" src="…/js/show-songs.js" defer>` line beside the existing module includes (same construction, `BASE_PATH`-based).

- [ ] **Step 4: Verify in browser**

On the show form: pick a song → second select appears; picked song is disabled in the other selects; × removes and the song reappears in other selects (Review Focus #5); drag row 1 onto row 2 → order swaps visually; submit posts in the new order. After HTMX swap back into the form (list → edit another show), the editor still works (re-bind check).

- [ ] **Step 5: Commit** (only if Andy authorized commits)

```bash
git add src/show-songs.ts app/views/team/index.php app/public/js/show-songs.js
git commit -m "feat(shows): setlist editor with grow-on-pick, duplicate lockout, drag reorder"
```

---

### Task 6: Detail view — numbered setlist

**Files:**
- Modify: `app/views/team/shows/shows.php` (fetch, when `$activeShow` and not form mode)
- Modify: `app/views/team/shows/detail.php` (render)

**Interfaces:**
- Consumes: `fetchShowSongs()` (Task 2).

- [ ] **Step 1: Fetch in `shows.php`**

When `$activeShow` is resolved and the panel shows detail (not form), compute `$activeShowSongs = fetchShowSongs($pdo, (int) $activeShow['id'])`. `detail.php` reads it with `$activeShowSongs ?? []`.

- [ ] **Step 2: Render in `detail.php`**

Below the `detail-meta` block, add a "Setlist" block: label `Setlist:`, then if songs exist an `<ol>` (Bulma content styling) with items `Name — Composer` (escaped); if empty, `<span class="has-text-grey is-size-7">No songs yet</span>`. Match the existing meta-row markup style.

- [ ] **Step 3: Verify in browser**

Open a show with and one without songs: numbered list in position order / grey "No songs yet".

- [ ] **Step 4: Commit** (only if Andy authorized commits)

```bash
git add app/views/team/shows/shows.php app/views/team/shows/detail.php
git commit -m "feat(shows): numbered setlist on show detail view"
```

---

### Task 7: Song deletion warning

**Files:**
- Modify: `app/views/team/songs/songs.php` (tab view — compute counts)
- Modify: `app/views/team/songs/list.php` (confirm message)
- Modify: `app/controllers/team/songs_delete.php` (no behavior change needed — cascade remains — but see step 3)

**Interfaces:**
- Consumes: `fetchSetlistCountsBySongIds()` (Task 2).

- [ ] **Step 1: Compute counts in `songs.php`**

Read the file first. After the songs list is loaded, if `$pdo` is available compute `$songSetlistCounts = fetchSetlistCountsBySongIds($pdo, array_column($songs, 'id'))` and pass it to `list.php` (guard `?? []` there).

- [ ] **Step 2: Warning in the delete confirm in `list.php`**

The delete form currently uses `onsubmit="return confirm('Delete this song?');"`. Build the message server-side per row: when the song's count > 0, `Delete this song? It appears on N show setlists.` (singular "setlist" when N=1); otherwise keep the current message. Same inline-confirm convention, data-driven wording.

- [ ] **Step 3: Confirm controller behavior**

Verify `songs_delete.php` needs no change: deleting proceeds after the confirm and the FK cascade removes setlist rows (Task 1). If you find songs are deletable through any other endpoint (search for `DELETE FROM team_songs`), apply the same guarantee there or flag it in the summary.

- [ ] **Step 4: Verify in browser**

A song on a show's setlist shows the count message; declining keeps the song; accepting removes it from the song list and from the show's detail setlist (reload show detail).

- [ ] **Step 5: Commit** (only if Andy authorized commits)

```bash
git add app/views/team/songs/songs.php app/views/team/songs/list.php
git commit -m "feat(songs): warn when deleting a song used on setlists"
```

---

### Task 8: End-to-end smoke test

**Files:** none (verification only)

- [ ] **Step 1: Prepare**

DB migrated (Task 1 step 4), `bun run build` current, dev server running the way Andy usually runs it (e.g. `php -S` with `index.php`, or the existing dev helper in `dev_helpers/`).

- [ ] **Step 2: Walk the checklist**

1. Create a show with songs A, B, C picked in that order → detail shows `1. A 2. B 3. C`.
2. Edit the show, drag C above A, submit → detail shows `1. C 2. A 3. B`.
3. Edit, remove all three, submit → detail shows "No songs yet" (Review Focus #4).
4. Edit, submit the form with an invalid date → error flash and the picked songs are still in the form (Review Focus #3 path).
5. Craft a POST with a duplicate `show_songs[]` entry → friendly "may only appear once" error, no SQL error (Review Focus #1).
6. Craft a POST with another team's song ID → "must belong to the selected team" (Review Focus #3).
7. Delete a song used on 2 setlists → confirm mentions both; confirm delete; both setlists drop it.
8. Songs dropdown only ever offers the active team's songs.
9. All `php tests/*.php` still pass; `bun run build` clean.

- [ ] **Step 3: Report**

Summarize results per checklist item, plus any deferred steps (e.g. live-DB migration) for Andy.

---

## Self-Review Notes

- Spec coverage: dedicated table (T1), team-scope/once/order constraints (T1+T2+T3), form UI grow/×/drag (T4+T5), detail list (T6), preserve-on-error (T3+T4), deletion warning (T7), no migration system → standalone SQL (T1), no new deps / native DnD (T5). Spec's "calls left to builder" are all decided in tasks (placement: before Notes; composite-FK chosen; disable-in-place chosen).
- Types consistent: `normalizeShowSongInput/fetchShowSongs/fetchTeamSongOptions/replaceShowSongs/fetchSetlistCountsBySongIds` names identical across T2-T7; markup contract names identical in T4/T5.
- Proportion: spec ≈ 1 page, plan ≈ 6 tasks — bodies omitted where signatures + tests determine them.
