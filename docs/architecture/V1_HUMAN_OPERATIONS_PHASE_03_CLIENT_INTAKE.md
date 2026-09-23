# Notify Desk V1 Human Operations - Phase 03 Client Intake

## Status

Phase 03 is implemented on `codex/human-operations-simplification`. The scope is limited to Client intake, list presentation, and Client detail editing.

## Files Changed

- `app/Http/Controllers/ClientController.php`
- `app/ViewModels/ClientListViewModel.php`
- `lang/ar/notify.php`
- `lang/en/notify.php`
- `resources/css/app.css`
- `resources/views/clients/create.blade.php`
- `resources/views/clients/edit.blade.php`
- `resources/views/clients/index.blade.php`
- `resources/views/components/notify/client-card.blade.php`
- `tests/Feature/ClientIntakePhase03Test.php`
- `tests/Feature/PartnerReferralDomainTest.php`

## Add Client

The normal form exposes exactly six fields:

1. Business Name / اسم النشاط
2. Business Type / نوع النشاط
3. Contact Person / اسم الشخص (optional)
4. Mobile / رقم الهاتف
5. Area / المنطقة
6. Lead Source / مصدر العميل

Business Type remains the existing `business_category` field. No canonical category registry exists in the application, so the input offers suggestions from existing stored category values without inventing or restricting stored codes.

The form does not expose business phone, contact role, source reference, city, separate area, legacy business type, branch count, notes, social/location fields, commission data, attribution agreement notes, stage, status, or owner.

## Defaults Used

`ClientController::store` continues to set:

- `business_phone` from `phone`
- `primary_contact_role` to `owner` when a contact is supplied
- `city` from `city_area`
- `business_type` from `business_category`
- `number_of_branches` to `1`
- `primary_owner_id` to the authenticated user
- `stage` and `status` to `prospect`
- partner commission from the Partner default through `ClientPartnerAttributionService`

Creation redirects directly to the existing Client workspace. It does not create subscriptions, invoices, payments, or installations.

## Partner Conditional Behavior

The Partner selector appears immediately after Lead Source only when the stored source value `Partner` is selected. Moving away from Partner clears and disables the selected Partner in submitted UI state.

Server validation requires an active `partner_id` for the Partner source. The controller ignores a stale Partner selection for other sources. Staff-supplied commission percentages and attribution agreement notes are ignored; the existing attribution service creates the default commission snapshot. Existing historical attribution is preserved during unrelated edits, including archived Partner records.

## Clients Index

Desktop uses four operational columns:

- Business
- Contact
- Stage
- Next Action

Open, Call, and WhatsApp actions sit within the Next Action cell, so no extra CRM columns are introduced. Subscription and Amount Due are absent.

Mobile cards show business name, preferred operational contact, stage, next action, Open, and supported Call/WhatsApp actions. `Client::preferredOperationalContact()` remains authoritative, and the controller's existing eager loading remains in place. Existing search and stage filtering are unchanged.

## Client Edit

Basic Details appears first with:

- business name
- business category
- contact person
- mobile
- business phone when different
- area
- lead source
- conditional Partner identity

More Details is collapsed by default and retains contact role, source reference, branch count, city, area, Instagram, website, map URL, location description, and notes. Validation opens it when one of its fields is invalid.

Raw stage/status, owner, partner commission, and partner attribution agreement notes are not rendered. Unsubmitted workflow fields cannot change lifecycle state. Historical advanced values and existing attribution snapshots remain intact.

## Validation And Authorization

Required-field messages use the frozen Arabic and English human wording. Errors render beside their fields, Laravel old input is retained, and Alpine focuses and scrolls to the first invalid field after a failed submission.

`ClientPolicy` remains authoritative for index, create, store, edit, and update. Guest and inactive-user boundaries were verified. No route or permission was added.

## Tests

Added `ClientIntakePhase03Test` with seven scenarios covering:

- the six visible fields and hidden complexity
- server defaults and prospect-only creation
- zero subscription, invoice, payment, and installation side effects
- Arabic/English validation and old input
- conditional Partner validation and backend-controlled commission
- desktop/mobile list output, preferred contact, and search
- edit history preservation, lifecycle protection, and authorization

Updated the existing Partner referral test to assert Staff receives the Partner default commission snapshot.

Verification completed:

- Focused Phase 03 tests: 7 passed, 92 assertions
- Related compatibility tests: 36 passed, 200 assertions
- Full `php artisan test`: 314 passed, 2179 assertions
- `php artisan view:cache`: passed
- `npm run build`: passed
- `git diff --check`: passed

## Manual QA

No approved local application server was already running, so live browser walkthroughs at 390px and 1440px were not performed. Request-render coverage verified Arabic and English output, both responsive presentations, conditional Partner markup, actions, and redirects. The responsive CSS provides a one-column form at 390px, persistent labels, touch-sized controls inherited from the shell, telephone input modes, and a submit footer fixed above the mobile navigation.

The Phase 02 shell was not changed.

## Dependencies And Boundaries

Implementation dependency UX-D06 was used only for conditional Partner validation and stale-selection integrity. The edit Partner query also replaced unsupported `orWhereKey()` with the equivalent `orWhere('id', ...)` so the existing archived-Partner option can render on this Laravel version.

No package dependency, migration, database schema change, subscription logic, financial logic, lifecycle architecture, domain service behavior, or Client workspace implementation was added.

The only newly observed product dependency is the absence of a canonical business-category registry. Phase 03 safely uses existing stored values as suggestions and keeps the existing free-text storage contract.

## Next Scope

Phase 04 should cover Client Workspace only.
