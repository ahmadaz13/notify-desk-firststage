<?php

namespace App\Support;

use App\Models\User;

/**
 * Canonical V1 permission registry and role matrix (§2.2–2.3).
 *
 * The matrix is code, not data: allows() = user active AND role listed for the permission.
 * Owner-level = founder + admin. Staff operates the company but never changes company rules
 * or creates authoritative money records.
 */
class Permissions
{
    // Existing permission strings (values preserved for compatibility)
    public const RECORD_PAYMENT = 'record_payment';
    public const MANAGE_SUBSCRIPTION_BILLING = 'manage_subscription_billing';
    public const MANAGE_EXPENSES = 'manage_expenses';
    public const MANAGE_EXPENSE_CATEGORIES = 'manage_expense_categories';
    public const MANAGE_VENDORS = 'manage_vendors';
    public const MANAGE_RECURRING_EXPENSES = 'manage_recurring_expenses';
    public const VIEW_EXPENSE_MANAGEMENT = 'view_expense_management';
    public const VIEW_FINANCIAL_REPORTS = 'view_financial_reports';
    public const MANAGE_COMMERCIAL_CATALOG = 'manage_commercial_catalog';
    public const MANAGE_INVOICES = 'manage_invoices';
    public const MANAGE_COLLECTION_CORRECTIONS = 'manage_collection_corrections';
    public const MANAGE_CREDIT_NOTES = 'manage_credit_notes';
    public const ISSUE_REFUNDS = 'issue_refunds';
    public const MANAGE_FINANCIAL_ACCOUNTS = 'manage_financial_accounts';
    public const MANAGE_CASH_TRANSFERS = 'manage_cash_transfers';
    public const ASSIGN_HISTORICAL_CASH_ACCOUNTS = 'assign_historical_cash_accounts';
    public const VIEW_CASH_MANAGEMENT = 'view_cash_management';
    public const MANAGE_FUNDING_SOURCES = 'manage_funding_sources';
    public const MANAGE_CAPITAL_FUNDING = 'manage_capital_funding';
    public const MANAGE_ASSET_CATEGORIES = 'manage_asset_categories';
    public const MANAGE_FIXED_ASSETS = 'manage_fixed_assets';
    public const VIEW_CAPITAL_MANAGEMENT = 'view_capital_management';
    public const VIEW_ACCOUNTING = 'view_accounting';
    public const MANAGE_CHART_OF_ACCOUNTS = 'manage_chart_of_accounts';
    public const MANAGE_ACCOUNTING_PERIODS = 'manage_accounting_periods';
    public const RUN_ACCOUNTING_BACKFILL = 'run_accounting_backfill';
    public const RUN_ACCOUNTING_RECONCILIATION = 'run_accounting_reconciliation';
    public const MANAGE_REVENUE_RECOGNITION = 'manage_revenue_recognition';
    public const RUN_REVENUE_RECOGNITION = 'run_revenue_recognition';
    public const RESOLVE_REVENUE_RECOGNITION_REVIEWS = 'resolve_revenue_recognition_reviews';
    public const VIEW_FINANCIAL_STATEMENTS = 'view_financial_statements';
    public const EXPORT_FINANCIAL_REPORTS = 'export_financial_reports';
    public const MANAGE_SUBSCRIPTION_LIFECYCLE = 'manage_subscription_lifecycle';
    public const RUN_SUBSCRIPTION_BILLING = 'run_subscription_billing';
    public const RESOLVE_SUBSCRIPTION_BILLING_REVIEWS = 'resolve_subscription_billing_reviews';
    public const VIEW_SAAS_METRICS = 'view_saas_metrics';
    public const EXPORT_SAAS_METRICS = 'export_saas_metrics';
    public const VIEW_EXECUTIVE_DASHBOARD = 'view_executive_dashboard';
    public const SUBMIT_PAYMENT_RECEIPT = 'submit_payment_receipt';
    public const APPROVE_PAYMENT_RECEIPTS = 'approve_payment_receipts';

