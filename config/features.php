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
    'admin_automation_status_index' => (bool) env('FEATURE_ADMIN_AUTOMATION_STATUS_INDEX', false),
    'admin_users_activity_summary_index' => (bool) env('FEATURE_ADMIN_USERS_ACTIVITY_SUMMARY_INDEX', false),
    'admin_logs_index' => (bool) env('FEATURE_ADMIN_LOGS_INDEX', false),
    'admin_chats_index' => (bool) env('FEATURE_ADMIN_CHATS_INDEX', false),
    'admin_payment_gateways_index' => (bool) env('FEATURE_ADMIN_PAYMENT_GATEWAYS_INDEX', false),
    'admin_commission_payouts_index' => (bool) env('FEATURE_ADMIN_COMMISSION_PAYOUTS_INDEX', false),
    'admin_accounting_index' => (bool) env('FEATURE_ADMIN_ACCOUNTING_INDEX', false),
];
