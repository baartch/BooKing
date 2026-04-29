# Phase 0 Research — Team Show Management

## Decision 1: Venue handling model
- **Decision**: Keep an optional free-text venue field on Show and use generic object linking for venue association.
- **Rationale**: Matches clarified scope while keeping loose coupling to venue records.
- **Alternatives considered**:
  - Required venue foreign key — rejected by updated specification.

## Decision 2: Venue link auto-fill behavior
- **Decision**: When a venue link is added and show venue text is empty, auto-fill venue text with linked venue name.
- **Rationale**: Preserves user convenience and avoids duplicate manual entry.
- **Alternatives considered**:
  - Never auto-fill — rejected due to unnecessary manual work.
  - Always overwrite — rejected to avoid clobbering user-provided text.

## Decision 3: Show field validation
- **Decision**: Require date only; venue text, name, time, artist fee, and notes are optional.
- **Rationale**: Aligns with current spec clarifications.
- **Alternatives considered**:
  - Require venue text — rejected by scope.

## Decision 4: Linking lifecycle
- **Decision**: Support add/remove links in both create and update flows.
- **Rationale**: Explicit requirement and existing pattern compatibility.
- **Alternatives considered**:
  - Update-only linking — rejected.

## Decision 5: Security and audit
- **Decision**: Preserve existing team authorization, CSRF checks, and `logAction()` for create/update/link actions.
- **Rationale**: Required by constitution.
- **Alternatives considered**:
  - Reduced checks/logging — rejected.
