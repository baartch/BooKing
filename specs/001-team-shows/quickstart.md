# Quickstart — Team Show Management

## Goal
Implement Team Shows with required date, optional venue text, and link-driven venue association including auto-fill behavior.

## Steps
1. Review `spec.md`, `plan.md`, `research.md`, `data-model.md`, and `contracts/shows-ui-contract.md`.
2. Update schema/model for show fields including optional `venue_text`.
3. Implement create/update validation (required date, optional others).
4. Implement link add/remove flows in create and update screens.
5. Add venue-link auto-fill rule:
   - when linking venue and venue text is blank, set venue text to linked venue name.
6. Ensure Shows tab and HTMX list/detail/form behavior works with full-page fallback.
7. Verify team authorization + CSRF + audit logging.

## Verification checklist
- Create show with date only.
- Create show with venue text manually entered.
- Add venue link when venue text empty → venue text auto-fills.
- Add venue link when venue text already present → value remains unchanged.
- Update show fields and links successfully.
- Unauthorized access attempts are denied.
