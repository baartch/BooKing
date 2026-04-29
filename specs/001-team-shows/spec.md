# Feature Specification: Team Show Management

**Feature Branch**: `001-team-shows`  
**Created**: 2026-04-29  
**Status**: Draft  
**Input**: User description: "Lets introduce \"Shows\". They represent a show of an artist with date, time, venue, notes, artists fee. Shows are in the scope of \"Teams\". Shows can have links to any other object."

## Clarifications

### Session 2026-04-29

- Q: Is show name required? → A: No, show name is optional.
- Q: Is artist name required on a show? → A: No, artist name is not required because each team represents one artist.
- Q: Which show fields are required vs optional? → A: Required: date and venue (selected from existing venue records). Optional: time, artist fee, and notes.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create a show record (Priority: P1)

As a team member, I want to create a show with all core details so that my team can track upcoming performances and planning information in one place.

**Why this priority**: Without show creation, no show data can exist, so no other workflow (editing, linking, reporting) provides value.

**Independent Test**: Can be fully tested by creating a show within a team and verifying all required details are saved and visible to team members.

**Acceptance Scenarios**:

1. **Given** I am viewing a team where I can manage content, **When** I create a show with required date and venue (and optionally show name, time, artist fee, and notes), **Then** the show is saved and appears in that team’s show list.
2. **Given** I submit a show missing required fields, **When** I attempt to save, **Then** I receive clear validation feedback and the show is not created.
3. **Given** I leave optional fields blank during creation, **When** the show is saved, **Then** the show is created successfully with only required fields.
4. **Given** I add optional notes during creation, **When** the show is saved, **Then** the notes are stored and shown in the show details.

---

### User Story 2 - Update show details (Priority: P2)

As a team member, I want to edit existing show details so that schedule, venue, and fee changes remain accurate over time.

**Why this priority**: Show details frequently change after creation; stale information creates operational and financial risk.

**Independent Test**: Can be fully tested by editing an existing show and verifying updates are reflected everywhere the show appears.

**Acceptance Scenarios**:

1. **Given** a show already exists in my team, **When** I update its date, time, venue, notes, or fee, **Then** the updated values are saved and displayed.
2. **Given** an invalid fee or invalid date/time format is entered, **When** I save changes, **Then** the system blocks the update and highlights the invalid fields.

---

### User Story 3 - Link shows to related objects (Priority: P3)

As a team member, I want to connect a show to other relevant records so that all related planning and context can be navigated from the show.

**Why this priority**: Linking improves discoverability and coordination, but core value still exists without it.

**Independent Test**: Can be fully tested by attaching one or more links between a show and existing records, then confirming the links are visible and navigable from the show.

**Acceptance Scenarios**:

1. **Given** a show exists, **When** I add a link to another existing object, **Then** the link is associated with the show and visible in show details.
2. **Given** a show has existing links, **When** I remove one link, **Then** that link is no longer shown for the show while other links remain intact.

### Edge Cases

- A user attempts to create or edit a show in a team they do not belong to.
- Two shows in the same team use the same date, time, and venue combination while both omit show name.
- A linked object is deleted or becomes inaccessible after being linked to a show.
- Artist fee is entered as zero, negative, or with unsupported currency formatting when fee is provided.
- A user tries to save a show without a date or without selecting an existing venue.
- Show date/time is in the past at time of creation.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow authorized team members to create a show within a specific team.
- **FR-002**: System MUST require each show to include date and venue at creation.
- **FR-003**: System MUST require venue to be selected from existing venue records.
- **FR-004**: System MUST allow each show to include an optional show name.
- **FR-005**: System MUST allow time and artist fee to be omitted at creation or update.
- **FR-006**: System MUST allow notes to be omitted, and when provided, recorded as optional free-form text for each show.
- **FR-007**: System MUST validate required fields and reject show creation or updates when required data is missing or invalid.
- **FR-008**: System MUST allow authorized team members to view all shows within their team scope.
- **FR-009**: System MUST allow authorized team members to edit show name, date, time, venue, notes, and fee.
- **FR-010**: System MUST keep each show scoped to exactly one team and prevent cross-team visibility by unauthorized users.
- **FR-011**: System MUST treat the owning team as the artist context and MUST NOT require a separate artist name field on the show record.
- **FR-012**: System MUST allow a show to have zero, one, or many links to other existing objects in the system.
- **FR-013**: System MUST allow authorized team members to add and remove links between a show and other objects during both create and update flows.
- **FR-014**: System MUST preserve existing show data and links when unrelated show fields are updated.
- **FR-015**: System MUST provide clear user-facing feedback when show creation, update, or link operations succeed or fail.
- **FR-016**: System MUST present Shows as a dedicated tab within each Team context.
- **FR-017**: System MUST render the Team Shows list as a table that includes date and venue columns.

### Key Entities *(include if feature involves data)*

- **Show**: A scheduled performance within a team, containing required date and venue (from existing venue records), plus optional show name, time, notes, and artist fee.
- **Team**: Organizational scope that owns shows and governs who can create, view, edit, and link show records.
- **Show Link**: Association between a show and another existing object, enabling contextual cross-reference.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 95% of authorized users can create a new show with required fields in under 2 minutes.
- **SC-002**: 99% of valid show updates are reflected to users immediately after save with no manual refresh or retry.
- **SC-003**: 100% of show access attempts from users outside the owning team are denied.
- **SC-004**: 90% of users can successfully add at least one related-object link to a show on first attempt during acceptance testing.

## Assumptions

- Only users with existing team-level management permissions can create or edit shows.
- Each team represents a single artist context, so show records do not require a separate artist-name field.
- Show name is optional and may be omitted without blocking creation or editing.
- Date and venue are required; time, artist fee, and notes are optional.
- Venue selection uses existing venue records.
- Artist fee is stored as a monetary amount in the project’s existing default currency unless broader multi-currency behavior is specified later.
- Links connect shows only to objects that already exist; link creation does not create new target objects.
- Existing team, permissions, and object-access behaviors are reused for show visibility and editing rights.
