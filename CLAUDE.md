# Notify Desk Agent Instructions

- `NOTIFY_DESK_V1_FINAL_ARCHITECTURE.md` is the single implementation authority and is **FROZEN FOR IMPLEMENTATION**.
- Never reopen or reinterpret a `[FROZEN D-xx]` owner decision. If a genuine business-rule gap remains, stop and report it instead of guessing.
- Implement exactly one approved architecture phase at a time. Read that phase's scope, dependencies, risks, and exit criteria before editing.
- Inspect the existing implementation before creating replacements. Reuse proven services and engines whenever possible.
- Keep changes inside the active phase. Do not fix unrelated baseline failures or start the next phase automatically.
- Phone and iPad quality are release requirements, not optional polish.
- After each implementation phase: pass targeted tests, pass affected regressions, run the full suite once, audit the diff, commit, return the phase JSON report, and stop.
- Load the relevant project-local skills under `.claude/skills/` for architecture, phase execution, finance safety, mobile UI, testing, and reporting.
