---
name: notify-phase-report
description: Use at the end of every Notify Desk implementation phase before any next phase begins.
---

# Phase completion report

Return valid JSON only, then stop. Do not begin the next phase.

Include these fields:

- `status`, `phase`, `branch`, and `commit`
- `architecture_compliance` and `summary`
- `files_changed`, `migrations_added_or_changed`, `services_added_or_changed`, and `controllers_routes_changed`
- `tests`, separating targeted, affected-regression, and full-suite results
- `financial_invariants` when applicable
- `security_findings`, `known_issues`, and `pre_existing_baseline_failures`
- `architecture_gaps_found`, `manual_review_needed`, and `ready_for_next_phase`

Use explicit empty arrays or `null` values where appropriate rather than omitting required fields.
