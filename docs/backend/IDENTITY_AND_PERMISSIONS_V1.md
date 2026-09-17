# Notify V1 Identity And Permissions

Backend Closure G3 establishes the active V1 identity model.

## Active application users

Active Notify application users are internal users only. Founder is the canonical account provisioned for live V1:

- `founder`: full owner-level operational, financial, accounting, reporting, and system access.
- `admin`: accepted owner-level compatibility/future value; no Admin management workflow is implemented in V1.
- `staff`: accepted compatibility value for existing records and operational tests; no new staff-management workflow is implemented in V1.
- `employee`: future reserved value and not an active V1 identity.

Authorization must not depend on personal names such as Ahmad or Khalid. It must use the role/capability checks implemented in models, policies, middleware, gates, and controller authorization.

`EnsureActiveInternalUser` protects authenticated application routes. `User::isActiveApplicationUser()` is the shared active identity predicate. Legacy `role=partner` rows are historical only and are not active application users.

## Partner policy

Partner is not an active login, role, owner, or authorization scope in V1. Partner records remain only as referral/history records.

Retired active partner workflows:

- partner login and partner-specific login identifiers
- first-login/onboarding credential workflow
- partner dashboard
- partner dashboard redirects and navigation
- partner password generation, reset, and credential management
- partner-specific client visibility isolation
- partner-specific CSV ownership
- partner-specific operational notifications or permissions
- active partner financial/share dashboard logic

Partner compatibility routes may remain registered only when useful for historical compatibility. They must not expose an active partner workflow.

Scheduled operational notifications follow the same identity boundary. Active reminders and commercial finance alerts are delivered only to internal application users; legacy partner-role users are not notification recipients.

## Historical data preservation

G3 does not drop partner tables, partner foreign keys, or historical partner metadata.

Preserved data includes:

- `partners` rows
- `partner_id` references on historical clients and conflict-resolution records
- referrer identity and lead source metadata
- historical partner commission/share/deduction metadata
- audit and activity history
- historical client origin

Deleting a partner from the management UI archives/suspends the partner record instead of hard-deleting it.

## Referral attribution

Referral attribution is descriptive metadata. It can record that a lead came from a partner, referral, delegate link, import, or other source, but it does not control authorization, ownership, client visibility, revenue, MRR, ARR, accounting, or recognized revenue.

Gate 2 commission values are read-only projections from canonical V2 cash movements and the Client attribution basis-point snapshot. They do not create a payable, expense, journal, or accounting balance. Hidden legacy percentages, deductions, and earned-share accessors are not company financial authority.

## Client assignment

Operational responsibility and referral source are separate:

- `primary_owner_id` and operational attendees/installers must reference internal users.
- `partner_id` is referral/history attribution only.
- Historical partner-attributed clients remain visible to authorized internal users.
- CSV import assigns internal ownership to the importing internal user and does not assign partner ownership.
- Public delegate lead creation can preserve `partner_id` attribution, but it cannot create users or grant privileges.

## Permission boundaries

Normal staff operations are allowed through client policies and active-internal middleware:

- create/update prospects and client details
- contacts and contact attempts
- appointments and follow-ups
- meeting outcomes
- free installation scheduling/completion
- operational review items
- operational queues

High-risk finance and system actions remain admin-only or governed by existing strict `FinancialPermissions` gates:

- plan/service and price management
- financial account configuration
- expense category, vendor, and recurring-expense configuration
- funding sources, capital funding, asset categories, and fixed assets
- accounting administration, accounting periods, backfills, and revenue-recognition administration
- subscription billing administration and billing review resolution
- financial statements, executive reporting, SaaS metrics, and exports
- system settings and legacy/archive administration

Blade visibility is not authorization. Active routes and write endpoints must enforce server-side middleware, policy, gate, or controller authorization.
