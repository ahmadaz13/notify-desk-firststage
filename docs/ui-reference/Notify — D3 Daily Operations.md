# Notify — D3 Daily Operations

## D3 status

**Status:** Complete. This phase defines the high-fidelity operational experience for Notify, covering the daily sales and service workflow from prospect contact through installation and follow-up. It uses the locked D2 visual system and D1 architecture.

## Operational focus

The design prioritizes the question **"What should I do now?"** by centering the experience on actionable queues, due times, and clear next steps. We have established a consistent interaction model where recording outcomes, scheduling appointments, and completing installations feel identical regardless of the entry point.

## Required JSON report

```json
{
  "phase": "D3_DAILY_OPERATIONS",
  "status": "COMPLETE",
  "screens_completed": [
    "HOME_01 — Today",
    "CLIENTS_01 — Clients",
    "CLIENT_DETAIL_01 — Client Workspace",
    "CONTACT_01 — Record Contact Outcome",
    "APPOINTMENTS_01 — Appointments",
    "INSTALL_01 — Installations & Follow-ups",
    "ADD_CLIENT — Add Client",
    "MOBILE_WORK — Work destination"
  ],
  "home_01": {
    "desktop": "Prioritized 2-column layout with actionable queues (Now, Follow-up, Money, New) and compact snapshots.",
    "mobile": "Action-first vertical stack with quick-access queue cards and bottom navigation.",
    "primary_hierarchy": ["Callbacks Due", "Appointments Today", "Installations Today", "Trial Follow-ups", "Decision Pending", "Client Reviews", "Renewals Due", "Overdue Collections", "New Prospects"],
    "queues": ["Now", "Follow-up Required", "Money Requiring Attention", "New"],
    "secondary_information": ["Recent Activity", "Subscription Snapshot", "Collections Snapshot", "Cash Snapshot"]
  },
  "clients_01": {
    "desktop": "High-density table with operational filters (Stage, Type, User, Due, Active) and primary contextual actions.",
    "mobile": "Actionable cards prioritizing Next Action and Due Time with one-tap primary actions.",
    "default_fields": ["Business", "Business Type", "Primary Phone", "Stage", "Next Action", "Due", "Responsible User", "Last Activity"],
    "filters": ["All Clients", "Prospects", "Subscribers", "Overdue", "Stage", "Type", "User"],
    "actions": ["Call", "WhatsApp", "Record Outcome", "Schedule Appointment", "Open Client"]
  },
  "client_detail_01": {
    "desktop": "3-column workspace: Entity Header, Section Navigation (Overview, Contacts, Timeline, etc.), and Contextual Side Panels.",
    "mobile": "Stacked workspace with sticky header and scrollable section navigation.",
    "workspace_sections": ["Overview", "Contacts", "Timeline", "Appointments", "Installation & Follow-up", "Subscription & Billing (placeholder)", "Notes"],
    "primary_context_rules": [
      "Contacting → Record Outcome / Call",
      "Appointment → Review Appointment",
      "Installation Scheduled → Installation Action",
      "Free Installation Completed → Follow Up",
      "Decision Pending → Follow Up"
    ]
  },
  "contact_01": {
    "desktop_pattern": "Contextual drawer from the right side.",
    "mobile_pattern": "Bottom sheet or full-height sheet.",
    "outcomes": ["Appointment", "No Contact", "Callback Later", "No Answer / Busy", "Wrong / Invalid", "Not Interested"],
    "progressive_fields": {
      "Appointment": "Date & Time",
      "Callback Later": "Date & Time",
      "Not Interested": "Required Note",
      "Wrong / Invalid": "Review Required state"
    }
  },
  "appointments_01": {
    "desktop": "Action-oriented list grouped by Today, Upcoming, and Overdue.",
    "mobile": "Time-oriented cards with direct 'Record Result' actions.",
    "primary_information": ["Client", "Time", "Location/Contact", "Responsible User", "Current State"],
    "appointment_outcomes": ["Installation Scheduled", "Follow-up Required", "Decision Pending", "Close"]
  },
  "install_01": {
    "desktop": "Two-panel operational area for Scheduled Installations and Trial Follow-ups.",
    "mobile": "Grouped cards with 'Complete Installation' and 'Record Follow-up' actions.",
    "installation_flow": ["Scheduled", "Complete Installation", "Installed Free state", "Auto 3-day follow-up"],
    "followup_flow": ["Installation Date", "Follow-up Due", "Contact Action", "Result (Subscribe/Pending/Closed)"]
  },
  "add_client": {
    "desktop": "Focused modal or drawer for prospect creation.",
    "mobile": "Full-height sheet with simple field entry.",
    "default_fields": ["Business Name", "Business Type", "Business Phone", "Contact Name", "Contact Phone", "Lead Source"],
    "optional_fields": ["Address", "Notes", "Secondary Contacts"]
  },
  "mobile_work": {
    "structure": ["Calls & Outcomes", "Appointments", "Installations", "Follow-ups"],
    "difference_from_today": "Today is prioritized by urgency; Work is structured by operational category."
  },
  "responsive_rules_applied": [
    "Desktop persistent sidebar → Mobile bottom navigation.",
    "Desktop multi-column workspace → Mobile stacked panels.",
    "Desktop data tables → Mobile actionable cards.",
    "Desktop drawers → Mobile bottom sheets.",
    "Desktop multi-column forms → Mobile single column."
  ],
  "states_designed": [
    "Normal", "Empty", "Loading", "No Results", "Validation Error", "Success", "Overdue", "Requires Review", "Closed / Archived", "Permission Denied"
  ],
  "d2_components_reused": [
    "Color tokens", "Inter typography", "Hairline borders", "Radius scale", "Button hierarchy", "Form fields", "Status badges", "Queue cards", "Client cards", "Workspace shell", "Table patterns", "Financial display", "Overlays"
  ],
  "backend_rules_respected": [
    "Free installation does not mean Subscriber.",
    "Installation completion creates 3-day follow-up.",
    "Add Client creates a prospect, not a subscriber.",
    "No automatic transitions between lifecycle stages.",
    "Closed clients keep history and may be reopened."
  ],
  "locked_d3_decisions": [
    "English / LTR design.",
    "Lucide Icons.",
    "Today as the default landing page.",
    "Client Workspace as the central business context.",
    "Contextual primary actions based on stage.",
    "Progressive disclosure in Contact Outcome.",
    "Separation of Today (urgency) and Work (category) on mobile."
  ],
  "assumptions": [
    "Users prefer action-oriented lists over complex calendars for appointments.",
    "The 3-day follow-up rule is a strict operational default.",
    "Admin and Staff share the same operational interaction patterns."
  ],
  "real_ambiguities": [
    "Exact technician assignment logic for installations.",
    "Specific validation rules for international vs local phone numbers.",
    "Detailed history log filtering rules."
  ],
  "decisions_needed_before_D4": [
    "Confirm the exact subscription package selection UI (D4).",
    "Confirm the payment recording fields and allocation logic (D4).",
    "Define the 'Decision Pending' follow-up cadence."
  ],
  "recommended_D4_scope": [
    "Design the high-fidelity Sales & Billing domain (SALES_01).",
    "Design the Start Subscription flow (SUBSCRIPTION_START_01).",
    "Design Subscription Detail and lifecycle actions.",
    "Design Renewals, Invoices, and Collections recording.",
    "Integrate Sales & Billing context into the Client Workspace."
  ],
  "next_phase": "D4_SALES_AND_BILLING"
}
```

## D3 completion boundary

D3 stops at high-fidelity operational design. It does not begin D4, produce finance/report/admin screens, write production code, or redesign backend logic.

## References

[1]: /home/ubuntu/upload/pasted_content.txt "NOTIFY_DESIGN_SOURCE_V1 product source of truth"
[2]: /home/ubuntu/D0_NOTIFY_PRODUCT_UNDERSTANDING.md "Approved D0 Notify Product Understanding report"
[3]: /home/ubuntu/D1_NOTIFY_INFORMATION_ARCHITECTURE.md "Approved D1 Notify Information Architecture report"
[4]: /home/ubuntu/D2_NOTIFY_DESIGN_SYSTEM.md "Approved D2 Notify Design System report"
[5]: /home/ubuntu/upload/pasted_content_5.txt "Notify D3 Daily Operations phase brief"