    // V1 role-matrix additions
    public const MANAGE_SYSTEM_ACCESS = 'manage_system_access';
    public const START_PAID_SUBSCRIPTION = 'start_paid_subscription';
    public const ISSUE_CONTRACTS = 'issue_contracts';
    public const VIEW_COLLECTIONS_DUE = 'view_collections_due';
    public const EDIT_REFERRAL_COMMISSION = 'edit_referral_commission';
    public const MANAGE_CLIENT_CREDENTIALS = 'manage_client_credentials';
    public const REVEAL_CLIENT_CREDENTIALS = 'reveal_client_credentials';
    public const VIEW_CUSTOM_PROJECTS = 'view_custom_projects';
    public const MANAGE_CUSTOM_PROJECTS = 'manage_custom_projects';
    public const MANAGE_TEAM = 'manage_team';
    public const MANAGE_REFERENCE_DATA = 'manage_reference_data';
    public const IMPORT_CLIENTS = 'import_clients';
    public const MANAGE_COMPANY_SETTINGS = 'manage_company_settings';

    private const OWNER = [User::ROLE_FOUNDER, User::ROLE_ADMIN];
    private const OWNER_AND_STAFF = [User::ROLE_FOUNDER, User::ROLE_ADMIN, User::ROLE_STAFF];

    public const ROLE_MATRIX = [
        self::RECORD_PAYMENT => self::OWNER,
        self::MANAGE_SUBSCRIPTION_BILLING => self::OWNER,
        self::MANAGE_EXPENSES => self::OWNER,
        self::MANAGE_EXPENSE_CATEGORIES => self::OWNER,
        self::MANAGE_VENDORS => self::OWNER,
        self::MANAGE_RECURRING_EXPENSES => self::OWNER,
        self::VIEW_EXPENSE_MANAGEMENT => self::OWNER,
        self::VIEW_FINANCIAL_REPORTS => self::OWNER,
        self::MANAGE_COMMERCIAL_CATALOG => self::OWNER,
        self::MANAGE_INVOICES => self::OWNER,
        self::MANAGE_COLLECTION_CORRECTIONS => self::OWNER,
        self::MANAGE_CREDIT_NOTES => self::OWNER,
        self::ISSUE_REFUNDS => self::OWNER,
        self::MANAGE_FINANCIAL_ACCOUNTS => self::OWNER,
        self::MANAGE_CASH_TRANSFERS => self::OWNER,
        self::ASSIGN_HISTORICAL_CASH_ACCOUNTS => self::OWNER,
        self::VIEW_CASH_MANAGEMENT => self::OWNER,
        self::MANAGE_FUNDING_SOURCES => self::OWNER,
        self::MANAGE_CAPITAL_FUNDING => self::OWNER,
        self::MANAGE_ASSET_CATEGORIES => self::OWNER,
        self::MANAGE_FIXED_ASSETS => self::OWNER,
        self::VIEW_CAPITAL_MANAGEMENT => self::OWNER,
        self::VIEW_ACCOUNTING => self::OWNER,
        self::MANAGE_CHART_OF_ACCOUNTS => self::OWNER,
        self::MANAGE_ACCOUNTING_PERIODS => self::OWNER,
        self::RUN_ACCOUNTING_BACKFILL => self::OWNER,
        self::RUN_ACCOUNTING_RECONCILIATION => self::OWNER,
        self::MANAGE_REVENUE_RECOGNITION => self::OWNER,
        self::RUN_REVENUE_RECOGNITION => self::OWNER,
        self::RESOLVE_REVENUE_RECOGNITION_REVIEWS => self::OWNER,
        self::VIEW_FINANCIAL_STATEMENTS => self::OWNER,
        self::EXPORT_FINANCIAL_REPORTS => self::OWNER,
        self::MANAGE_SUBSCRIPTION_LIFECYCLE => self::OWNER,
        self::RUN_SUBSCRIPTION_BILLING => self::OWNER,
        self::RESOLVE_SUBSCRIPTION_BILLING_REVIEWS => self::OWNER,
        self::VIEW_SAAS_METRICS => self::OWNER,
        self::EXPORT_SAAS_METRICS => self::OWNER,
        self::VIEW_EXECUTIVE_DASHBOARD => self::OWNER,
        self::SUBMIT_PAYMENT_RECEIPT => self::OWNER_AND_STAFF,
        self::APPROVE_PAYMENT_RECEIPTS => self::OWNER,
        self::MANAGE_SYSTEM_ACCESS => self::OWNER_AND_STAFF,
        self::START_PAID_SUBSCRIPTION => self::OWNER_AND_STAFF,
        self::ISSUE_CONTRACTS => self::OWNER,
        self::VIEW_COLLECTIONS_DUE => self::OWNER_AND_STAFF,
        self::EDIT_REFERRAL_COMMISSION => self::OWNER,
        self::MANAGE_CLIENT_CREDENTIALS => self::OWNER_AND_STAFF,
        self::REVEAL_CLIENT_CREDENTIALS => self::OWNER_AND_STAFF,
        self::VIEW_CUSTOM_PROJECTS => self::OWNER_AND_STAFF,
        self::MANAGE_CUSTOM_PROJECTS => self::OWNER,
        self::MANAGE_TEAM => self::OWNER,
        self::MANAGE_REFERENCE_DATA => self::OWNER,
        self::IMPORT_CLIENTS => self::OWNER,
        self::MANAGE_COMPANY_SETTINGS => self::OWNER,
    ];

