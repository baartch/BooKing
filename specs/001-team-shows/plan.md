# Implementation Plan: Team Show Management

**Branch**: `001-team-shows` | **Date**: 2026-04-29 | **Spec**: [specs/001-team-shows/spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-team-shows/spec.md`

## Summary

Add a dedicated **Shows** tab under each Team (FR-017) where authorized team members can create, view, and edit team-scoped shows. Shows require date and support optional name/time/**venue text**/artist fee/notes. Shows can link to other objects in create and update flows, and when a venue link is added while venue text is empty, venue text is auto-filled from the linked venue name (FR-014). The feature uses existing HTMX dynamic loading, shared table/detail partials, and existing security/audit patterns.

## Technical Context

**Language/Version**: PHP 8.0+, TypeScript (existing frontend pipeline)  
**Primary Dependencies**: HTMX helpers, Bulma patterns, shared table/detail partials, existing link editor flow  
**Storage**: MariaDB/MySQL (`sql/schema.sql`)  
**Testing**: Existing `tests/` scripts + manual HTMX/CRUD/link-flow verification  
**Target Platform**: Server-rendered web application  
**Project Type**: MVC web application  
**Performance Goals**: Typical team list/detail interactions feel immediate under normal data volumes  
**Constraints**: No inline JS/CSS in PHP, preserve MVC boundaries, preserve CSRF/auth checks, preserve audit logging without sensitive data  
**Scale/Scope**: Team-scoped usage (dozens to low hundreds of shows per team)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. MVC Separation of Concerns**: PASS — controller/model/view separation retained.
- **II. Approved Stack & Data Store**: PASS — PHP + MariaDB retained; schema updated in `sql/schema.sql`.
- **III. Frontend Assets via Bulma + TypeScript Pipeline**: PASS — HTMX + existing UI partials; no direct JS output edits.
- **IV. Security Baseline Compliance**: PASS — CSRF, session, and team authorization remain required for mutations.
- **V. Auditable Logging Without Sensitive Data**: PASS — create/update/link operations logged via `logAction()` without sensitive payloads.

## Project Structure

### Documentation (this feature)

```text
specs/001-team-shows/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── shows-ui-contract.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── controllers/
│   └── team/
│       └── (shows endpoints and tab integration)
├── models/
│   └── team/
│       └── (show data helpers)
├── views/
│   └── team/
│       └── shows/
│           ├── shows.php
│           ├── list.php
│           ├── detail.php
│           └── form.php
├── models/core/
│   ├── link_helpers.php
│   ├── link_scope.php
│   └── link_search.php
└── partials/
    └── tables/

sql/
└── schema.sql
```

**Structure Decision**: Reuse existing Team feature patterns (Tasks/Mailboxes) and existing generic object-link infrastructure, with show-specific additions under `app/models/team`, `app/controllers/team`, and `app/views/team/shows`.

## Complexity Tracking

No constitution violations identified; no exception tracking required.

## Phase 0: Outline & Research

### Research Topics

1. Best-practice handling for optional venue text plus venue-link auto-fill behavior.
2. Consistent linking behavior in create/update forms while preserving existing link editor flow.
3. Show data validation rules with required date + optional venue text/time/fee/notes.
4. HTMX list/detail refresh pattern for Team Shows tab.

### Phase 0 Output

`specs/001-team-shows/research.md` with updated decisions reflecting venue-text + link-based auto-fill behavior.

## Phase 1: Design & Contracts

### Design Outputs

- `specs/001-team-shows/data-model.md`: Show entity includes optional venue text and link-driven venue association.
- `specs/001-team-shows/contracts/shows-ui-contract.md`: UI contract updated for venue text input and link auto-fill rule.
- `specs/001-team-shows/quickstart.md`: implementation/verification steps updated for new venue behavior.

### Agent Context Update

- Keep AGENTS plan pointer at `specs/001-team-shows/plan.md`.
- Run `.specify/scripts/bash/update-agent-context.sh pi`.

## Post-Design Constitution Check

- **I. MVC Separation of Concerns**: PASS
- **II. Approved Stack & Data Store**: PASS
- **III. Frontend Assets via Bulma + TypeScript Pipeline**: PASS
- **IV. Security Baseline Compliance**: PASS
- **V. Auditable Logging Without Sensitive Data**: PASS
