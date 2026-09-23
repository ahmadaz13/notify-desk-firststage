---
name: notify-testing-discipline
description: Use while running, debugging, or modifying tests in every Notify Desk implementation phase.
---

# Phase testing discipline

- During development, run focused tests for the active behavior; do not repeatedly run the full suite.
- Update an old test only when the active phase intentionally changes what it asserts, and record the reason with the phase change.
- If an unrelated failure reproduces on the clean baseline, classify it as `PRE-EXISTING` and leave it outside the phase. Do not chase unrelated legacy failures.
- Do not create indefinite polling or wait loops. Use normal test commands with reasonable completion behavior and timeouts.
- After targeted tests pass, run affected regression tests, then one final full-suite run at phase end.
- Report only environments actually executed. Do not claim MySQL verification unless MySQL ran.
- Full SQLite and MySQL verification is P15; earlier phases follow their own architecture exit criteria.
