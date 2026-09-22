<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CapitalExpenseController;
use App\Http\Controllers\CapitalManagementController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientReviewItemController;
use App\Http\Controllers\ClientStageController;
use App\Http\Controllers\CommercialCatalogController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\ConflictResolutionController;
use App\Http\Controllers\ContactAttemptController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DailyNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExecutiveDashboardController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\FreeInstallationController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\GuidedSubscriptionController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MeetingOutcomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperatingExpenseController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PartnerDashboardController;
use App\Http\Controllers\PublicClientController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SaasMetricsController;
use App\Http\Controllers\SubscriptionBillingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Middleware\EnsureActiveInternalUser;
use Illuminate\Support\Facades\Route;

// Health Check (Public, Rate Limited)
Route::get('/health', HealthCheckController::class)->middleware('throttle:60,1')->name('health');

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Public Delegate Form (No Login Required)
Route::get('/p/{uuid}/client/create', [PublicClientController::class, 'create'])->name('public.client.create');
Route::post('/p/{uuid}/client', [PublicClientController::class, 'store'])->name('public.client.store');

Route::middleware(['auth', EnsureActiveInternalUser::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/work', [DashboardController::class, 'work'])->name('work');
    Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

    // Daily Notes
    Route::put('/daily-notes', [DailyNoteController::class, 'save'])->name('daily-notes.save');

    // Client Management
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::post('/clients/{client}/convert', [ClientController::class, 'convert'])->name('clients.convert');
    Route::get('/clients/{client}/guided-subscription/catalog', [GuidedSubscriptionController::class, 'catalog'])->name('clients.guided-subscription.catalog');
    Route::post('/clients/{client}/guided-subscription/preview', [GuidedSubscriptionController::class, 'preview'])->name('clients.guided-subscription.preview');
    Route::post('/clients/{client}/guided-subscription', [GuidedSubscriptionController::class, 'store'])->middleware('financial.idempotency')->name('clients.guided-subscription.store');
    Route::post('/clients/{client}/paid-subscriptions', [BillingController::class, 'startPaidSubscription'])->middleware('financial.idempotency')->name('clients.paid-subscriptions.store');
    Route::post('/clients/{client}/one-time-invoices', [BillingController::class, 'storeOneTimeInvoice'])->middleware('financial.idempotency')->name('clients.one-time-invoices.store');
    Route::post('/clients/{client}/payments/normal', [CollectionsController::class, 'storeNormalPayment'])->middleware('financial.idempotency')->name('clients.payments.normal.store');
    Route::post('/clients/{client}/collections/payments', [CollectionsController::class, 'storePayment'])->middleware('financial.idempotency')->name('clients.collections.payments.store');
    Route::post('/clients/{client}/credit-notes', [CollectionsController::class, 'storeCreditNote'])->middleware('financial.idempotency')->name('clients.credit-notes.store');
    Route::patch('/clients/{client}/stage', [ClientStageController::class, 'update'])->name('clients.stage.update');
    Route::post('/clients/{client}/close', [ClientStageController::class, 'close'])->name('clients.close');
    Route::post('/clients/{client}/reopen', [ClientStageController::class, 'reopen'])->name('clients.reopen');
    Route::post('/client-review-items/{reviewItem}/resolve', [ClientReviewItemController::class, 'resolve'])->name('client-review-items.resolve');
    Route::post('/client-review-items/{reviewItem}/dismiss', [ClientReviewItemController::class, 'dismiss'])->name('client-review-items.dismiss');
    Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('clients.contacts.store');
    Route::patch('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->name('clients.contacts.update');
    Route::post('/clients/{client}/contact-attempts', [ContactAttemptController::class, 'store'])->name('clients.contact-attempts.store');
    Route::post('/clients/{client}/installations/schedule', [FreeInstallationController::class, 'schedule'])->name('clients.installations.schedule');
    Route::post('/clients/{client}/installations/complete', [FreeInstallationController::class, 'complete'])->name('clients.installations.complete');

    // Appointments & Financial Transactions
    Route::post('/appointments', [DashboardController::class, 'storeAppointment'])->name('appointments.store');
    Route::patch('/appointments/{appointment}', [DashboardController::class, 'updateAppointment'])->name('appointments.update');
    Route::patch('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::post('/payments', [DashboardController::class, 'storePayment'])->name('payments.store');
    Route::post('/payments/{payment}/allocations', [CollectionsController::class, 'allocatePayment'])->middleware('financial.idempotency')->name('payments.allocations.store');
    Route::post('/payments/{payment}/auto-allocate', [CollectionsController::class, 'autoAllocatePayment'])->middleware('financial.idempotency')->name('payments.auto-allocate');
    Route::post('/payment-allocations/{paymentAllocation}/reverse', [CollectionsController::class, 'reverseAllocation'])->middleware('financial.idempotency')->name('payment-allocations.reverse');
    Route::post('/payments/{payment}/reverse', [CollectionsController::class, 'reversePayment'])->middleware('financial.idempotency')->name('payments.reverse');
    Route::post('/payments/{payment}/refunds', [CollectionsController::class, 'refundPayment'])->middleware('financial.idempotency')->name('payments.refunds.store');
    Route::post('/credit-notes/{creditNote}/applications', [CollectionsController::class, 'applyCreditNote'])->middleware('financial.idempotency')->name('credit-notes.applications.store');
    Route::post('/credit-note-applications/{creditNoteApplication}/reverse', [CollectionsController::class, 'reverseCreditApplication'])->middleware('financial.idempotency')->name('credit-note-applications.reverse');
    Route::post('/credit-notes/{creditNote}/void', [CollectionsController::class, 'voidCreditNote'])->middleware('financial.idempotency')->name('credit-notes.void');
    Route::post('/credit-notes/{creditNote}/refunds', [CollectionsController::class, 'refundCreditNote'])->middleware('financial.idempotency')->name('credit-notes.refunds.store');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::post('/investments', [InvestmentController::class, 'store'])->name('investments.store');
    Route::post('/capital-expenses', [CapitalExpenseController::class, 'store'])->name('capital-expenses.store');

    // Subscriptions Workflow
    Route::post('/subscriptions/{subscription}/plan-change', [SubscriptionController::class, 'schedulePlanChange'])->name('subscriptions.plan-change.schedule');
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'scheduleCancellation'])->name('subscriptions.cancel');
    Route::post('/subscriptions/{subscription}/cancel/undo', [SubscriptionController::class, 'undoCancellation'])->name('subscriptions.cancel.undo');
    Route::post('/subscriptions/{subscription}/reactivate', [SubscriptionController::class, 'reactivate'])->name('subscriptions.reactivate');
    Route::post('/invoices/{invoice}/void', [BillingController::class, 'voidInvoice'])->middleware('financial.idempotency')->name('invoices.void');

    // Contracts Workflow
    Route::post('/clients/{client}/subscriptions/{subscription}/contract', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract}/preview', [ContractController::class, 'preview'])->name('contracts.preview');
    Route::get('/contracts/{contract}/print', [ContractController::class, 'print'])->name('contracts.print');
    Route::get('/contracts/{contract}/download', [ContractController::class, 'download'])->name('contracts.download');
    Route::get('/contracts/{contract}/download-pdf', [ContractController::class, 'downloadPdf'])->name('contracts.download-pdf');
    Route::post('/contracts/{contract}/issue', [ContractController::class, 'issue'])->name('contracts.issue');
    Route::post('/contracts/{contract}/void', [ContractController::class, 'void'])->name('contracts.void');
    Route::post('/contracts/{contract}/supersede', [ContractController::class, 'supersede'])->name('contracts.supersede');

    // Partner Isolated Dashboard
    Route::get('/partner/dashboard', [PartnerDashboardController::class, 'index'])->name('partner.dashboard');

    // Partner Management (Admin Only)
    Route::post('/partners/{partner}/reset-password', [PartnerController::class, 'resetPassword'])->name('partners.reset-password');
    Route::resource('partners', PartnerController::class);

    // Conflict Resolution (Admin Only)
    Route::get('/conflicts', [ConflictResolutionController::class, 'index'])->name('conflicts.index');
    Route::get('/conflicts/{conflict}', [ConflictResolutionController::class, 'show'])->name('conflicts.show');
    Route::post('/conflicts/{conflict}/resolve', [ConflictResolutionController::class, 'resolve'])->name('conflicts.resolve');

    // Meeting Outcomes
    Route::get('/appointments/{appointment}/outcome', [MeetingOutcomeController::class, 'create'])->name('appointments.outcome.create');
    Route::post('/appointments/{appointment}/outcome', [MeetingOutcomeController::class, 'store'])->name('appointments.outcome.store');
    Route::post('/appointments/{appointment}/compact-outcome', [\App\Http\Controllers\CompactAppointmentOutcomeController::class, 'store'])->name('appointments.compact-outcome.store');

    // Follow-ups & Offers
    Route::post('/clients/{client}/follow-ups', [FollowUpController::class, 'store'])->name('clients.follow-ups.store');
    Route::post('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::post('/clients/{client}/offers', [OfferController::class, 'store'])->name('clients.offers.store');

    // CSV Import
    Route::get('/clients-import', [CsvImportController::class, 'index'])->name('clients.import');
    Route::post('/clients-import/preview', [CsvImportController::class, 'preview'])->name('clients.import.preview');
    Route::post('/clients-import/confirm', [CsvImportController::class, 'confirm'])->name('clients.import.confirm');
    Route::get('/clients-import/template/{type}', [CsvImportController::class, 'downloadTemplate'])->name('clients.import.template');

    // ─── Phase 6: Unified Administration Hub ───
    Route::get('/administration', [AdministrationController::class, 'index'])->name('administration.index');
    Route::get('/administration/team', [AdministrationController::class, 'team'])->name('administration.team');
    Route::get('/administration/team/create', [AdministrationController::class, 'teamCreate'])->name('administration.team.create');
    Route::post('/administration/team', [AdministrationController::class, 'teamStore'])->name('administration.team.store');
    Route::get('/administration/team/{id}/edit', [AdministrationController::class, 'teamEdit'])->name('administration.team.edit');
    Route::put('/administration/team/{id}', [AdministrationController::class, 'teamUpdate'])->name('administration.team.update');
    Route::post('/administration/team/{id}/reset-password', [AdministrationController::class, 'teamResetPassword'])->name('administration.team.reset-password');
    Route::post('/administration/team/{id}/deactivate', [AdministrationController::class, 'teamDeactivate'])->name('administration.team.deactivate');

    // Admin Settings & Control Center
    Route::get('/commercial-catalog', [CommercialCatalogController::class, 'index'])->name('commercial-catalog.index');
    Route::post('/commercial-catalog/products', [CommercialCatalogController::class, 'storeProduct'])->name('commercial-catalog.products.store');
    Route::patch('/commercial-catalog/products/{product}', [CommercialCatalogController::class, 'updateProduct'])->name('commercial-catalog.products.update');
    Route::post('/commercial-catalog/products/{product}/archive', [CommercialCatalogController::class, 'archiveProduct'])->name('commercial-catalog.products.archive');
    Route::post('/commercial-catalog/plans', [CommercialCatalogController::class, 'storePlan'])->name('commercial-catalog.plans.store');
    Route::patch('/commercial-catalog/plans/{plan}', [CommercialCatalogController::class, 'updatePlan'])->name('commercial-catalog.plans.update');
    Route::post('/commercial-catalog/plans/{plan}/archive', [CommercialCatalogController::class, 'archivePlan'])->name('commercial-catalog.plans.archive');
    Route::post('/commercial-catalog/plans/{plan}/prices', [CommercialCatalogController::class, 'storePrice'])->name('commercial-catalog.prices.store');
    Route::get('/collections', [CollectionsController::class, 'index'])->name('collections.index');
    Route::get('/financial-accounts', [FinancialAccountController::class, 'index'])->name('financial-accounts.index');
    Route::post('/financial-accounts', [FinancialAccountController::class, 'store'])->name('financial-accounts.store');
    Route::post('/financial-accounts/{financialAccount}/archive', [FinancialAccountController::class, 'archive'])->name('financial-accounts.archive');
    Route::post('/financial-transfers', [FinancialAccountController::class, 'storeTransfer'])->middleware('financial.idempotency')->name('financial-transfers.store');
    Route::post('/financial-transfers/{financialTransfer}/reverse', [FinancialAccountController::class, 'reverseTransfer'])->middleware('financial.idempotency')->name('financial-transfers.reverse');
    Route::post('/cash-events/assign-account', [FinancialAccountController::class, 'assignCashEvent'])->name('cash-events.assign-account');
    Route::get('/operating-expenses', [OperatingExpenseController::class, 'index'])->name('operating-expenses.index');
    Route::post('/operating-expenses', [OperatingExpenseController::class, 'storeExpense'])->middleware('financial.idempotency')->name('operating-expenses.store');
    Route::post('/operating-expenses/{expense}/reverse', [OperatingExpenseController::class, 'reverseExpense'])->middleware('financial.idempotency')->name('operating-expenses.reverse');
    Route::post('/expense-categories', [OperatingExpenseController::class, 'storeCategory'])->name('expense-categories.store');
    Route::patch('/expense-categories/{category}', [OperatingExpenseController::class, 'updateCategory'])->name('expense-categories.update');
    Route::post('/expense-categories/{category}/archive', [OperatingExpenseController::class, 'archiveCategory'])->name('expense-categories.archive');
    Route::post('/vendors', [OperatingExpenseController::class, 'storeVendor'])->name('vendors.store');
    Route::post('/vendors/{vendor}/archive', [OperatingExpenseController::class, 'archiveVendor'])->name('vendors.archive');
    Route::post('/recurring-expense-templates', [OperatingExpenseController::class, 'storeTemplate'])->name('recurring-expense-templates.store');
    Route::patch('/recurring-expense-templates/{template}', [OperatingExpenseController::class, 'updateTemplate'])->name('recurring-expense-templates.update');
    Route::post('/recurring-expense-obligations/generate', [OperatingExpenseController::class, 'generateRecurring'])->name('recurring-expense-obligations.generate');
    Route::post('/recurring-expense-obligations/{obligation}/pay', [OperatingExpenseController::class, 'payObligation'])->middleware('financial.idempotency')->name('recurring-expense-obligations.pay');
    Route::post('/recurring-expense-obligations/{obligation}/skip', [OperatingExpenseController::class, 'skipObligation'])->name('recurring-expense-obligations.skip');
    Route::post('/recurring-expense-obligations/{obligation}/cancel', [OperatingExpenseController::class, 'cancelObligation'])->name('recurring-expense-obligations.cancel');
    Route::get('/capital-management', [CapitalManagementController::class, 'index'])->name('capital-management.index');
    Route::get('/executive', [ExecutiveDashboardController::class, 'index'])->name('executive.index');
    Route::get('/saas-metrics', [SaasMetricsController::class, 'index'])->name('saas-metrics.index');
    Route::get('/saas-metrics/export/{report}', [SaasMetricsController::class, 'export'])->name('saas-metrics.export');
    Route::get('/finance', [FinanceReportController::class, 'index'])->name('finance.index');
    Route::get('/finance/export/{report}', [FinanceReportController::class, 'export'])->name('finance.export');
    Route::get('/subscription-billing', [SubscriptionBillingController::class, 'index'])->name('subscription-billing.index');
    Route::post('/subscription-billing/generate-renewals', [SubscriptionBillingController::class, 'generateRenewals'])->name('subscription-billing.generate-renewals');
    Route::post('/subscription-billing/backfill-periods', [SubscriptionBillingController::class, 'backfillPeriods'])->name('subscription-billing.backfill-periods');
    Route::post('/funding-sources', [CapitalManagementController::class, 'storeFundingSource'])->name('funding-sources.store');
    Route::post('/funding-sources/{fundingSource}/archive', [CapitalManagementController::class, 'archiveFundingSource'])->name('funding-sources.archive');
    Route::post('/capital-funding-transactions', [CapitalManagementController::class, 'storeFunding'])->middleware('financial.idempotency')->name('capital-funding-transactions.store');
    Route::post('/capital-funding-transactions/{transaction}/reverse', [CapitalManagementController::class, 'reverseFunding'])->middleware('financial.idempotency')->name('capital-funding-transactions.reverse');
    Route::post('/asset-categories', [CapitalManagementController::class, 'storeAssetCategory'])->name('asset-categories.store');
    Route::post('/asset-categories/{category}/archive', [CapitalManagementController::class, 'archiveAssetCategory'])->name('asset-categories.archive');
    Route::post('/fixed-assets', [CapitalManagementController::class, 'storeFixedAsset'])->middleware('financial.idempotency')->name('fixed-assets.store');
    Route::post('/fixed-assets/{asset}/reverse', [CapitalManagementController::class, 'reverseFixedAsset'])->middleware('financial.idempotency')->name('fixed-assets.reverse');
    Route::patch('/fixed-assets/{asset}/status', [CapitalManagementController::class, 'updateFixedAssetStatus'])->name('fixed-assets.status');
    Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
    Route::post('/accounting/chart-accounts/{chartAccount}/archive', [AccountingController::class, 'archiveAccount'])->name('accounting.chart-accounts.archive');
    Route::post('/accounting/periods/{period}/close', [AccountingController::class, 'closePeriod'])->name('accounting.periods.close');
    Route::post('/accounting/periods/{period}/reopen', [AccountingController::class, 'reopenPeriod'])->name('accounting.periods.reopen');
    Route::post('/accounting/backfill', [AccountingController::class, 'backfill'])->name('accounting.backfill');
    Route::post('/accounting/revenue-schedules/backfill', [AccountingController::class, 'backfillRevenueSchedules'])->name('accounting.revenue-schedules.backfill');
    Route::post('/accounting/revenue-recognition/run', [AccountingController::class, 'recognizeRevenue'])->name('accounting.revenue-recognition.run');
    Route::post('/accounting/revenue-recognition/schedules/{schedule}/confirm', [AccountingController::class, 'confirmRevenueRecognition'])->name('accounting.revenue-recognition.confirm');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/update', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/export', [SettingsController::class, 'export'])->name('settings.export');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});
