---
name: notify-architecture
description: Use for every Notify Desk implementation, refactor, or review governed by the frozen V1 architecture.
---

# Frozen architecture discipline

Before changing or reviewing code, determine the current P1-P18 phase. Read only the sections of `NOTIFY_DESK_V1_FINAL_ARCHITECTURE.md` needed for that phase's scope, dependencies, risks, exit criteria, and affected domain rules.

- Treat the frozen architecture as the sole implementation authority. It overrides older documents, comments, prior reports, and obsolete tests.
- Never reopen or reinterpret a `[FROZEN D-xx]` decision.
- Do not use superseded architecture documents as implementation sources.
- When the architecture leaves a genuine business-rule gap, stop and report the gap; do not guess or invent behavior.
