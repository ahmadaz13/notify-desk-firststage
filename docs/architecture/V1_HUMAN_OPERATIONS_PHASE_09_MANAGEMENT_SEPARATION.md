# NotifyDesk V1 Human Operations — Phase 09: Management Separation

## Overview

Phase 09 completes the structural and informational architecture separation across NotifyDesk between:
1. **Daily Operational Surfaces** (`Today`, `Clients`, `Work`)
2. **Management Surfaces** (`Commercial`, `Money`, `Reports`, `Operations Admin`, `System`)
3. **Advanced Engine Surfaces** (`Financial Accounts`, `Accounting`)

This phase focuses on navigation hygiene, discoverability, permission-gated visibility, and ensuring the application shell does not overwhelm operational staff while retaining structured, organized access for Founders and Administrators.

---

## 1. Information Architecture Separation

### A. Daily Operational Layer
Daily operations are designed for rapid, focused execution without distraction:
- **Desktop Sidebar Top Navigation**:
  - `Today` (`/dashboard?mode=daily` or `/`)
  - `Clients` (`/clients`)
  - `Work` (`/work` or `/dashboard?mode=work`)
- **Mobile Bottom Navigation**:
  - Exactly 4 primary destinations: `Today`, `Clients`, `Work`, `More`.
  - Notifications remain exclusively a top-header signal action and are not a bottom nav tab.
  - Contextual `+ Add Client` action appears in the header only when relevant (Today and Clients list) for authorized users.

### B. Management Layer (Founder / Administrator)
Deliberately organized into 5 cohesive management groups:
1. **Commercial**:
   - Subscription Management (`/subscription-management`)
   - Products & Pricing (`/products`)
   - Partners / Affiliates (`/partners`)
2. **Money**:
   - Collections (`/collections`)
   - Finance (`/finance`)
   - Operating Expenses (`/expenses`)
   - Capital Management (`/capital-management`)
3. **Reports**:
   - Executive Summary (`/reports/executive`)
   - SaaS Metrics (`/reports/saas-metrics`)
4. **Operations Admin**:
   - Import CSV (`/clients-import`)
   - Conflicts / Reviews (`/conflicts`)
5. **System**:
   - Settings (`/settings`)

### C. Advanced Engine Layer
Deliberately separated into a dedicated subordinate layer to prevent confusing engine internals with routine business activities:
- `Financial Accounts` (`/financial-accounts`)
- `Accounting` / General Ledger (`/accounting`)

---

## 2. Role-Based Navigation & Security Guarantees

| Surface / Group | Staff Role | Founder / Admin Role | Enforcement Mechanism |
|---|---|---|---|
| **Daily (Today, Clients, Work)** | Visible | Visible | `User::is_active` check |
| **Commercial (Subscriptions, Pricing, Partners)** | Hidden | Visible | `canManageSubscriptionManagement`, `canManageProducts`, `canManagePartners` |
| **Money (Collections, Finance, Expenses, Capital)** | Hidden | Visible | `FinancialPermissions::VIEW_COLLECTIONS`, `VIEW_FINANCE`, `MANAGE_EXPENSES`, `VIEW_CASH_MANAGEMENT` |
| **Reports (Executive, SaaS)** | Hidden | Visible | `FinancialPermissions::VIEW_FINANCIAL_REPORTS`, `VIEW_SAAS_METRICS` |
| **Operations Admin (Import, Conflicts)** | Permission-gated | Visible | `canImportClients`, `canViewConflicts` |
| **System (Settings)** | Hidden | Visible | `FinancialPermissions::MANAGE_FINANCIAL_SETTINGS` |
| **Advanced (Financial Accounts, Accounting)** | Hidden | Visible | `FinancialPermissions::VIEW_CASH_MANAGEMENT`, `VIEW_ACCOUNTING` |

> [!IMPORTANT]
> **Defense in Depth**: Navigation visibility filtering is strictly paired with backend middleware and policy authorization. Direct route access by unauthorized users results in HTTP 403 Forbidden.

---

## 3. Mobile "More" Sheet Architecture

- Accessible via the bottom navigation bar on mobile viewports (<1024px).
- Dynamic permission filtering ensures zero dead links or disabled menu placeholders are presented to staff.
- Includes language toggling (`العربية` / `English`) and session logout in an Account layer.
- Fully accessible with ARIA attributes (`aria-expanded`, `aria-controls`, `role="dialog"`).

---

## 4. Verification & Testing

Feature tests implemented in `tests/Feature/Phase09ManagementSeparationTest.php` and `tests/Feature/ApplicationShellNavigationTest.php`:
- `test_staff_sees_daily_navigation_only_and_more_is_permission_filtered`: Verified staff navigation exclusion.
- `test_founder_sees_grouped_management_and_subordinate_advanced_layers`: Verified 5 management groups and advanced layer for founder.
- `test_mobile_bottom_nav_has_exact_four_items_and_notifications_in_header_only`: Verified exact bottom nav structure.
- `test_desktop_daily_nav_not_polluted_by_finance_or_admin`: Verified clean daily navigation on desktop.

Result: **All navigation and separation tests passing.**
