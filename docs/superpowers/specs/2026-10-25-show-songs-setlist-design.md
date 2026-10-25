# Show Setlist (Songs on Shows) — Design

Date: 2026-10-25
Status: Approved in conversation by Andy (human partner)

## Intent and why

Teams recently got Songs (`team_songs`). Shows (`team_shows`) now need to carry the
setlist: which songs a show contains, and in which order they are played. This is
*not* app-wide linking (`object_links`) — the setlist is Show-owned data, ordered,
team-scoped, and belongs to the Show add/edit/detail UI only.

Why not the app-wide link mechanism: `object_links` is an unordered, polymorphic
pair table (unique key on the whole tuple, no position column, no FK to
`team_songs`). It cannot express setlist order or "song once per show" without
modifying the shared mechanism for every other link consumer, so the human
partner explicitly chose a dedicated table over both app-wide linking and a JSON
column.

## Goals

- One show has multiple songs, each at most once (a song cannot appear twice on
  the same show).
- Order matters: the list is a setlist; song 3 plays third.
- Songs offered are only the ones belonging to the show's team.
- Show add/edit form contains the setlist editor; Show detail view shows the
  numbered setlist.
- Deleting a show or a song cleans up setlist rows automatically (no orphan
  cleanup code).

## Non-goals

- No changes to the app-wide linking mechanism (`object_links`, `link_scope.php`,
  link editor modal) beyond leaving them untouched.
- No cross-team song references, ever.
- No song duration totals or playback features.
- No touch/keyboard drag support (desktop mouse only, confirmed).
- No changes to the link-editor error-path drop behavior (songs are preserved on
  error; links stay as they are).

## Anti-goals

- Storing the setlist as a JSON column on `team_shows` (rejected: no DB-level
  integrity, dangling IDs on song delete).
- Reusing/extending `object_links` (rejected: unordered, polymorphic, no FK
  integrity).

## Decisions (made by the human partner in conversation)

- **Dedicated junction table** `team_show_songs` rather than JSON column or
  app-wide linking.
- **Order matters** — setlist semantics, not a bag of songs.
- **Team scope**: dropdowns only offer the show's team's songs.
- **Song once per show.**
- **Form UI**: list of `<select>`s that starts with one; picking a song reveals
  the next empty select below; and so on. Select order = setlist order.
- **Removal**: an × on each filled row removes it; rows below shift up.
- **Reordering**: drag-and-drop rows (up/down), following the existing native
  HTML5 drag-drop pattern in `src/email.ts` (`initEmailDragAndDrop`) — no new
  libraries. Desktop-only is acceptable (no touch/keyboard fallback).
- **Song deletion**: deleting a song that appears on setlists must warn first
  ("this song appears on N setlists") — not silent cascade. Confirmed by Andy.
- **Error path**: picked songs are preserved (flash-repopulated) when the show
  form fails validation. Confirmed by Andy. (Note: existing link-editor links are
  currently dropped on error; that behavior stays as-is.)

## Calls made by the builder (not explicitly approved — flag to Andy if you disagree)

- Dropdown option label format: `Name — Composer`.
- A song picked in one select disappears from (or is disabled in) the other
  selects, so duplicates cannot be built in the UI; the controller still
  validates duplicates as defense in depth.
- Detail view renders a numbered list: `1. Name — Composer`.

## Database

New table in `sql/schema.sql` (repo has no migration system — one full-schema
file; also provide a standalone `CREATE TABLE` statement for running against the
live DB):

