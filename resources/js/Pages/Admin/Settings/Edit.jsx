import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

const on = (v) => v === true || v === 1 || v === '1' || v === 'true';

const TAB_CONFIG = [
    { key: 'general', label: 'General', hint: 'Brand, locale and security configuration.' },
    { key: 'invoices', label: 'Invoices', hint: 'Invoice core values and payment instructions.' },
    { key: 'automation', label: 'Automation', hint: 'Cron actions and support automation.' },
    { key: 'billing', label: 'Billing', hint: 'Reminder, fees, cancellation and licensing rules.' },
    { key: 'tasks', label: 'Tasks', hint: 'Task workflow defaults and upload limits.' },
    { key: 'email-templates', label: 'Email Templates', hint: 'Outgoing email message templates.' },
];

const Num = ({ name, value, min = 0, max = 3650, step = 1, errors }) => (
    <div>
        <input
            name={name}
            type="number"
            min={min}
            max={max}
            step={step}
            defaultValue={value ?? 0}
            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
        />
        <InputError errors={errors} name={name} />
    </div>
);

const Check = ({ name, checked, label }) => (
    <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
        <input type="hidden" name={name} value="0" />
        <input type="checkbox" name={name} value="1" defaultChecked={on(checked)} className="rounded border-slate-300 text-teal-500" />
        <span>{label}</span>
    </label>
);

const InputError = ({ errors, name }) => (
    errors?.[name] ? <div className="mt-1 text-xs text-rose-600">{errors[name]}</div> : null
);

const Field = ({ label, name, errors, children }) => (
    <div>
        <label className="text-sm text-slate-600">{label}</label>
        {children}
        <InputError errors={errors} name={name} />
    </div>
);

