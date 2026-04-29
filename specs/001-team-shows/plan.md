# Implementation Plan: Team Show Management

**Branch**: `001-team-shows` | **Date**: 2026-04-29 | **Spec**: [specs/001-team-shows/spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-team-shows/spec.md`

## Summary

Add a dedicated **Shows** tab under each Team where authorized team members can create, view, and edit team-scoped shows, render show listings via the existing shared table/detail partials, and manage object links during both create and update flows. The solution follows existing BooKing patterns (MVC boundaries, HTMX dynamic partial loads, security/logging baseline, and existing venue records as selectable references).

## Technical Context

**Language/Version**: PHP 8.0+, TypeScript (existing frontend pipeline)  
**Primary Dependencies**: HTMX request/response helpers, Bulma UI patterns, existing table/detail partials  
**Storage**: MariaDB/MySQL (schema updates in `sql/schema.sql`)  
**Testing**: Existing project test scripts in `tests/` plus manual HTMX flow verification  
**Target Platform**: Server-rendered web application  
**Project Type**: MVC web application  
**Performance Goals**: Show tab list/detail interactions perceived as immediate for normal team usage (target: list/detail update within 1 second for typical team dataset)  
**Constraints**: No inline JS/CSS in PHP; use HTMX helper class; use existing table partials; keep team-scope authorization and security controls intact  
**Scale/Scope**: Team-level show management for routine operations (dozens to low hundreds of shows per team)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. MVC Separation of Concerns**: PASS — changes scoped to controllers/models/views/partials with no business logic in views.
- **II. Approved Stack & Data Store**: PASS — PHP + MariaDB retained; schema edits planned in `sql/schema.sql`.
- **III. Frontend Assets via Bulma + TypeScript Pipeline**: PASS — reuse existing PHP/HTMX view patterns and table partials; no direct compiled JS edits.
- **IV. Security Baseline Compliance**: PASS — existing auth/session/CSRF/rate-limit/security headers remain mandatory for show mutations.
- **V. Auditable Logging Without Sensitive Data**: PASS — show create/update/link actions will log via `logAction()` without sensitive payloads.

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
│       └── (new/updated shows controller endpoints)
├── models/
│   └── team/
│       └── (new/updated show + linking model helpers)
├── views/
│   └── team/
│       └── shows/
│           ├── shows.php
│           ├── list.php
│           ├── detail.php
│           └── form.php
├── partials/
│   └── tables/
│       ├── table.php
│       └── detail.php
└── public/
    └── js/

sql/
└── schema.sql

tests/
└── (existing test scripts extended as needed)
```

**Structure Decision**: Follow existing single-project MVC web app layout and Team feature conventions (`app/views/team/tasks/*`, `app/views/team/mailboxes/*`) while adding a parallel Team Shows feature area and shared model/controller logic under team namespaces.

## Complexity Tracking

No constitution violations identified; no complexity exception required.

## Phase 0: Outline & Research

### Research Topics

1. Best-practice integration pattern for adding a new Team tab with HTMX dynamic loads and full-page fallback.
2. Reuse strategy for `app/partials/tables/table.php` and `app/partials/tables/detail.php` for show list/detail rendering.
3. Validation and persistence rules for required/optional fields (required: date + venue-from-DB; optional: name/time/fee/notes).
4. Linking workflow parity with existing object-link patterns, including create-time and update-time link management.
5. Authorization/logging touchpoints for team-scoped show operations.

### Phase 0 Output

`specs/001-team-shows/research.md` (all technical unknowns resolved; no remaining NEEDS CLARIFICATION markers).

## Phase 1: Design & Contracts

### Design Outputs

- `specs/001-team-shows/data-model.md`: show entity shape, constraints, relationships, lifecycle notes.
- `specs/001-team-shows/contracts/shows-ui-contract.md`: HTMX/UI interaction contract for Team Shows tab/list/detail/form/linking behavior.
- `specs/001-team-shows/quickstart.md`: implementation walkthrough aligned to BooKing structure.

### Agent Context Update

- Update AGENTS plan reference between `<!-- SPECKIT START -->` and `<!-- SPECKIT END -->` to point to `specs/001-team-shows/plan.md`.
- Run `.specify/scripts/bash/update-agent-context.sh pi` after plan generation.

## Post-Design Constitution Check

- **I. MVC Separation of Concerns**: PASS (design keeps domain logic in models, request flow in controllers, rendering in views/partials).
- **II. Approved Stack & Data Store**: PASS (MariaDB schema change only; no stack drift).
- **III. Frontend Assets via Bulma + TypeScript Pipeline**: PASS (HTMX + existing partials, no inline assets).
- **IV. Security Baseline Compliance**: PASS (mutations remain CSRF-protected and team-authorized).
- **V. Auditable Logging Without Sensitive Data**: PASS (show action logs defined; sensitive data excluded).
