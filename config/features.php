<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UI Feature Flags
    |--------------------------------------------------------------------------
    |
    | React/Inertia routes are opt-in so Blade remains the default until
    | each module is proven equivalent and safe to switch.
    |
    */
    'react_sandbox' => (bool) env('FEATURE_REACT_SANDBOX', false),
    'admin_expenses_recurring_index' => (bool) env('FEATURE_ADMIN_EXPENSES_RECURRING_INDEX', false),
    'admin_expenses_recurring_show' => (bool) env('FEATURE_ADMIN_EXPENSES_RECURRING_SHOW', false),
    'admin_expenses_recurring_create' => (bool) env('FEATURE_ADMIN_EXPENSES_RECURRING_CREATE', false),
    'admin_expenses_recurring_edit' => (bool) env('FEATURE_ADMIN_EXPENSES_RECURRING_EDIT', false),
    'admin_expenses_categories_index' => (bool) env('FEATURE_ADMIN_EXPENSES_CATEGORIES_INDEX', false),
    'admin_expenses_index' => (bool) env('FEATURE_ADMIN_EXPENSES_INDEX', false),
    'admin_income_categories_index' => (bool) env('FEATURE_ADMIN_INCOME_CATEGORIES_INDEX', false),
    'admin_income_index' => (bool) env('FEATURE_ADMIN_INCOME_INDEX', false),
    'admin_automation_status_index' => (bool) env('FEATURE_ADMIN_AUTOMATION_STATUS_INDEX', false),
    'admin_users_activity_summary_index' => (bool) env('FEATURE_ADMIN_USERS_ACTIVITY_SUMMARY_INDEX', false),
    'admin_logs_index' => (bool) env('FEATURE_ADMIN_LOGS_INDEX', false),
    'admin_chats_index' => (bool) env('FEATURE_ADMIN_CHATS_INDEX', false),
    'admin_payment_gateways_index' => (bool) env('FEATURE_ADMIN_PAYMENT_GATEWAYS_INDEX', false),
    'admin_commission_payouts_index' => (bool) env('FEATURE_ADMIN_COMMISSION_PAYOUTS_INDEX', false),
    'admin_accounting_index' => (bool) env('FEATURE_ADMIN_ACCOUNTING_INDEX', false),
    'admin_finance_reports_index' => (bool) env('FEATURE_ADMIN_FINANCE_REPORTS_INDEX', false),
    'admin_support_tickets_index' => (bool) env('FEATURE_ADMIN_SUPPORT_TICKETS_INDEX', false),
    'admin_finance_payment_methods_index' => (bool) env('FEATURE_ADMIN_FINANCE_PAYMENT_METHODS_INDEX', false),
    'admin_finance_tax_index' => (bool) env('FEATURE_ADMIN_FINANCE_TAX_INDEX', false),
    'admin_orders_index' => (bool) env('FEATURE_ADMIN_ORDERS_INDEX', false),
    'admin_apptimatic_email_inbox' => (bool) env('FEATURE_ADMIN_APPTIMATIC_EMAIL_INBOX', false),
    'admin_apptimatic_email_show' => (bool) env('FEATURE_ADMIN_APPTIMATIC_EMAIL_SHOW', false),
    'admin_products_index' => (bool) env('FEATURE_ADMIN_PRODUCTS_INDEX', false),
    'admin_plans_index' => (bool) env('FEATURE_ADMIN_PLANS_INDEX', false),
    'admin_subscriptions_index' => (bool) env('FEATURE_ADMIN_SUBSCRIPTIONS_INDEX', false),
    'admin_licenses_index' => (bool) env('FEATURE_ADMIN_LICENSES_INDEX', false),
    'admin_payment_proofs_index' => (bool) env('FEATURE_ADMIN_PAYMENT_PROOFS_INDEX', false),
];