    public const ALL = [
        self::RECORD_PAYMENT,
        self::MANAGE_SUBSCRIPTION_BILLING,
        self::MANAGE_EXPENSES,
        self::MANAGE_EXPENSE_CATEGORIES,
        self::MANAGE_VENDORS,
        self::MANAGE_RECURRING_EXPENSES,
        self::VIEW_EXPENSE_MANAGEMENT,
        self::VIEW_FINANCIAL_REPORTS,
        self::MANAGE_COMMERCIAL_CATALOG,
        self::MANAGE_INVOICES,
        self::MANAGE_COLLECTION_CORRECTIONS,
        self::MANAGE_CREDIT_NOTES,
        self::ISSUE_REFUNDS,
        self::MANAGE_FINANCIAL_ACCOUNTS,
        self::MANAGE_CASH_TRANSFERS,
        self::ASSIGN_HISTORICAL_CASH_ACCOUNTS,
        self::VIEW_CASH_MANAGEMENT,
        self::MANAGE_FUNDING_SOURCES,
        self::MANAGE_CAPITAL_FUNDING,
        self::MANAGE_ASSET_CATEGORIES,
        self::MANAGE_FIXED_ASSETS,
        self::VIEW_CAPITAL_MANAGEMENT,
        self::VIEW_ACCOUNTING,
        self::MANAGE_CHART_OF_ACCOUNTS,
        self::MANAGE_ACCOUNTING_PERIODS,
        self::RUN_ACCOUNTING_BACKFILL,
        self::RUN_ACCOUNTING_RECONCILIATION,
        self::MANAGE_REVENUE_RECOGNITION,
        self::RUN_REVENUE_RECOGNITION,
        self::RESOLVE_REVENUE_RECOGNITION_REVIEWS,
        self::VIEW_FINANCIAL_STATEMENTS,
        self::EXPORT_FINANCIAL_REPORTS,
        self::MANAGE_SUBSCRIPTION_LIFECYCLE,
        self::RUN_SUBSCRIPTION_BILLING,
        self::RESOLVE_SUBSCRIPTION_BILLING_REVIEWS,
        self::VIEW_SAAS_METRICS,
        self::EXPORT_SAAS_METRICS,
        self::VIEW_EXECUTIVE_DASHBOARD,
        self::SUBMIT_PAYMENT_RECEIPT,
        self::APPROVE_PAYMENT_RECEIPTS,
        self::MANAGE_SYSTEM_ACCESS,
        self::START_PAID_SUBSCRIPTION,
        self::ISSUE_CONTRACTS,
        self::VIEW_COLLECTIONS_DUE,
        self::EDIT_REFERRAL_COMMISSION,
        self::MANAGE_CLIENT_CREDENTIALS,
        self::REVEAL_CLIENT_CREDENTIALS,
        self::VIEW_CUSTOM_PROJECTS,
        self::MANAGE_CUSTOM_PROJECTS,
        self::MANAGE_TEAM,
        self::MANAGE_REFERENCE_DATA,
        self::IMPORT_CLIENTS,
        self::MANAGE_COMPANY_SETTINGS,
    ];

    public static function allows(?User $user, string $permission): bool
    {
        if ($user === null || $user->is_active === false) {
            return false;
        }

        return in_array($user->role, self::ROLE_MATRIX[$permission] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        return array_keys(array_filter(
            self::ROLE_MATRIX,
            fn (array $roles) => in_array($role, $roles, true)
        ));
    }
}
