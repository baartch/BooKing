# Data Model — Team Show Management

## Entity: Show

### Purpose
Represents one scheduled show within a single Team scope.

### Fields
- **id**: Unique show identifier.
- **team_id**: Required reference to owning Team.
- **show_name**: Optional short label/title.
- **show_date**: Required calendar date.
- **show_time**: Optional time-of-day.
- **venue_text**: Optional free-text venue value.
- **artist_fee**: Optional monetary amount.
- **notes**: Optional free-form text.
- **created_at / updated_at**: Audit timestamps.

### Validation Rules
- `team_id` required and must reference an accessible team.
- `show_date` required and must be a valid date.
- `show_time` optional; when provided, must be a valid time.
- `venue_text` optional; when provided, stored as plain text.
- `artist_fee` optional; when provided, must be non-negative.
- `show_name` optional.
- `notes` optional.

### Relationships
- Many Shows belong to one Team.
- Shows can have many object links (including venue objects) via generic link associations.

### Lifecycle
- **Created**: Show created with required date and optional fields.
- **Updated**: Any mutable fields updated by authorized team users.
- **Linked/Unlinked**: Related links added/removed during create/update workflows.
- **Venue Auto-fill**: If venue link is added and `venue_text` is empty, set `venue_text` to linked venue name.

## Entity: Show Link

### Purpose
Associates a Show with another existing object for contextual navigation.

### Fields
- **id**
- **show_id**
- **target_type**
- **target_id**
- **created_at**

### Validation Rules
- Link targets must exist and be accessible.
- Duplicate show-target link pairs are prevented.

## Entity: Team (existing)
Defines ownership/scope for Shows.

## Entity: Venue (existing)
May be linked to a show; its name can auto-fill empty show venue text.
