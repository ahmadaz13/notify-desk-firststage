---
name: notify-mobile-ui
description: Use when changing Notify Desk Blade, CSS, navigation, dashboards, tables, lists, modals, forms, Today, or Client Workspace UI.
---

# Mobile-first UI requirements

Design and verify in this order: 390px phone, 768/820px iPad portrait, 1024px iPad landscape, then desktop.

- Phone uses bottom navigation, never the desktop sidebar.
- Use a full-screen sheet or page for important phone forms where appropriate.
- Keep touch targets at least 44px and form text at least 16px.
- Prevent horizontal page overflow. Dense desktop rows become cards on phone.
- Present one obvious primary action; place secondary actions under More.
- Use business terminology, not implementation terminology.
- V1 is light-theme only. Extend design tokens instead of adding hard-coded colors.
- Apply the device, list, localization, and theme rules in `NOTIFY_DESK_V1_FINAL_ARCHITECTURE.md` to every touched UI.
