<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function edit()
    {
        $logoPath = Setting::getValue('company_logo_path');
        $faviconPath = Setting::getValue('company_favicon_path');

        return view('admin.settings.edit', [
            'settings' => [
                'company_name' => Setting::getValue('company_name'),
                'company_email' => Setting::getValue('company_email'),
                'pay_to_text' => Setting::getValue('pay_to_text'),
                'company_logo_path' => $logoPath,
                'company_logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
                'company_favicon_path' => $faviconPath,
                'company_favicon_url' => $faviconPath ? Storage::disk('public')->url($faviconPath) : null,
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
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'pay_to_text' => ['nullable', 'string', 'max:255'],
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
        ]);

        Setting::setValue('company_name', $data['company_name']);
        Setting::setValue('company_email', $data['company_email'] ?? '');
        Setting::setValue('pay_to_text', $data['pay_to_text'] ?? '');

        if ($request->hasFile('company_logo')) {
            $logoPath = $request->file('company_logo')->store('branding', 'public');
            Setting::setValue('company_logo_path', $logoPath);
        }

        if ($request->hasFile('company_favicon')) {
            $faviconPath = $request->file('company_favicon')->store('branding', 'public');
            Setting::setValue('company_favicon_path', $faviconPath);
        }

        Setting::setValue('currency', strtoupper($data['currency']));
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

        return redirect()->route('admin.settings.edit')
            ->with('status', 'Settings updated.');
    }
}