export default function Edit({
    pageTitle = 'Settings',
    active_tab = 'general',
    settings = {},
    countries = [],
    date_formats = {},
    time_zones = [],
    task_type_labels = {},
    email_template_groups = [],
    routes = {},
}) {
    const { csrf_token: csrf = '', errors = {} } = usePage().props || {};
    const [tab, setTab] = useState(active_tab || 'general');

    const activeTabMeta = TAB_CONFIG.find((item) => item.key === tab) || TAB_CONFIG[0];

    const openTab = (next) => {
        setTab(next);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', next);
        window.history.replaceState(null, '', url.toString());
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="space-y-6">
                <div className="card p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="section-label">Platform Settings</div>
                            <div className="text-2xl font-semibold text-slate-900">Configuration Center</div>
                            <div className="mt-1 text-sm text-slate-500">{activeTabMeta?.hint}</div>
                        </div>
                        <button
                            form="settingsForm"
                            type="submit"
                            className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white hover:bg-teal-600"
                        >
                            Save settings
                        </button>
                    </div>
                </div>

                <div className="card p-4">
                    <div className="flex flex-wrap gap-2">
                        {TAB_CONFIG.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() => openTab(item.key)}
                                className={`rounded-full border px-4 py-2 text-sm font-semibold transition ${
                                    tab === item.key
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-teal-300 hover:text-teal-600'
                                }`}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                <form
                    id="settingsForm"
                    method="POST"
                    action={routes?.update}
                    encType="multipart/form-data"
                    data-native="true"
                    className="space-y-6"
                >
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="PUT" />
                    <input type="hidden" name="active_tab" value={tab} />

                    <section className={tab === 'general' ? 'space-y-6' : 'hidden'}>
                        <div className="grid gap-6 lg:grid-cols-2">
                            <div className="card p-6">
                                <div className="section-label">Brand & Contact</div>
                                <div className="mt-4 grid gap-4">
                                    <Field label="Company name" name="company_name" errors={errors}>
                                        <input name="company_name" defaultValue={settings.company_name || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                    </Field>
                                    <Field label="Pay to text" name="pay_to_text" errors={errors}>
                                        <input name="pay_to_text" defaultValue={settings.pay_to_text || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                    </Field>
                                    <Field label="Email" name="company_email" errors={errors}>
                                        <input name="company_email" type="email" defaultValue={settings.company_email || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                    </Field>
                                    <Field label="App URL" name="app_url" errors={errors}>
                                        <input name="app_url" type="url" defaultValue={settings.app_url || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                    </Field>
                                    <Field label="Country" name="company_country" errors={errors}>
                                        <select name="company_country" defaultValue={settings.company_country || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                            <option value="">Select country</option>
                                            {countries.map((country) => <option key={country} value={country}>{country}</option>)}
                                        </select>
                                    </Field>
                                </div>
                            </div>

                            <div className="card p-6">
                                <div className="section-label">Locale & Schedule</div>
                                <div className="mt-4 grid gap-4">
                                    <Field label="Date format" name="date_format" errors={errors}>
                                        <select name="date_format" defaultValue={settings.date_format || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                            {Object.entries(date_formats).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                    </Field>
                                    <Field label="Time zone" name="time_zone" errors={errors}>
                                        <select name="time_zone" defaultValue={settings.time_zone || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                            {time_zones.map((zone) => <option key={zone} value={zone}>{zone}</option>)}
                                        </select>
                                    </Field>
                                    <Field label="Automation time" name="automation_time_of_day" errors={errors}>
                                        <input name="automation_time_of_day" type="time" defaultValue={settings.automation_time_of_day || '00:00'} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                    </Field>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <div className="card p-6">
                                <div className="section-label">Brand Assets</div>
                                <div className="mt-4 grid gap-4">
                                    <Field label="Company logo" name="company_logo" errors={errors}>
                                        <input name="company_logo" type="file" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                    </Field>
                                    {settings.company_logo_url ? (
                                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Current Logo</div>
                                            <img src={settings.company_logo_url} alt="Company logo" className="mt-2 h-14 w-auto object-contain" />
                                        </div>
                                    ) : null}
                                    <Field label="Favicon" name="company_favicon" errors={errors}>
                                        <input name="company_favicon" type="file" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                    </Field>
                                    {settings.company_favicon_url ? (
                                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Current Favicon</div>
                                            <img src={settings.company_favicon_url} alt="Favicon" className="mt-2 h-10 w-10 rounded-lg object-contain" />
                                        </div>
                                    ) : null}
                                </div>
                            </div>

                            <div className="card p-6">
                                <div className="section-label">reCAPTCHA</div>
                                <div className="mt-4 space-y-4">
                                    <Check name="enable_recaptcha" checked={settings.recaptcha_enabled} label="Enable reCAPTCHA validation" />
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Field label="Site key" name="recaptcha_site_key" errors={errors}>
                                            <input name="recaptcha_site_key" defaultValue={settings.recaptcha_site_key || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                        </Field>
                                        <Field label="Secret key" name="recaptcha_secret_key" errors={errors}>
                                            <input name="recaptcha_secret_key" type="password" defaultValue={settings.recaptcha_secret_key || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                        </Field>
                                        <Field label="Project ID" name="recaptcha_project_id" errors={errors}>
                                            <input name="recaptcha_project_id" defaultValue={settings.recaptcha_project_id || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                        </Field>
                                        <Field label="API key" name="recaptcha_api_key" errors={errors}>
                                            <input name="recaptcha_api_key" type="password" defaultValue={settings.recaptcha_api_key || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                        </Field>
                                        <Field label="Score threshold" name="recaptcha_score_threshold" errors={errors}>
                                            <input name="recaptcha_score_threshold" type="number" step="0.01" min="0" max="1" defaultValue={settings.recaptcha_score_threshold || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                        </Field>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className={tab === 'invoices' ? 'space-y-6' : 'hidden'}>
                        <div className="card p-6">
                            <div className="section-label">Invoice Settings</div>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <Field label="Currency" name="currency" errors={errors}>
                                    <select name="currency" defaultValue={settings.currency || 'USD'} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                        <option value="BDT">BDT</option>
                                        <option value="USD">USD</option>
                                    </select>
                                </Field>
                                <div>
                                    <label className="text-sm text-slate-600">Invoice due days</label>
                                    <Num name="invoice_due_days" value={settings.invoice_due_days} errors={errors} />
                                </div>
                                <div className="md:col-span-2">
                                    <label className="text-sm text-slate-600">Payment instructions</label>
                                    <textarea name="payment_instructions" rows={4} defaultValue={settings.payment_instructions || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                    <InputError errors={errors} name="payment_instructions" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className={tab === 'automation' ? 'space-y-6' : 'hidden'}>
                        <div className="card p-6">
                            <div className="section-label">Lifecycle Automation</div>
                            <div className="mt-4 grid gap-3 md:grid-cols-2">
                                <Check name="enable_suspension" checked={settings.enable_suspension} label="Enable suspension" />
                                <div><label className="text-sm text-slate-600">Suspend days</label><Num name="suspend_days" value={settings.suspend_days} errors={errors} /></div>
                                <Check name="send_suspension_email" checked={settings.send_suspension_email} label="Send suspension email" />
                                <Check name="enable_unsuspension" checked={settings.enable_unsuspension} label="Enable unsuspension" />
                                <Check name="send_unsuspension_email" checked={settings.send_unsuspension_email} label="Send unsuspension email" />
                                <Check name="enable_termination" checked={settings.enable_termination} label="Enable termination" />
                                <div><label className="text-sm text-slate-600">Termination days</label><Num name="termination_days" value={settings.termination_days} errors={errors} /></div>
                            </div>
                        </div>

                        <div className="card p-6">
                            <div className="section-label">Support Ticket Automation</div>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <div><label className="text-sm text-slate-600">Auto-close days</label><Num name="ticket_auto_close_days" value={settings.ticket_auto_close_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Admin reminder days</label><Num name="ticket_admin_reminder_days" value={settings.ticket_admin_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Feedback days</label><Num name="ticket_feedback_days" value={settings.ticket_feedback_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Cleanup days</label><Num name="ticket_cleanup_days" value={settings.ticket_cleanup_days} errors={errors} max={3650} /></div>
                            </div>
                        </div>
                    </section>

                    <section className={tab === 'billing' ? 'space-y-6' : 'hidden'}>
                        <div className="card p-6">
                            <div className="section-label">Billing Rules</div>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <div><label className="text-sm text-slate-600">Invoice generation days</label><Num name="invoice_generation_days" value={settings.invoice_generation_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Grace period days</label><Num name="grace_period_days" value={settings.grace_period_days} errors={errors} /></div>
                                <Check name="payment_reminder_emails" checked={settings.payment_reminder_emails} label="Payment reminder emails" />
                                <div><label className="text-sm text-slate-600">Unpaid reminder days</label><Num name="invoice_unpaid_reminder_days" value={settings.invoice_unpaid_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">First overdue reminder</label><Num name="first_overdue_reminder_days" value={settings.first_overdue_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Second overdue reminder</label><Num name="second_overdue_reminder_days" value={settings.second_overdue_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Third overdue reminder</label><Num name="third_overdue_reminder_days" value={settings.third_overdue_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Late fee days</label><Num name="late_fee_days" value={settings.late_fee_days} errors={errors} /></div>
                                <Field label="Late fee type" name="late_fee_type" errors={errors}>
                                    <select name="late_fee_type" defaultValue={settings.late_fee_type || 'fixed'} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                        <option value="fixed">Fixed</option>
                                        <option value="percent">Percent</option>
                                    </select>
                                </Field>
                                <div><label className="text-sm text-slate-600">Late fee amount</label><Num name="late_fee_amount" value={settings.late_fee_amount} errors={errors} step={0.01} min={0} max={1000000} /></div>
                                <Field label="Overage billing mode" name="overage_billing_mode" errors={errors}>
                                    <select name="overage_billing_mode" defaultValue={settings.overage_billing_mode || 'last_day_separate'} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                        <option value="last_day_separate">Last day separate invoice</option>
                                        <option value="last_day_next_invoice">Last day include next invoice</option>
                                    </select>
                                </Field>
                                <Check name="change_invoice_status_on_reversal" checked={settings.change_invoice_status_on_reversal} label="Change status on reversal" />
                                <Check name="change_due_dates_on_reversal" checked={settings.change_due_dates_on_reversal} label="Change due dates on reversal" />
                                <Check name="enable_auto_cancellation" checked={settings.enable_auto_cancellation} label="Enable auto cancellation" />
                                <div><label className="text-sm text-slate-600">Auto cancellation days</label><Num name="auto_cancellation_days" value={settings.auto_cancellation_days} errors={errors} /></div>
                                <Check name="auto_bind_domains" checked={settings.auto_bind_domains} label="Auto bind domains on first check" />
                                <div><label className="text-sm text-slate-600">License first notice days</label><Num name="license_expiry_first_notice_days" value={settings.license_expiry_first_notice_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">License second notice days</label><Num name="license_expiry_second_notice_days" value={settings.license_expiry_second_notice_days} errors={errors} /></div>
                            </div>
                        </div>
                    </section>

                    <section className={tab === 'tasks' ? 'space-y-6' : 'hidden'}>
                        <div className="card p-6">
                            <div className="section-label">Task Defaults</div>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <label className="text-sm text-slate-600">Enabled task types</label>
                                    <div className="mt-3 grid gap-2 md:grid-cols-3">
                                        {Object.entries(task_type_labels).map(([value, label]) => (
                                            <label key={value} className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                                                <input type="checkbox" name="task_types_enabled[]" value={value} defaultChecked={Array.isArray(settings.task_types_enabled) && settings.task_types_enabled.includes(value)} className="rounded border-slate-300 text-teal-500" />
                                                <span>{label}</span>
                                            </label>
                                        ))}
                                    </div>
                                    <InputError errors={errors} name="task_types_enabled" />
                                </div>
                                <Field label="Custom task label" name="task_custom_type_label" errors={errors}>
                                    <input name="task_custom_type_label" defaultValue={settings.task_custom_type_label || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                </Field>
                                <div><label className="text-sm text-slate-600">Upload size limit (MB)</label><Num name="task_upload_max_mb" value={settings.task_upload_max_mb} errors={errors} min={1} max={100} /></div>
                                <Check name="task_customer_visible_default" checked={settings.task_customer_visible_default} label="Default new tasks as customer visible" />
                            </div>
                        </div>
                    </section>

                    <section className={tab === 'email-templates' ? 'space-y-6' : 'hidden'}>
                        <div className="card p-6">
                            <div className="section-label">Email Templates</div>
                            {email_template_groups.length === 0 ? (
                                <div className="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">No templates found.</div>
                            ) : (
                                <div className="mt-4 space-y-6">
                                    {email_template_groups.map((group) => (
                                        <div key={group.category} className="space-y-4">
                                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{group.category}</div>
                                            {group.templates.map((template) => (
                                                <details key={template.id} className="rounded-2xl border border-slate-200 bg-white p-5">
                                                    <summary className="flex cursor-pointer items-center justify-between text-sm font-semibold text-slate-700">
                                                        <span>{template.name}</span>
                                                        <span className="text-xs font-normal text-slate-400">{template.key}</span>
                                                    </summary>
                                                    <div className="mt-5 grid gap-4">
                                                        <Field label="From email" name={`templates.${template.id}.from_email`} errors={errors}>
                                                            <input name={`templates[${template.id}][from_email]`} defaultValue={template.from_email || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                                        </Field>
                                                        <Field label="Subject" name={`templates.${template.id}.subject`} errors={errors}>
                                                            <input name={`templates[${template.id}][subject]`} defaultValue={template.subject || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                                        </Field>
                                                        <Field label="Body" name={`templates.${template.id}.body`} errors={errors}>
                                                            <textarea name={`templates[${template.id}][body]`} rows={6} defaultValue={template.body || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                                                        </Field>
                                                    </div>
                                                </details>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <div className="flex justify-end pb-2">
                        <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white hover:bg-teal-600">
                            Save settings
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
