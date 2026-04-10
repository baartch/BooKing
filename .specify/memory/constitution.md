<!-- Sync Impact Report
- Version change: N/A (template) → 1.0.0
- Modified principles: Initial adoption (placeholders filled)
- Added sections: Core Principles content, Architecture & Stack Constraints,
  Development Workflow & Quality Gates, Governance rules
- Removed sections: None
- Templates requiring updates:
  - ✅ Reviewed: .specify/templates/plan-template.md
  - ✅ Reviewed: .specify/templates/spec-template.md
  - ✅ Reviewed: .specify/templates/tasks-template.md
  - N/A: .specify/templates/commands (no command templates found)
- Follow-up TODOs:
  - TODO(RATIFICATION_DATE): adoption date not recorded yet
-->
# BooKing Constitution

## Core Principles

### I. MVC Separation of Concerns (NON-NEGOTIABLE)
Controllers MUST own HTTP request/response flow and delegate to models/services.
Models in `app/models/` MUST encapsulate domain logic and data access. Views in
`app/views/` and `app/partials/` MUST contain presentation only, with no direct
DB access or business logic. This keeps the MVC architecture consistent and
maintainable.

### II. Approved Stack & Data Store
The application MUST remain a PHP 8+ web app backed by MariaDB/MySQL. Schema
changes MUST be reflected in `sql/schema.sql`. Introducing alternative data
stores or runtimes requires explicit approval because it impacts deployment and
operations.

### III. Frontend Assets via Bulma + TypeScript Pipeline
Styling MUST use Bulma (via CDN) with no inline CSS in PHP templates. JavaScript
changes MUST be made in `src/` TypeScript sources and compiled to
`app/public/js/` using `bun run build`. Compiled JS files MUST NOT be edited
manually. This preserves a consistent asset pipeline.

### IV. Security Baseline Compliance
Security controls MUST remain in place: session cookies via
`app/models/auth/cookie_helpers.php`, CSRF tokens for POST forms, automatic
security headers, and login rate limiting. Any change to auth or session flow
MUST preserve these guarantees.

### V. Auditable Logging Without Sensitive Data
Significant actions MUST be logged via `logAction()` in
`app/models/core/database.php`. Logs MUST NOT include sensitive data (cookies,
secrets, or raw credentials). This ensures traceability without leaking secrets.

## Architecture & Stack Constraints

- MVC layout under `app/controllers/`, `app/models/`, and `app/views/` is the
  canonical structure; shared fragments live in `app/partials/`.
- Configuration files MUST live in `config/` and be limited to runtime config
  (e.g., `config.php`).
- UI tables MUST use partials in `app/partials/tables/`.
- Mapping features rely on Leaflet + Mapbox; changes MUST preserve integration
  points and required API keys.

## Development Workflow & Quality Gates

- Use HTMX helpers (`app/models/core/htmx_class.php`) for request/response
  metadata when building HTMX endpoints.
- Validate any new admin or auth features against security requirements and
  logging rules before release.
- Rebuild frontend assets after TypeScript changes and verify generated output
  in `app/public/js/`.

## Governance

- This constitution supersedes other guidance. Conflicts MUST be resolved in
  favor of these principles.
- Amendments require documentation in this file, a rationale in the Sync Impact
  Report, and a semantic version bump.
- Versioning policy: MAJOR for removals/redefinitions, MINOR for new or expanded
  principles, PATCH for clarifications.
- Every plan/spec review MUST include a constitution compliance check.

**Version**: 1.0.0 | **Ratified**: TODO(RATIFICATION_DATE): adoption date not recorded yet | **Last Amended**: 2026-04-10
