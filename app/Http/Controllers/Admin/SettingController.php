<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\Plan;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use DateTimeZone;

class SettingController extends Controller
{
    public function edit(Request $request)
    {
        $tabs = ['general', 'invoices', 'automation', 'billing', 'email-templates'];
        $activeTab = $request->query('tab', 'general');
        if (! in_array($activeTab, $tabs, true)) {
            $activeTab = 'general';
        }

        $logoPath = Setting::getValue('company_logo_path');
        $faviconPath = Setting::getValue('company_favicon_path');
        $cronToken = (string) Setting::getValue('cron_token');

        if ($cronToken === '') {
            $cronToken = Str::random(40);
            Setting::setValue('cron_token', $cronToken);
        }

        $baseUrl = UrlResolver::portalUrl();
        $cronUrl = $cronToken !== '' ? "{$baseUrl}/cron/billing?token={$cronToken}" : null;

        $emailTemplates = Schema::hasTable('email_templates')
            ? EmailTemplate::query()->orderBy('category')->orderBy('name')->get()
            : collect();

        $countries = config('countries', []);
        $dateFormats = [
            'd-m-Y' => 'DD-MM-YYYY (31-12-2025)',
            'm-d-Y' => 'MM-DD-YYYY (12-31-2025)',
            'Y-m-d' => 'YYYY-MM-DD (2025-12-31)',
            'd/m/Y' => 'DD/MM/YYYY (31/12/2025)',
        ];
        $timeZones = DateTimeZone::listIdentifiers();

        return view('admin.settings.edit', [
            'settings' => [
                'company_name' => Setting::getValue('company_name'),
                'company_email' => Setting::getValue('company_email'),
                'pay_to_text' => Setting::getValue('pay_to_text'),
                'company_country' => Setting::getValue('company_country'),
                'app_url' => Setting::getValue('app_url', config('app.url')),
                'company_logo_path' => $logoPath,
                'company_logo_url' => Branding::url($logoPath),
                'company_favicon_path' => $faviconPath,
                'company_favicon_url' => Branding::url($faviconPath),
                'cron_token' => $cronToken,
                'cron_url' => $cronUrl,
                'billing_last_run_at' => Setting::getValue('billing_last_run_at'),
                'billing_last_started_at' => Setting::getValue('billing_last_started_at'),
                'billing_last_status' => Setting::getValue('billing_last_status'),
                'billing_last_error' => Setting::getValue('billing_last_error'),
                'currency' => Setting::getValue('currency'),
                'invoice_generation_days' => (int) Setting::getValue('invoice_generation_days'),
                'invoice_due_days' => (int) Setting::getValue('invoice_due_days'),
                'grace_period_days' => (int) Setting::getValue('grace_period_days'),
                'late_fee_days' => (int) Setting::getValue('late_fee_days'),
                'late_fee_type' => Setting::getValue('late_fee_type'),
                'late_fee_amount' => Setting::getValue('late_fee_amount'),
                'payment_reminder_emails' => (int) Setting::getValue('payment_reminder_emails'),
                'invoice_unpaid_reminder_days' => (int) Setting::getValue('invoice_unpaid_reminder_days'),
                'first_overdue_reminder_days' => (int) Setting::getValue('first_overdue_reminder_days'),
                'second_overdue_reminder_days' => (int) Setting::getValue('second_overdue_reminder_days'),
                'third_overdue_reminder_days' => (int) Setting::getValue('third_overdue_reminder_days'),
                'enable_suspension' => (int) Setting::getValue('enable_suspension'),
                'suspend_days' => (int) Setting::getValue('suspend_days'),
                'send_suspension_email' => (int) Setting::getValue('send_suspension_email'),
                'enable_unsuspension' => (int) Setting::getValue('enable_unsuspension'),
                'send_unsuspension_email' => (int) Setting::getValue('send_unsuspension_email'),
                'enable_termination' => (int) Setting::getValue('enable_termination'),
                'termination_days' => (int) Setting::getValue('termination_days'),
                'overage_billing_mode' => Setting::getValue('overage_billing_mode'),
                'change_invoice_status_on_reversal' => (int) Setting::getValue('change_invoice_status_on_reversal'),
                'change_due_dates_on_reversal' => (int) Setting::getValue('change_due_dates_on_reversal'),
                'enable_auto_cancellation' => (int) Setting::getValue('enable_auto_cancellation'),
                'auto_cancellation_days' => (int) Setting::getValue('auto_cancellation_days'),
                'auto_bind_domains' => (int) Setting::getValue('auto_bind_domains'),
                'payment_instructions' => Setting::getValue('payment_instructions'),
                'ticket_auto_close_days' => (int) Setting::getValue('ticket_auto_close_days'),
                'ticket_admin_reminder_days' => (int) Setting::getValue('ticket_admin_reminder_days'),
                'ticket_feedback_days' => (int) Setting::getValue('ticket_feedback_days'),
                'ticket_cleanup_days' => (int) Setting::getValue('ticket_cleanup_days'),
                'license_expiry_first_notice_days' => (int) Setting::getValue('license_expiry_first_notice_days'),
                'license_expiry_second_notice_days' => (int) Setting::getValue('license_expiry_second_notice_days'),
                'recaptcha_enabled' => (bool) Setting::getValue('recaptcha_enabled', config('recaptcha.enabled')),
                'recaptcha_site_key' => Setting::getValue('recaptcha_site_key', config('recaptcha.site_key')),
                'recaptcha_secret_key' => Setting::getValue('recaptcha_secret_key', config('recaptcha.secret_key')),
                'recaptcha_project_id' => Setting::getValue('recaptcha_project_id', config('recaptcha.project_id')),
                'recaptcha_api_key' => Setting::getValue('recaptcha_api_key', config('recaptcha.api_key')),
                'recaptcha_score_threshold' => Setting::getValue('recaptcha_score_threshold', config('recaptcha.score_threshold')),
                'date_format' => Setting::getValue('date_format'),
                'time_zone' => Setting::getValue('time_zone', config('app.timezone')),
            ],
            'emailTemplates' => $emailTemplates,
            'activeTab' => $activeTab,
            'countries' => $countries,
            'dateFormats' => $dateFormats,
            'timeZones' => $timeZones,
        ]);
    }

