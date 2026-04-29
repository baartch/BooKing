# UI Contract — Team Shows

## Purpose
Define the user-visible interaction contract for Team Shows, including tab behavior, table/detail rendering, and create/update/link flows.

## Team Navigation Contract
- Teams view exposes a dedicated **Shows** tab.
- Selecting Shows loads show content using existing HTMX dynamic load patterns.
- Non-HTMX fallback renders full-page equivalent content.

## List Contract
- Shows list is presented as a table using existing shared table partial.
- Required visible columns:
  - **Date**
  - **Venue**
- Row selection loads show detail using the shared detail partial wrapper pattern.

## Detail Contract
- Selecting a show displays show details and available actions.
- Detail view supports transition to edit mode.

## Create Contract
- Create form supports fields:
  - Required: date, venue (venue chosen from existing DB records)
  - Optional: show name, time, artist fee, notes
- Linking to other objects is available in the create flow.
- On validation failure, user receives clear field-level feedback.
- On success, list and detail update to reflect new show.

## Update Contract
- Edit form supports updating all show fields (required/optional semantics preserved).
- Linking to other objects is available in update flow.
- Link removal is supported during update.
- On success, updated values and links appear immediately.

## Authorization & Security Contract
- Only authorized users within the owning team can create/update/link shows.
- Unauthorized access attempts are denied and do not leak protected data.
- All write operations require CSRF-valid requests.

## Audit Contract
- Show create/update/link actions are logged via the existing audit mechanism.
- Logs exclude sensitive data.
