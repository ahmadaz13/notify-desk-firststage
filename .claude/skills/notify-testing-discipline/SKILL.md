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

## Regression execution

- Run affected regression tests in deterministic groups of at most 3–6 files.
- Do not run large mixed regression batches.
- If a regression group exceeds 5 minutes without a final result, stop that process and split the group.
- Never use indefinite polling loops or repeated background wait loops.
- Do not rerun already-green groups unless relevant production code changed afterward.

## Visual QA during implementation

- Phase-level visual QA is smoke testing only.
- Default widths: 390px, 820px, 1440px.
- Check only:
  1. page renders,
  2. no horizontal overflow,
  3. primary action/form usable,
  4. no obvious runtime/console error.
- Do not perform exhaustive screenshot or pixel-level QA during implementation phases.
- P16 owns comprehensive visual/manual QA.

## Full suite

- Run one final full suite after targeted and regression tests are green.
- If the only failure is proven to be a stale test expectation caused by the current intentional architecture change, and only test code is changed to correct it, rerunning that exact affected test file is sufficient; do not rerun the entire suite solely because test code changed.
- If production code changes after the full-suite run, run the full suite again.
