<?php

namespace App\Support;

class UiFeature
{
    public const REACT_SANDBOX = 'react_sandbox';

    public const ADMIN_EXPENSES_RECURRING_INDEX = 'admin_expenses_recurring_index';

    public const ADMIN_EXPENSES_RECURRING_SHOW = 'admin_expenses_recurring_show';

    public const ADMIN_EXPENSES_RECURRING_CREATE = 'admin_expenses_recurring_create';

    public const ADMIN_EXPENSES_RECURRING_EDIT = 'admin_expenses_recurring_edit';

    public const ADMIN_AUTOMATION_STATUS_INDEX = 'admin_automation_status_index';

    public const ADMIN_USERS_ACTIVITY_SUMMARY_INDEX = 'admin_users_activity_summary_index';

    public const ADMIN_LOGS_INDEX = 'admin_logs_index';

    public const ADMIN_CHATS_INDEX = 'admin_chats_index';

    public const ADMIN_PAYMENT_GATEWAYS_INDEX = 'admin_payment_gateways_index';

    public const ADMIN_COMMISSION_PAYOUTS_INDEX = 'admin_commission_payouts_index';

    public const ADMIN_ACCOUNTING_INDEX = 'admin_accounting_index';

    public const ADMIN_FINANCE_REPORTS_INDEX = 'admin_finance_reports_index';

    public const ADMIN_SUPPORT_TICKETS_INDEX = 'admin_support_tickets_index';

    public const ADMIN_FINANCE_PAYMENT_METHODS_INDEX = 'admin_finance_payment_methods_index';

    public const ADMIN_ORDERS_INDEX = 'admin_orders_index';

    public const ADMIN_APPTIMATIC_EMAIL_INBOX = 'admin_apptimatic_email_inbox';

    public const ADMIN_APPTIMATIC_EMAIL_SHOW = 'admin_apptimatic_email_show';

    public static function enabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", false);
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return [
            self::REACT_SANDBOX => self::enabled(self::REACT_SANDBOX),
            self::ADMIN_EXPENSES_RECURRING_INDEX => self::enabled(self::ADMIN_EXPENSES_RECURRING_INDEX),
            self::ADMIN_EXPENSES_RECURRING_SHOW => self::enabled(self::ADMIN_EXPENSES_RECURRING_SHOW),
            self::ADMIN_EXPENSES_RECURRING_CREATE => self::enabled(self::ADMIN_EXPENSES_RECURRING_CREATE),
            self::ADMIN_EXPENSES_RECURRING_EDIT => self::enabled(self::ADMIN_EXPENSES_RECURRING_EDIT),
            self::ADMIN_AUTOMATION_STATUS_INDEX => self::enabled(self::ADMIN_AUTOMATION_STATUS_INDEX),
            self::ADMIN_USERS_ACTIVITY_SUMMARY_INDEX => self::enabled(self::ADMIN_USERS_ACTIVITY_SUMMARY_INDEX),
            self::ADMIN_LOGS_INDEX => self::enabled(self::ADMIN_LOGS_INDEX),
            self::ADMIN_CHATS_INDEX => self::enabled(self::ADMIN_CHATS_INDEX),
            self::ADMIN_PAYMENT_GATEWAYS_INDEX => self::enabled(self::ADMIN_PAYMENT_GATEWAYS_INDEX),
            self::ADMIN_COMMISSION_PAYOUTS_INDEX => self::enabled(self::ADMIN_COMMISSION_PAYOUTS_INDEX),
            self::ADMIN_ACCOUNTING_INDEX => self::enabled(self::ADMIN_ACCOUNTING_INDEX),
            self::ADMIN_FINANCE_REPORTS_INDEX => self::enabled(self::ADMIN_FINANCE_REPORTS_INDEX),
            self::ADMIN_SUPPORT_TICKETS_INDEX => self::enabled(self::ADMIN_SUPPORT_TICKETS_INDEX),
            self::ADMIN_FINANCE_PAYMENT_METHODS_INDEX => self::enabled(self::ADMIN_FINANCE_PAYMENT_METHODS_INDEX),
            self::ADMIN_ORDERS_INDEX => self::enabled(self::ADMIN_ORDERS_INDEX),
            self::ADMIN_APPTIMATIC_EMAIL_INBOX => self::enabled(self::ADMIN_APPTIMATIC_EMAIL_INBOX),
            self::ADMIN_APPTIMATIC_EMAIL_SHOW => self::enabled(self::ADMIN_APPTIMATIC_EMAIL_SHOW),
        ];
    }
}
