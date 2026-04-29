# UI Contract — Team Shows

## Team Navigation
- Teams view exposes a dedicated **Shows** tab.
- Shows tab uses existing HTMX dynamic load patterns with full-page fallback.

## List Contract
- Shows are listed in shared table partial format.
- Table includes at least **Date** column.

## Show Form Contract (Create/Update)
- Required: date.
- Optional: show name, time, venue text, artist fee, notes.
- Links to objects can be managed during create and update.

## Venue Link Auto-fill Contract
- If user adds a link to a venue and venue text is currently empty, system auto-fills venue text with linked venue name.
- If venue text is already populated, linking a venue must not overwrite existing venue text.

## Detail Contract
- Show detail displays core fields and linked objects.
- Linked object list remains navigable.

## Security/Audit Contract
- Only authorized team users may create/update/link.
- CSRF validation required for write operations.
- Show create/update/link actions logged without sensitive data.
