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
    public const MANAGE_INVESTMENTS = 'manage_investments';
    public const MANAGE_CAPITAL_EXPENSES = 'manage_capital_expenses';
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

    public const ALL = [
        self::RECORD_PAYMENT,
        self::MANAGE_SUBSCRIPTION_BILLING,
        self::MANAGE_EXPENSES,
        self::MANAGE_EXPENSE_CATEGORIES,
        self::MANAGE_VENDORS,
        self::MANAGE_RECURRING_EXPENSES,
        self::VIEW_EXPENSE_MANAGEMENT,
        self::MANAGE_INVESTMENTS,
        self::MANAGE_CAPITAL_EXPENSES,
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
    ];

    public static function allows(?User $user, string $permission): bool
    {
        return $user !== null
            && in_array($permission, self::ALL, true)
            && $user->isAdmin();
    }
}
