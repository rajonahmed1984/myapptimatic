@extends('layouts.admin')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="section-label">WHMCS style settings</div>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Portal configuration</h1>
                <p class="mt-2 text-sm text-slate-600">Control billing automation, reminders, and branding.</p>
            </div>
        </div>

        <div class="mt-8">
            <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 text-sm" role="tablist">
                <button type="button" data-tab-target="general" class="rounded-full border border-slate-200 px-4 py-2 text-slate-600">General</button>
                <button type="button" data-tab-target="invoices" class="rounded-full border border-slate-200 px-4 py-2 text-slate-600">Invoices</button>
                <button type="button" data-tab-target="automation" class="rounded-full border border-slate-200 px-4 py-2 text-slate-600">Automatic module functions</button>
                <button type="button" data-tab-target="billing" class="rounded-full border border-slate-200 px-4 py-2 text-slate-600">Billing settings</button>
            </div>

            <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-8 space-y-8" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <section data-tab-panel="general" class="space-y-6">
                    <div class="section-label">General</div>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-sm text-slate-600">Company name</label>
                            <input name="company_name" value="{{ old('company_name', $settings['company_name']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Pay to text</label>
                            <input name="pay_to_text" value="{{ old('pay_to_text', $settings['pay_to_text']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Email address (default sender)</label>
                            <input name="company_email" type="email" value="{{ old('company_email', $settings['company_email']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Company logo</label>
                            <input name="company_logo" type="file" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            @if(!empty($settings['company_logo_url']))
                                <img src="{{ $settings['company_logo_url'] }}" alt="Company logo" class="mt-3 h-12 rounded-xl border border-slate-200 bg-white p-1">
                            @endif
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Favicon</label>
                            <input name="company_favicon" type="file" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            @if(!empty($settings['company_favicon_url']))
                                <img src="{{ $settings['company_favicon_url'] }}" alt="Favicon" class="mt-3 h-10 w-10 rounded-xl border border-slate-200 bg-white p-1">
                            @endif
                        </div>
                    </div>
                </section>

                <section data-tab-panel="invoices" class="space-y-6">
                    <div class="section-label">Invoices</div>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-sm text-slate-600">Currency</label>
                            <select name="currency" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                <option value="BDT" @selected($settings['currency'] === 'BDT')>BDT</option>
                                <option value="USD" @selected($settings['currency'] === 'USD')>USD</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Invoice due days</label>
                            <input name="invoice_due_days" type="number" value="{{ old('invoice_due_days', $settings['invoice_due_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            <p class="mt-2 text-xs text-slate-500">Days after invoice issue date to set the due date.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm text-slate-600">Payment instructions</label>
                            <textarea name="payment_instructions" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('payment_instructions', $settings['payment_instructions']) }}</textarea>
                        </div>
                    </div>
                </section>

                <section data-tab-panel="automation" class="space-y-6">
                    <div class="section-label">Automatic module functions</div>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="enable_suspension" value="0" />
                            <input type="checkbox" name="enable_suspension" value="1" @checked($settings['enable_suspension']) class="rounded border-slate-300 text-teal-500" />
                            Enable suspension
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Suspend days</label>
                            <input name="suspend_days" type="number" value="{{ old('suspend_days', $settings['suspend_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="send_suspension_email" value="0" />
                            <input type="checkbox" name="send_suspension_email" value="1" @checked($settings['send_suspension_email']) class="rounded border-slate-300 text-teal-500" />
                            Send suspension email
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="enable_unsuspension" value="0" />
                            <input type="checkbox" name="enable_unsuspension" value="1" @checked($settings['enable_unsuspension']) class="rounded border-slate-300 text-teal-500" />
                            Enable unsuspension
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="send_unsuspension_email" value="0" />
                            <input type="checkbox" name="send_unsuspension_email" value="1" @checked($settings['send_unsuspension_email']) class="rounded border-slate-300 text-teal-500" />
                            Send unsuspension email
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="enable_termination" value="0" />
                            <input type="checkbox" name="enable_termination" value="1" @checked($settings['enable_termination']) class="rounded border-slate-300 text-teal-500" />
                            Enable termination
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Termination days</label>
                            <input name="termination_days" type="number" value="{{ old('termination_days', $settings['termination_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                    </div>
                </section>

                <section data-tab-panel="billing" class="space-y-6">
                    <div class="section-label">Billing settings</div>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-sm text-slate-600">Invoice generation days</label>
                            <input name="invoice_generation_days" type="number" value="{{ old('invoice_generation_days', $settings['invoice_generation_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            <p class="mt-2 text-xs text-slate-500">Days before the due date to generate invoices.</p>
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Grace period days</label>
                            <input name="grace_period_days" type="number" value="{{ old('grace_period_days', $settings['grace_period_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="payment_reminder_emails" value="0" />
                            <input type="checkbox" name="payment_reminder_emails" value="1" @checked($settings['payment_reminder_emails']) class="rounded border-slate-300 text-teal-500" />
                            Payment reminder emails
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Invoice unpaid reminder days</label>
                            <input name="invoice_unpaid_reminder_days" type="number" value="{{ old('invoice_unpaid_reminder_days', $settings['invoice_unpaid_reminder_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">First overdue reminder days</label>
                            <input name="first_overdue_reminder_days" type="number" value="{{ old('first_overdue_reminder_days', $settings['first_overdue_reminder_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Second overdue reminder days</label>
                            <input name="second_overdue_reminder_days" type="number" value="{{ old('second_overdue_reminder_days', $settings['second_overdue_reminder_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Third overdue reminder days</label>
                            <input name="third_overdue_reminder_days" type="number" value="{{ old('third_overdue_reminder_days', $settings['third_overdue_reminder_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Add late fee days</label>
                            <input name="late_fee_days" type="number" value="{{ old('late_fee_days', $settings['late_fee_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Late fee type</label>
                            <select name="late_fee_type" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                <option value="fixed" @selected($settings['late_fee_type'] === 'fixed')>Fixed</option>
                                <option value="percent" @selected($settings['late_fee_type'] === 'percent')>Percent</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Late fee amount</label>
                            <input name="late_fee_amount" type="number" step="0.01" value="{{ old('late_fee_amount', $settings['late_fee_amount']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Overage billing charges</label>
                            <select name="overage_billing_mode" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                <option value="last_day_separate" @selected($settings['overage_billing_mode'] === 'last_day_separate')>Calculate and invoice on the last day of the month</option>
                                <option value="last_day_next_invoice" @selected($settings['overage_billing_mode'] === 'last_day_next_invoice')>Calculate on last day, include on next invoice</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="change_invoice_status_on_reversal" value="0" />
                            <input type="checkbox" name="change_invoice_status_on_reversal" value="1" @checked($settings['change_invoice_status_on_reversal']) class="rounded border-slate-300 text-teal-500" />
                            Change invoice status on payment reversal
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="change_due_dates_on_reversal" value="0" />
                            <input type="checkbox" name="change_due_dates_on_reversal" value="1" @checked($settings['change_due_dates_on_reversal']) class="rounded border-slate-300 text-teal-500" />
                            Change due dates on payment reversal
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="enable_auto_cancellation" value="0" />
                            <input type="checkbox" name="enable_auto_cancellation" value="1" @checked($settings['enable_auto_cancellation']) class="rounded border-slate-300 text-teal-500" />
                            Enable auto cancellation
                        </div>
                        <div>
                            <label class="text-sm text-slate-600">Days overdue</label>
                            <input name="auto_cancellation_days" type="number" value="{{ old('auto_cancellation_days', $settings['auto_cancellation_days']) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="auto_bind_domains" value="0" />
                            <input type="checkbox" name="auto_bind_domains" value="1" @checked($settings['auto_bind_domains']) class="rounded border-slate-300 text-teal-500" />
                            Auto bind domains on first check
                        </div>
                    </div>
                </section>

                <div class="flex justify-end pt-6">
                    <button type="submit" class="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">Save settings</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const tabs = Array.from(document.querySelectorAll('[data-tab-target]'));
            const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));

            function setActive(target) {
                tabs.forEach((tab) => {
                    const isActive = tab.dataset.tabTarget === target;
                    tab.classList.toggle('bg-slate-900', isActive);
                    tab.classList.toggle('text-white', isActive);
                    tab.classList.toggle('border-slate-900', isActive);
                    tab.classList.toggle('text-slate-600', !isActive);
                });

                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.tabPanel !== target);
                });
            }

            const hash = window.location.hash.replace('#', '');
            const defaultTab = tabs.some((tab) => tab.dataset.tabTarget === hash) ? hash : tabs[0]?.dataset.tabTarget;

            if (defaultTab) {
                setActive(defaultTab);
            }

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.tabTarget;
                    setActive(target);
                    window.history.replaceState(null, '', `#${target}`);
                });
            });
        })();
    </script>
@endsection
