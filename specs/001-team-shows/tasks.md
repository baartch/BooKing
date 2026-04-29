# Tasks: Team Show Management

**Input**: Design documents from `/specs/001-team-shows/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: No explicit TDD/test-first requirement in spec; tasks include implementation and manual verification checkpoints.

**Organization**: Tasks are grouped by user story for independent delivery and validation.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no direct dependency)
- **[Story]**: User story mapping (`[US1]`, `[US2]`, `[US3]`)
- Every task includes a concrete file path.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Align feature scaffolding and entrypoints

- [X] T001 Create shows module files in `app/models/team/shows.php` and `app/controllers/team/shows_save.php`
- [X] T002 Create team shows view files in `app/views/team/shows/shows.php`, `app/views/team/shows/list.php`, `app/views/team/shows/detail.php`, and `app/views/team/shows/form.php`
- [X] T003 Add/verify team routing aliases in `app/controllers/team/shows.php` and `app/controllers/team/teams.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared data and linking infrastructure required by all stories

**⚠️ CRITICAL**: User-story work starts only after this phase

- [X] T004 Update show schema for `venue_text` (optional) and remove hard venue dependency in `sql/schema.sql`
- [X] T005 [P] Implement shared show data access/validation helpers in `app/models/team/shows.php`
- [X] T006 [P] Extend link scope/type support for `show` in `app/models/core/link_scope.php`
- [X] T007 [P] Extend linked-object display behavior for show links in `app/models/core/link_helpers.php`
- [X] T008 [P] Extend link search with show targets in `app/models/core/link_search.php`
- [X] T009 Integrate shows loading, tab handling, and team-scope guards in `app/controllers/team/index.php`
- [X] T010 Add audit logging points for show create/update/link behavior in `app/controllers/team/shows_save.php`

**Checkpoint**: Foundation complete

---

## Phase 3: User Story 1 - Create a show record (Priority: P1) 🎯 MVP

**Goal**: Team members can create shows with required date and optional fields including venue text.

**Independent Test**: In Team → Shows, create a show with only date; create another with date + venue text + optional fields; both persist and render.

### Implementation for User Story 1

- [X] T011 [P] [US1] Implement Shows tab container with HTMX/full-page compatibility in `app/views/team/shows/shows.php`
- [X] T012 [P] [US1] Implement shows list table (date column minimum) in `app/views/team/shows/list.php`
- [X] T013 [P] [US1] Implement show detail rendering in `app/views/team/shows/detail.php`
- [X] T014 [US1] Implement show create/update form fields (required date, optional venue text/time/fee/notes/name) in `app/views/team/shows/form.php`
- [X] T015 [US1] Implement create action validation/persistence in `app/controllers/team/shows_save.php`
- [X] T016 [US1] Integrate Shows tab into Team tab layout in `app/views/team/index.php`

**Checkpoint**: US1 independently functional (MVP)

---

## Phase 4: User Story 2 - Update show details (Priority: P2)

**Goal**: Team members can edit existing show fields with proper validation and feedback.

**Independent Test**: Edit an existing show’s date/time/venue text/notes/fee/name; valid updates persist and invalid input is rejected with clear feedback.

### Implementation for User Story 2

- [X] T017 [US2] Implement update-path validation and persistence in `app/controllers/team/shows_save.php`
- [X] T018 [US2] Implement edit-mode loading and form prefill behavior in `app/views/team/shows/form.php`
- [X] T019 [US2] Implement HTMX detail/list refresh behavior after updates in `app/controllers/team/index.php`
- [X] T020 [US2] Implement edge-case feedback rendering for invalid updates in `app/views/team/shows/shows.php`

**Checkpoint**: US2 independently functional

---

## Phase 5: User Story 3 - Link shows to related objects (Priority: P3)

**Goal**: Team members can add/remove links on create/update; venue linking can auto-fill empty venue text.

**Independent Test**: Add/remove links in create/update; add venue link with empty venue text and verify auto-fill; ensure existing venue text is not overwritten.

### Implementation for User Story 3

- [X] T021 [US3] Implement create-flow link capture handling in `app/views/team/shows/form.php`
- [X] T022 [US3] Implement save-time link persistence for create/update in `app/controllers/team/shows_save.php`
- [X] T023 [US3] Implement venue-link auto-fill logic (empty-only) in `app/controllers/team/shows_save.php`
- [X] T024 [US3] Render linked objects in show detail with navigation links in `app/views/team/shows/detail.php`
- [X] T025 [US3] Ensure duplicate-link prevention and target validation through shared link helpers in `app/models/core/object_links.php`

**Checkpoint**: US3 independently functional

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final consistency, docs, and validation

- [ ] T026 [P] Update feature implementation notes for venue text + link auto-fill in `specs/001-team-shows/quickstart.md`
- [ ] T027 Verify full quickstart scenario execution in `specs/001-team-shows/quickstart.md`
- [ ] T028 Verify audit log entries for show create/update/link actions in `app/models/core/database.php`
- [ ] T029 Verify team authorization/CSRF paths for shows endpoints in `app/controllers/team/shows_save.php`
- [ ] T030 If TypeScript files changed, run build and verify outputs in `app/public/js/`

---

## Dependencies & Execution Order

### Phase Dependencies

- Phase 1 → no dependencies
- Phase 2 → depends on Phase 1 (blocks all stories)
- Phase 3 (US1) → depends on Phase 2
- Phase 4 (US2) → depends on Phase 2 and US1 baseline
- Phase 5 (US3) → depends on Phase 2 and show create/update baseline
- Phase 6 → depends on desired user stories complete

### User Story Dependencies

- **US1 (P1)**: first deliverable (MVP)
- **US2 (P2)**: extends same show records, independently testable after foundation
- **US3 (P3)**: depends on create/update availability, independently testable

### Parallel Opportunities

- Foundational: T005/T006/T007/T008 in parallel
- US1: T011/T012/T013 in parallel
- Polish: T026 parallel with T028

---

## Parallel Example: User Story 3

```bash
Task: "T021 [US3] Implement create-flow link capture handling in app/views/team/shows/form.php"
Task: "T024 [US3] Render linked objects in app/views/team/shows/detail.php"
```

---

## Implementation Strategy

### MVP First
1. Finish Phase 1 and Phase 2
2. Deliver Phase 3 (US1)
3. Validate US1 independently and demo

### Incremental Delivery
1. Add US2 update flow
2. Add US3 linking + venue auto-fill
3. Run polish and final verification

### Parallel Team Strategy
1. Engineer A: schema/model/foundation tasks
2. Engineer B: US1 views and tab integration
3. Engineer C: US2/US3 controller-linking behavior

---

## Notes

- All tasks use required checklist format with IDs, labels, and file paths.
- `[P]` markers are only used for non-conflicting parallel work.
- Story phases are independently verifiable.
