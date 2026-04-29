# Phase 0 Research — Team Show Management

## Decision 1: Team tab and HTMX loading pattern
- **Decision**: Implement Shows as a dedicated Team tab with HTMX-driven partial loading for list/detail/form interactions, while retaining full-page compatibility.
- **Rationale**: This matches existing Team feature behavior and keeps interaction responsive without introducing new frontend stack complexity.
- **Alternatives considered**:
  - Full page reload for all actions — rejected due to poorer UX and inconsistency with existing dynamic Team screens.
  - Custom JavaScript behavior — rejected because existing best practice is HTMX helper usage and minimal custom client logic.

## Decision 2: Reuse existing table/detail partials
- **Decision**: Render Shows list/detail using `app/partials/tables/table.php` and `app/partials/tables/detail.php` with feature-specific view wrappers.
- **Rationale**: User requested this explicitly, and it aligns with project guidance to build tables via shared table partials.
- **Alternatives considered**:
  - New custom show table component — rejected to avoid duplication and visual inconsistency.

## Decision 3: Show field validation and requirement rules
- **Decision**: Enforce required fields `date` and `venue` (venue selected from existing DB venues). Keep `show_name`, `time`, `artist_fee`, and `notes` optional.
- **Rationale**: Matches clarified requirements and ensures minimum scheduling fidelity while allowing incomplete operational details.
- **Alternatives considered**:
  - Require time and/or fee — rejected because current clarified scope defines these as optional.

## Decision 4: Linking flow timing and behavior
- **Decision**: Support object linking during both show creation and show update using the same link association model and validation rules.
- **Rationale**: Explicitly requested; avoids rework and ensures consistent link management lifecycle.
- **Alternatives considered**:
  - Update-only linking — rejected because it blocks requested create-time workflow.
  - Create-only linking — rejected because links often evolve post-creation.

## Decision 5: Team artist context handling
- **Decision**: Do not store a required artist-name field on Show; derive artist context from owning Team.
- **Rationale**: Clarified by product direction that one team maps to one artist context.
- **Alternatives considered**:
  - Keep optional artist name field — rejected as redundant and ambiguity-inducing.

## Decision 6: Security and audit behavior
- **Decision**: Apply existing team authorization checks and CSRF protection to all show write operations; log create/update/link actions through `logAction()` without sensitive data.
- **Rationale**: Required by constitution and existing security baseline.
- **Alternatives considered**:
  - Reduced logging for simpler implementation — rejected because auditable actions are mandatory.

## Decision 7: Venue source of truth
- **Decision**: Venue selection is constrained to existing venue records and validated server-side on create/update.
- **Rationale**: Prevents orphan/free-text venue data and aligns with "select from DB" requirement.
- **Alternatives considered**:
  - Free-text venue entry — rejected due to inconsistency and data quality risk.
