<?php

namespace App\Support;

use App\Models\User;

class FinancialPermissions
{
    public const RECORD_PAYMENT = 'record_payment';
    public const MANAGE_SUBSCRIPTION_BILLING = 'manage_subscription_billing';
    public const MANAGE_EXPENSES = 'manage_expenses';
    public const MANAGE_EXPENSE_CATEGORIES = 'manage_expense_categories';
    public const MANAGE_VENDORS = 'manage_vendors';
    public const MANAGE_RECURRING_EXPENSES = 'manage_recurring_expenses';
    public const VIEW_EXPENSE_MANAGEMENT = 'view_expense_management';
    public const MANAGE_FINANCIAL_SETTINGS = 'manage_financial_settings';
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

    public const ALL = [
        self::RECORD_PAYMENT,
        self::MANAGE_SUBSCRIPTION_BILLING,
        self::MANAGE_EXPENSES,
        self::MANAGE_EXPENSE_CATEGORIES,
        self::MANAGE_VENDORS,
        self::MANAGE_RECURRING_EXPENSES,
        self::VIEW_EXPENSE_MANAGEMENT,
        self::MANAGE_FINANCIAL_SETTINGS,
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
    ];

    public static function allows(?User $user, string $permission): bool
    {
        return $user !== null
            && in_array($permission, self::ALL, true)
            && $user->isAdmin();
    }
}
