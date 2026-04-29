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
- **venue_id**: Required reference to an existing Venue record.
- **artist_fee**: Optional monetary amount.
- **notes**: Optional free-form text.
- **created_at / updated_at**: Audit timestamps.

### Validation Rules
- `team_id` required and must reference an accessible team.
- `show_date` required and must be a valid date.
- `venue_id` required and must reference an existing venue.
- `show_time` optional; when provided, must be a valid time value.
- `artist_fee` optional; when provided, must be a valid non-negative monetary amount.
- `show_name` optional; when provided, trimmed and length-limited per project conventions.
- `notes` optional; when provided, stored as plain text.

### Relationships
- Many Shows belong to one Team (`Show.team_id -> Team.id`).
- Many Shows reference one Venue (`Show.venue_id -> Venue.id`).
- Many-to-many relationship between Show and other linkable objects via Show Link associations.

### Lifecycle
- **Created**: Show is created with required fields (`show_date`, `venue_id`) and optional details.
- **Updated**: Optional or required fields may be modified by authorized team users.
- **Linked**: Related object links may be added during create or update.
- **Unlinked**: Existing related object links may be removed.

## Entity: Show Link

### Purpose
Associates a Show with another existing object for contextual navigation.

### Fields
- **id**: Unique link identifier.
- **show_id**: Required reference to Show.
- **target_type**: Type/category of linked object.
- **target_id**: Identifier of linked object.
- **created_at**: Link creation timestamp.

### Validation Rules
- `show_id` must reference an existing show within authorized team scope.
- `target_type + target_id` must reference an existing linkable object.
- Duplicate link pairs for the same show/target should be prevented.

### Relationships
- Many Show Links belong to one Show.
- Each Show Link points to one external target object.

## Entity: Team (existing)

### Role in feature
Defines ownership/scope for Shows and implicitly provides artist context.

## Entity: Venue (existing)

### Role in feature
Provides selectable venue records for required Show venue assignment.
