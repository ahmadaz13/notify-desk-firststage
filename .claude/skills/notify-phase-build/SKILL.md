---
name: notify-phase-build
description: Use when implementing exactly one approved Notify Desk P1-P18 phase.
---

# Single-phase build workflow

1. Confirm the requested branch and inspect the working tree without discarding existing work.
2. Read the active phase's scope, dependencies, exit criteria, and relevant risks in `NOTIFY_DESK_V1_FINAL_ARCHITECTURE.md`.
3. Inspect existing code before editing. Reuse or refactor proven services and engines instead of duplicating them.
4. Stay strictly inside the active phase; do not implement future phases opportunistically.
5. Iterate with targeted tests. After they pass, run affected regression tests, then one full-suite run at phase completion.
6. Audit the Git diff for scope leakage, then commit the completed phase.
7. Use `notify-phase-report` to return the phase JSON report and stop before starting the next phase.