```sql
CREATE TABLE team_show_songs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    show_id INT NOT NULL,
    song_id INT NOT NULL,
    position INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_show_song (show_id, song_id),
    UNIQUE KEY uniq_show_position (show_id, position),
    INDEX idx_team_show_songs_song (song_id),
    FOREIGN KEY (show_id) REFERENCES team_shows(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES team_songs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `uniq_show_song` = song once per show.
- `uniq_show_position` = no two songs in the same slot.
- Cascades = automatic cleanup on show/song delete.
- **Same-team enforcement**: a show and its songs must belong to the same team.
  Preferred: composite-FK trick — add `UNIQUE(team_id, id)` to `team_shows` and
  `team_songs`, add `team_id` to the junction, and use composite FKs
  `(team_id, show_id) → team_shows(team_id, id)` and
  `(team_id, song_id) → team_songs(team_id, id)`. If that proves awkward in the
  existing schema (the unique keys on parents are new indexes), fall back to an
  app-level check inside the save transaction — builder's call; state which was
  chosen in the PR/summary.

## Backend

- **Model** (`app/models/team/shows.php` or a new `app/models/team/show_songs.php`):
  - `fetchShowSongs(pdo, showId)` → ordered list (join to `team_songs` for
    name/composer), used by form and detail view.
  - `replaceShowSongs(pdo, showId, array $songIds)` → delete + insert positions
    1..n, inside the caller's transaction.
  - `fetchTeamSongOptions(pdo, teamId)` → the team's songs for the dropdown.
- **Controller** (`app/controllers/team/shows_save.php`):
  - Read ordered `show_songs[]` from POST (array of song IDs, order = DOM order).
  - Validate: each ID is an integer belonging to `team_songs.team_id` of the
    show's team; no duplicates. Reject with the existing error-flash pattern.
  - On both `create_show` and `update_show`, call `replaceShowSongs` inside the
    existing transaction.
  - The list view (`list.php`) can ignore the setlist.
- **Song deletion flow** (e.g. `app/controllers/team/songs_delete.php` /
  equivalent — locate the existing delete endpoint):
  - Before deleting, count setlists the song appears on
    (`SELECT COUNT(DISTINCT show_id) FROM team_show_songs WHERE song_id = ?`).
  - If count > 0, require confirmation in the UI ("appears on N setlists") before
    the delete goes through (follow the repo's existing confirmation pattern for
    destructive actions). The DB cascade remains as the actual delete mechanism.
- **Views**:
  - `app/views/team/shows/form.php`: new "Songs" section between existing
    fields and Notes (or after Notes — builder's judgment, keep Bulma styling,
    no inline JS beyond data attributes; all behavior goes in TypeScript).
      - Existing songs render as pre-filled selects in order.
      - One trailing empty select; JS appends the next empty select when the
        last one gets a value.
      - Each filled row has an × (Bulma `delete`-style control) and is
        draggable via a handle; rows get `data-*` attributes.
      - Hidden inputs or selects named `show_songs[]` keep DOM order = posted
        order after drag/removal.
  - `app/views/team/shows/detail.php`: numbered setlist
    (`1. Name — Composer`), or a grey "No songs yet" line when empty.

## Frontend (TypeScript)

- New module, e.g. `src/show-songs.ts` (never edit `app/public/js/*`; build with
  `bun run build`):
  - Init on the shows form: finds the song-select container.
  - "Grow on pick": on change of the last select, append a new empty select.
  - "× removes": row disappears, rows below shift up; if the removed row was
    the only filled one, leave exactly one empty select.
  - "Filter duplicates": selected songs are removed/disabled in other selects;
    on removal they come back.
  - "Drag reorder": native HTML5 drag-and-drop following the
    `initEmailDragAndDrop` pattern (`draggable`, `dragstart`, `dragover`,
    `drop`), drop between rows reorders; the trailing empty select is not
    draggable.
  - Rebuild hidden `show_songs[]` inputs (or rely on select DOM order) after
    every mutation so the POST order always matches the visual order.

## Constraints

- PHP 8.0+, MariaDB, HTMX where applicable, Bulma styling, CSRF on all POSTs
  (existing `renderCsrfField`).
- No inline JS in PHP files (repo rule) — behavior lives in `src/*.ts`, built
  with `bun run build` to `app/public/js/`.
- No new third-party dependencies (drag-drop is native HTML5, as in
  `src/email.ts`).
- `sql/schema.sql` is the single schema source; supply the live-DB `CREATE
  TABLE` (plus parent `UNIQUE(team_id, id)` indexes if composite FKs are used)
  separately.
- Do not log sensitive data; log show saves via existing `logAction`.
- Do not commit unless Andy says so.

## Left to the builder

- Exact placement of the Songs section in the form.
- Whether same-team enforcement is composite-FK (preferred) or in-transaction
  app check.
- How options are disabled vs. removed in duplicate filtering.
- Test approach within the repo's existing `tests/` conventions (CLI checks).
