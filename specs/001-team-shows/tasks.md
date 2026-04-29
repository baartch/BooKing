# Tasks: Team Show Management

**Input**: Design documents from `/specs/001-team-shows/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tests were not explicitly requested in the specification; implementation tasks include manual verification checkpoints.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare feature scaffolding and documentation alignment

- [X] T001 Create Team Shows view directory scaffold in `app/views/team/shows/`
- [X] T002 Create Team Shows controller file scaffold in `app/controllers/team/shows.php`
- [X] T003 Create Team Shows model helper scaffold in `app/models/team/shows.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core schema, routing, authorization, and shared wiring required before user-story work

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T004 Update show and show-link schema structures in `sql/schema.sql`
- [X] T005 [P] Add shared show data-access helpers (CRUD + venue validation) in `app/models/team/shows.php`
- [X] T006 [P] Add shared show-link association helpers in `app/models/team/shows.php`
- [X] T007 Add team-scope authorization and CSRF checks for show endpoints in `app/controllers/team/shows.php`
- [X] T008 Add show action audit logging (`logAction`) hooks in `app/controllers/team/shows.php`
- [X] T009 Wire Team Shows routes into team routing entrypoints in `app/controllers/team/teams.php`

**Checkpoint**: Foundation ready - user story implementation can now begin

---

## Phase 3: User Story 1 - Create a show record (Priority: P1) 🎯 MVP

**Goal**: Authorized team members can create shows with required date+venue and optional fields from a dedicated Shows tab.

**Independent Test**: Open a team, navigate to Shows tab, create a show with only date+venue, then create another with optional fields; both appear correctly in list/detail with validation on missing required fields.

### Implementation for User Story 1

- [X] T010 [P] [US1] Implement Shows tab container view in `app/views/team/shows/shows.php`
- [X] T011 [P] [US1] Implement show table list wrapper (date, venue columns via shared table partial) in `app/views/team/shows/list.php`
- [X] T012 [P] [US1] Implement show detail wrapper using shared detail partial in `app/views/team/shows/detail.php`
- [X] T013 [US1] Implement create/edit form view with required/optional field semantics and DB venue selector in `app/views/team/shows/form.php`
- [X] T014 [US1] Implement show create endpoint with validation and save logic in `app/controllers/team/shows.php`
- [X] T015 [US1] Implement HTMX list/detail/form responses for create flow in `app/controllers/team/shows.php`
- [X] T016 [US1] Integrate Shows tab into Team UI navigation in `app/views/team/index.php`

**Checkpoint**: User Story 1 is independently functional and demoable as MVP

---

## Phase 4: User Story 2 - Update show details (Priority: P2)

**Goal**: Authorized team members can edit existing show values while preserving required-field rules and optional-field behavior.

**Independent Test**: Select an existing show, edit required and optional fields, save successfully; invalid updates fail with clear feedback and no data corruption.

### Implementation for User Story 2

- [X] T017 [US2] Implement show update endpoint with required/optional field validation in `app/controllers/team/shows.php`
- [X] T018 [US2] Implement edit-mode loading and submit handling in `app/views/team/shows/form.php`
- [X] T019 [US2] Implement HTMX refresh behavior for list/detail after update in `app/controllers/team/shows.php`
- [X] T020 [US2] Add optional artist_fee/time validation handling for update scenarios in `app/models/team/shows.php`
- [X] T021 [US2] Add edge-case feedback handling for invalid date/venue/time/fee combinations in `app/views/team/shows/detail.php`

**Checkpoint**: User Stories 1 and 2 both work independently

---

## Phase 5: User Story 3 - Link shows to related objects (Priority: P3)

**Goal**: Team members can add/remove links to other objects during both show creation and show updates.

**Independent Test**: During create and update flows, add and remove related-object links; saved links display correctly in show detail and persist across reloads.

### Implementation for User Story 3

- [X] T022 [US3] Implement link add/remove endpoint handlers for show lifecycle actions in `app/controllers/team/shows.php`
- [X] T023 [US3] Implement create-flow link capture and persistence in `app/views/team/shows/form.php`
- [X] T024 [US3] Implement update-flow link management UI and persistence in `app/views/team/shows/form.php`
- [X] T025 [US3] Render linked-object list in show detail wrapper in `app/views/team/shows/detail.php`
- [X] T026 [US3] Enforce duplicate-link prevention and target existence checks in `app/models/team/shows.php`

**Checkpoint**: All user stories are independently functional

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final hardening and end-to-end verification across stories

- [ ] T027 [P] Update feature documentation notes for Team Shows usage in `specs/001-team-shows/quickstart.md`
- [ ] T028 Run end-to-end quickstart verification scenarios from `specs/001-team-shows/quickstart.md`
- [X] T029 Validate HTMX full-page fallback behavior for Team Shows tab in `app/controllers/team/shows.php`
- [ ] T030 Validate logging output for create/update/link actions (no sensitive data) in `app/models/core/database.php`
- [ ] T031 If TypeScript sources change, run `bun run build` and verify compiled output in `app/public/js/`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies
- **Phase 2 (Foundational)**: Depends on Phase 1; blocks all user stories
- **Phase 3 (US1)**: Depends on Phase 2
- **Phase 4 (US2)**: Depends on Phase 2 and reuses US1 structures
- **Phase 5 (US3)**: Depends on Phase 2 and integrates with US1/US2 flows
- **Phase 6 (Polish)**: Depends on completion of desired user stories

### User Story Dependencies

- **US1 (P1)**: Starts immediately after Foundational; no dependency on other stories
- **US2 (P2)**: Starts after Foundational; builds on same show records but remains independently testable
- **US3 (P3)**: Starts after Foundational; can be developed after US1 endpoint scaffolding exists

### Parallel Opportunities

- **Foundational**: T005 and T006 can run in parallel
- **US1**: T010, T011, and T012 can run in parallel
- **Polish**: T027 can run in parallel with T029

---

## Parallel Example: User Story 1

```bash
Task: "T010 [US1] Implement Shows tab container view in app/views/team/shows/shows.php"
Task: "T011 [US1] Implement show table list wrapper in app/views/team/shows/list.php"
Task: "T012 [US1] Implement show detail wrapper in app/views/team/shows/detail.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 (Setup)
2. Complete Phase 2 (Foundational)
3. Complete Phase 3 (US1)
4. Validate US1 independent test criteria
5. Demo/deploy MVP

### Incremental Delivery

1. Deliver US1 (create + list/detail) as first increment
2. Deliver US2 (update behavior) as second increment
3. Deliver US3 (link management) as third increment
4. Run polish phase for cross-story reliability

### Parallel Team Strategy

1. One developer handles schema/foundational backend (T004–T009)
2. One developer handles US1 view wrappers (T010–T013)
3. One developer handles controller integrations and HTMX responses (T014–T019)
4. Merge for US3 linking and polish

---

## Notes

- All tasks follow required checklist format: `- [ ] T### [P?] [US?] Description with file path`
- [P] tasks are limited to non-overlapping files or independent implementation areas
- User story labels are applied only within story phases
- Prioritize US1 completion before expanding scope
