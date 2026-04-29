# Quickstart — Team Show Management

## Goal
Implement team-scoped Show management with a dedicated Shows tab, shared table/detail partial rendering, required date+venue fields, optional metadata fields, and object linking on create/update.

## 1) Prepare data and contracts
1. Review `spec.md`, `plan.md`, `research.md`, and `data-model.md`.
2. Review UI/interaction contract: `contracts/shows-ui-contract.md`.

## 2) Backend model work
1. Add/extend Show model helpers under `app/models/team/`.
2. Enforce field rules:
   - Required: date, venue (existing venue record)
   - Optional: show name, time, artist fee, notes
3. Implement link attach/detach helpers for create and update flows.
4. Add `logAction()` calls for create/update/link operations (no sensitive data).

## 3) Schema updates
1. Update `sql/schema.sql` with `shows` and show-link association structures.
2. Ensure referential integrity to teams and venues.
3. Ensure uniqueness/constraints for duplicate link prevention.

## 4) Controller endpoints and HTMX behavior
1. Add/extend team show controller endpoints in `app/controllers/team/`.
2. Use `HTMX::isRequest()` and related helpers for partial/full response branching.
3. Return list/detail/form partial responses for HTMX requests.
4. Ensure create and update endpoints both accept link operations.

## 5) View and partial composition
1. Create team show views under `app/views/team/shows/`:
   - `shows.php` (tab container)
   - `list.php` (table wrapper)
   - `detail.php` (detail panel wrapper)
   - `form.php` (create/update form)
2. Render list/detail using existing shared partials in `app/partials/tables/`.
3. Expose columns for show table: date, venue.

## 6) Team navigation integration
1. Add Shows as a dedicated tab in Team UI.
2. Ensure tab loading aligns with existing HTMX dynamic behavior patterns.

## 7) Verification
1. Create show with required fields only.
2. Create show with optional fields populated.
3. Reject creation without date or venue.
4. Verify venue must be selected from existing records.
5. Add/remove links during create and update flows.
6. Verify team scope authorization and audit logs.
