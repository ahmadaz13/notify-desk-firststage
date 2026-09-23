# Notify Desk V1 Human Operations Phase 02: Application Shell

## Checkpoint

Phase 02 implements the approved application-shell hierarchy from
`V1_HUMAN_OPERATIONS_UX_ARCHITECTURE.md`. The work is limited to shell markup,
navigation presentation, translations, icons, and shell-focused tests. No routes,
controllers, services, policies, models, migrations, or business workflows were
changed.

## Files Changed

- `resources/views/components/notify/app-shell.blade.php`
- `resources/views/components/notify/mobile-nav.blade.php`
- `resources/views/components/notify/nav-group.blade.php`
- `resources/views/components/notify/icon.blade.php`
- `resources/views/components/notify/add-client-fab.blade.php` (removed)
- `resources/css/app.css`
- `resources/js/app.js`
- `lang/en/notify.php`
- `lang/ar/notify.php`
- `tests/Feature/ApplicationShellNavigationTest.php`
- `tests/Feature/Phase1VerificationTest.php`
- `tests/Feature/RouteConsolidationTest.php`
- `docs/architecture/V1_HUMAN_OPERATIONS_PHASE_02_APPLICATION_SHELL.md`

## Implemented Navigation

### Daily

- Today
- Clients
- Work

These are the only first-level daily destinations on desktop. They are also the
first three fixed mobile destinations, followed by More.

### Management

Management is visually separated from Daily and shown through compact grouped
disclosures. Empty groups are omitted.

| Group | Destinations |
| --- | --- |
| Commercial | Subscription Management, Products & Pricing, Partners |
| Money | Collections, Finance, Operating Expenses, Capital Management |
| Reports | Executive, SaaS Metrics |
| Operations Admin | Import, Conflicts |
| System | Settings |

### Advanced

Advanced is subordinate to Management and contains Financial Accounts and
Accounting when the existing permission gates allow access.

## Route Mapping

| Shell destination | Existing route |
| --- | --- |
| Today | `dashboard?mode=daily` |
| Clients | `clients.index` |
| Work | `dashboard?mode=work` |
| More | UI surface only; no route added |
| Subscription Management | `subscription-billing.index` |
| Products & Pricing | `commercial-catalog.index` |
| Partners | `partners.index` |
| Collections | `collections.index` |
| Finance | `finance.index` |
| Operating Expenses | `operating-expenses.index` |
| Capital Management | `capital-management.index` |
| Executive | `executive.index` |
| SaaS Metrics | `saas-metrics.index` |
| Import | `clients.import` |
| Conflicts | `conflicts.index` |
| Settings | `settings.index` |
| Financial Accounts | `financial-accounts.index` |
| Accounting | `accounting.index` |

Revenue-recognition controls remain inside the existing Accounting destination;
no duplicate route or shell destination was introduced.

## Permission Behavior

The shell uses the existing `ClientPolicy`, `FinancialPermissions` gates, and
admin role checks. It does not introduce a parallel authorization model.

- Staff sees Today, Clients, Work, More, and authorized operational utilities
  such as Import.
- Founder/Admin sees the permitted Management groups and Advanced destinations.
- Hidden navigation does not replace controller authorization; direct protected
  requests continue to return 403 for unauthorized users.
- Add Client appears only on Today and the Clients index when `ClientPolicy::create`
  allows it.

## Mobile More Sheet

More opens a permission-filtered bottom sheet with a backdrop, dialog semantics,
Escape handling, focus transfer to the close control, and focus return to More.
Management, Advanced, and Account utilities are separated. Notifications are not
duplicated in the sheet and remain available through the header bell only.

## Active State Rules

- Dashboard `mode=daily` (or no mode) activates Today.
- Dashboard `mode=work` activates Work.
- Client routes activate Clients, except CSV import routes, which activate Import.
- Nested management and advanced route families activate their destination and
  open the relevant desktop group.

## Localization And Responsive Contract

The shell uses translation keys for English and Arabic labels. Canonical daily
labels are Today/اليوم, Clients/العملاء, Work/العمل, and More/المزيد. Layout uses
logical CSS properties, mobile-safe wrapping, 44px-or-larger interactive targets,
and a scrollable More sheet for constrained heights.

## Verification Scope

The checkpoint is verified through shell feature tests, full Laravel tests, Blade
view compilation, the Vite production build, and responsive review at 390px,
768px, and 1440px in English and Arabic where the local environment permits live
rendering.

### Verification Results

- Shell feature suite: 7 passed, 67 assertions.
- Full Laravel suite: 307 passed, 2,087 assertions.
- Blade compilation: `php artisan view:cache` passed.
- Frontend build: `npm run build` passed.
- Responsive contract: English/LTR and Arabic/RTL markup, logical properties,
  mobile breakpoints, label wrapping, sheet scrolling, safe-area spacing, and
  touch targets were reviewed statically for 390px, 768px, and 1440px behavior.
  Live screenshots were not captured because the available browser sandbox
  blocked local-file rendering and the phase rules prohibit starting a server
  without an explicit request.

### Tests Added Or Updated

- Added shell-specific authorization, permission filtering, active-state,
  localization, notification, and Add Client placement coverage.
- Updated two legacy assertions that required the retired floating Add Client
  button so they now validate the compact header action.

### Manual QA Matrix

| User | Locale | Width | Result |
| --- | --- | --- | --- |
| Staff | Arabic RTL | 390px | Static shell and permission contract reviewed; live screenshot blocked |
| Staff | English LTR | 390px | Static shell and permission contract reviewed; live screenshot blocked |
| Founder/Admin | Arabic RTL | 390px | More hierarchy and wrapping reviewed statically; live screenshot blocked |
| Founder/Admin | Arabic RTL | 1440px | Sidebar hierarchy reviewed statically; live screenshot blocked |
| Founder/Admin | English LTR | 768px | Tablet breakpoint and More layout reviewed statically; live screenshot blocked |

## Known Dependencies

Phase 02 intentionally does not resolve Phase 01 dependencies or redesign domain
screens. Existing route availability and backend authorization remain the source
of truth.

## Known Shell Limitations

- The available browser sandbox rejected local-file rendering, so this checkpoint
  has no live responsive screenshots. Automated markup coverage and static CSS
  review passed, but a live 390px/768px/1440px visual pass remains advisable when
  an approved local server is available.
- Revenue Recognition remains part of Accounting because no standalone authorized
  GET destination exists.

## Domain Authority Confirmation

No domain authority, financial behavior, subscription behavior, lifecycle rule,
policy, permission, route, controller, service, model, migration, or database data
was changed in Phase 02.

## Next Phase

Phase 03 should cover Client Intake only.