    public function update(Request $request)
    {
        $countries = config('countries', []);
        $countryOptions = array_merge([''], $countries);
        $dateFormatKeys = ['d-m-Y', 'm-d-Y', 'Y-m-d', 'd/m/Y'];
        $timeZones = DateTimeZone::listIdentifiers();

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'pay_to_text' => ['nullable', 'string', 'max:255'],
            'company_country' => ['nullable', 'string', 'max:255', Rule::in($countryOptions)],
            'app_url' => ['nullable', 'url', 'max:255'],
            'company_logo' => ['nullable', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
            'company_favicon' => ['nullable', 'mimes:jpg,jpeg,png,svg,ico', 'max:1024'],
            'currency' => ['required', Rule::in(['BDT', 'USD'])],
            'invoice_generation_days' => ['required', 'integer', 'min:0', 'max:365'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'late_fee_days' => ['required', 'integer', 'min:0', 'max:365'],
            'late_fee_type' => ['required', Rule::in(['fixed', 'percent'])],
            'late_fee_amount' => ['required', 'numeric', 'min:0'],
            'payment_reminder_emails' => ['nullable', 'boolean'],
            'invoice_unpaid_reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'first_overdue_reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'second_overdue_reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'third_overdue_reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'enable_suspension' => ['nullable', 'boolean'],
            'suspend_days' => ['required', 'integer', 'min:0', 'max:365'],
            'send_suspension_email' => ['nullable', 'boolean'],
            'enable_unsuspension' => ['nullable', 'boolean'],
            'send_unsuspension_email' => ['nullable', 'boolean'],
            'enable_termination' => ['nullable', 'boolean'],
            'termination_days' => ['required', 'integer', 'min:0', 'max:365'],
            'overage_billing_mode' => ['required', Rule::in(['last_day_separate', 'last_day_next_invoice'])],
            'change_invoice_status_on_reversal' => ['nullable', 'boolean'],
            'change_due_dates_on_reversal' => ['nullable', 'boolean'],
            'enable_auto_cancellation' => ['nullable', 'boolean'],
            'auto_cancellation_days' => ['required', 'integer', 'min:0', 'max:365'],
            'auto_bind_domains' => ['nullable', 'boolean'],
            'payment_instructions' => ['nullable', 'string'],
            'ticket_auto_close_days' => ['required', 'integer', 'min:0', 'max:365'],
            'ticket_admin_reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'ticket_feedback_days' => ['required', 'integer', 'min:0', 'max:365'],
            'ticket_cleanup_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'license_expiry_first_notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'license_expiry_second_notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'enable_recaptcha' => ['nullable', 'boolean'],
            'recaptcha_site_key' => ['nullable', 'string', 'max:255', 'required_if:enable_recaptcha,1'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:255', 'required_if:enable_recaptcha,1'],
            'recaptcha_project_id' => ['nullable', 'string', 'max:255'],
            'recaptcha_api_key' => ['nullable', 'string', 'max:255'],
            'recaptcha_score_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'date_format' => ['required', Rule::in($dateFormatKeys)],
            'time_zone' => ['required', Rule::in($timeZones)],
            'templates' => ['nullable', 'array'],
            'templates.*.from_email' => ['nullable', 'email', 'max:255'],
            'templates.*.subject' => ['nullable', 'string', 'max:255'],
            'templates.*.body' => ['nullable', 'string'],
        ]);

        Setting::setValue('company_name', $data['company_name']);
        Setting::setValue('company_email', $data['company_email'] ?? '');
        Setting::setValue('pay_to_text', $data['pay_to_text'] ?? '');
        Setting::setValue('company_country', $data['company_country'] ?? '');
        $appUrl = $data['app_url'] ?? '';
        $appUrl = is_string($appUrl) ? rtrim($appUrl, '/') : '';
        Setting::setValue('app_url', $appUrl);

        if ($request->hasFile('company_logo')) {
            $logoPath = $request->file('company_logo')->store('branding', 'public');
            Setting::setValue('company_logo_path', $logoPath);
        }

        if ($request->hasFile('company_favicon')) {
            $faviconPath = $request->file('company_favicon')->store('branding', 'public');
            Setting::setValue('company_favicon_path', $faviconPath);
        }

        Setting::setValue('currency', strtoupper($data['currency']));
        Plan::query()->update(['currency' => strtoupper($data['currency'])]);
        Setting::setValue('invoice_generation_days', (int) $data['invoice_generation_days']);
        Setting::setValue('invoice_due_days', (int) $data['invoice_due_days']);
        Setting::setValue('grace_period_days', (int) $data['grace_period_days']);
        Setting::setValue('late_fee_days', (int) $data['late_fee_days']);
        Setting::setValue('late_fee_type', $data['late_fee_type']);
        Setting::setValue('late_fee_amount', number_format((float) $data['late_fee_amount'], 2, '.', ''));
        Setting::setValue('payment_reminder_emails', $request->boolean('payment_reminder_emails') ? '1' : '0');
        Setting::setValue('invoice_unpaid_reminder_days', (int) $data['invoice_unpaid_reminder_days']);
        Setting::setValue('first_overdue_reminder_days', (int) $data['first_overdue_reminder_days']);
        Setting::setValue('second_overdue_reminder_days', (int) $data['second_overdue_reminder_days']);
        Setting::setValue('third_overdue_reminder_days', (int) $data['third_overdue_reminder_days']);
        Setting::setValue('enable_suspension', $request->boolean('enable_suspension') ? '1' : '0');
        Setting::setValue('suspend_days', (int) $data['suspend_days']);
        Setting::setValue('send_suspension_email', $request->boolean('send_suspension_email') ? '1' : '0');
        Setting::setValue('enable_unsuspension', $request->boolean('enable_unsuspension') ? '1' : '0');
        Setting::setValue('send_unsuspension_email', $request->boolean('send_unsuspension_email') ? '1' : '0');
        Setting::setValue('enable_termination', $request->boolean('enable_termination') ? '1' : '0');
        Setting::setValue('termination_days', (int) $data['termination_days']);
        Setting::setValue('overage_billing_mode', $data['overage_billing_mode']);
        Setting::setValue('change_invoice_status_on_reversal', $request->boolean('change_invoice_status_on_reversal') ? '1' : '0');
        Setting::setValue('change_due_dates_on_reversal', $request->boolean('change_due_dates_on_reversal') ? '1' : '0');
        Setting::setValue('enable_auto_cancellation', $request->boolean('enable_auto_cancellation') ? '1' : '0');
        Setting::setValue('auto_cancellation_days', (int) $data['auto_cancellation_days']);
        Setting::setValue('auto_bind_domains', $request->boolean('auto_bind_domains') ? '1' : '0');
        Setting::setValue('payment_instructions', $data['payment_instructions'] ?? '');
        Setting::setValue('ticket_auto_close_days', (int) $data['ticket_auto_close_days']);
        Setting::setValue('ticket_admin_reminder_days', (int) $data['ticket_admin_reminder_days']);
        Setting::setValue('ticket_feedback_days', (int) $data['ticket_feedback_days']);
        Setting::setValue('ticket_cleanup_days', (int) $data['ticket_cleanup_days']);
        Setting::setValue('license_expiry_first_notice_days', (int) $data['license_expiry_first_notice_days']);
        Setting::setValue('license_expiry_second_notice_days', (int) $data['license_expiry_second_notice_days']);
        Setting::setValue('recaptcha_enabled', $request->boolean('enable_recaptcha') ? '1' : '0');
        Setting::setValue('recaptcha_site_key', $data['recaptcha_site_key'] ?? '');
        Setting::setValue('recaptcha_secret_key', $data['recaptcha_secret_key'] ?? '');
        Setting::setValue('recaptcha_project_id', $data['recaptcha_project_id'] ?? '');
        Setting::setValue('recaptcha_api_key', $data['recaptcha_api_key'] ?? '');
        Setting::setValue('recaptcha_score_threshold', $data['recaptcha_score_threshold'] ?? '');
        Setting::setValue('date_format', $data['date_format']);
        Setting::setValue('time_zone', $data['time_zone']);

        if (Schema::hasTable('email_templates') && ! empty($data['templates']) && is_array($data['templates'])) {
            $templateUpdates = $data['templates'];
            $templates = EmailTemplate::query()
                ->whereIn('id', array_keys($templateUpdates))
                ->get()
                ->keyBy('id');

            foreach ($templateUpdates as $templateId => $payload) {
                $template = $templates->get((int) $templateId);

                if (! $template) {
                    continue;
                }

                if (array_key_exists('subject', $payload)) {
                    $template->subject = $payload['subject'] ?? '';
                }

                if (array_key_exists('from_email', $payload)) {
                    $template->from_email = $payload['from_email'] ?? '';
                }

                if (array_key_exists('body', $payload)) {
                    $template->body = $payload['body'] ?? '';
                }

                $template->save();
            }
        }

        $tabs = ['general', 'invoices', 'automation', 'billing', 'email-templates'];
        $activeTab = $request->input('active_tab', 'general');
        if (! in_array($activeTab, $tabs, true)) {
            $activeTab = 'general';
        }

        return redirect()->route('admin.settings.edit', ['tab' => $activeTab])
            ->with('status', 'Settings updated.');
    }
}
