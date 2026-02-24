import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

const on = (v) => v === true || v === 1 || v === '1' || v === 'true';

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
        {errors?.[name] ? <div className="mt-1 text-xs text-rose-600">{errors[name]}</div> : null}
    </div>
);

const Check = ({ name, checked, label }) => (
    <label className="flex items-center gap-3 text-sm text-slate-600">
        <input type="hidden" name={name} value="0" />
        <input type="checkbox" name={name} value="1" defaultChecked={on(checked)} className="rounded border-slate-300 text-teal-500" />
        {label}
    </label>
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

    const openTab = (next) => {
        setTab(next);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', next);
        window.history.replaceState(null, '', url.toString());
    };

    return (
        <>
            <Head title={pageTitle} />
            <div className="card p-6">
                <div className="mt-8">
                    <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-4 text-sm">
                        {['general', 'invoices', 'automation', 'billing', 'tasks', 'email-templates'].map((t) => (
                            <button
                                key={t}
                                type="button"
                                onClick={() => openTab(t)}
                                className={`rounded-full border px-4 py-2 ${tab === t ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-600'}`}
                            >
                                {t}
                            </button>
                        ))}
                    </div>

                    <form method="POST" action={routes?.update} encType="multipart/form-data" data-native="true" className="mt-8 space-y-8">
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="hidden" name="_method" value="PUT" />
                        <input type="hidden" name="active_tab" value={tab} />

                        <section className={tab === 'general' ? 'space-y-6' : 'hidden'}>
                            <div className="section-label">General</div>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div><label className="text-sm text-slate-600">Company name</label><input name="company_name" defaultValue={settings.company_name || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />{errors?.company_name ? <div className="mt-1 text-xs text-rose-600">{errors.company_name}</div> : null}</div>
                                <div><label className="text-sm text-slate-600">Pay to text</label><input name="pay_to_text" defaultValue={settings.pay_to_text || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                <div><label className="text-sm text-slate-600">Email</label><input name="company_email" type="email" defaultValue={settings.company_email || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                <div><label className="text-sm text-slate-600">App URL</label><input name="app_url" type="url" defaultValue={settings.app_url || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                <div>
                                    <label className="text-sm text-slate-600">Country</label>
                                    <select name="company_country" defaultValue={settings.company_country || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                        <option value="">Select country</option>
                                        {countries.map((country) => <option key={country} value={country}>{country}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Date format</label>
                                    <select name="date_format" defaultValue={settings.date_format || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                        {Object.entries(date_formats).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Time zone</label>
                                    <select name="time_zone" defaultValue={settings.time_zone || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                        {time_zones.map((zone) => <option key={zone} value={zone}>{zone}</option>)}
                                    </select>
                                </div>
                                <div><label className="text-sm text-slate-600">Automation time</label><input name="automation_time_of_day" type="time" defaultValue={settings.automation_time_of_day || '00:00'} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                <div><label className="text-sm text-slate-600">Company logo</label><input name="company_logo" type="file" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                <div><label className="text-sm text-slate-600">Favicon</label><input name="company_favicon" type="file" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                <div className="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-5">
                                    <div className="text-sm font-semibold text-slate-800">reCAPTCHA</div>
                                    <Check name="enable_recaptcha" checked={settings.recaptcha_enabled} label="Enable" />
                                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                                        <div><label className="text-sm text-slate-600">Site key</label><input name="recaptcha_site_key" defaultValue={settings.recaptcha_site_key || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                        <div><label className="text-sm text-slate-600">Secret key</label><input name="recaptcha_secret_key" type="password" defaultValue={settings.recaptcha_secret_key || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                        <div><label className="text-sm text-slate-600">Project ID</label><input name="recaptcha_project_id" defaultValue={settings.recaptcha_project_id || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                        <div><label className="text-sm text-slate-600">API key</label><input name="recaptcha_api_key" type="password" defaultValue={settings.recaptcha_api_key || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                        <div><label className="text-sm text-slate-600">Score threshold</label><input name="recaptcha_score_threshold" type="number" step="0.01" min="0" max="1" defaultValue={settings.recaptcha_score_threshold || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className={tab === 'invoices' ? 'space-y-6' : 'hidden'}>
                            <div className="section-label">Invoices</div>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div><label className="text-sm text-slate-600">Currency</label><select name="currency" defaultValue={settings.currency || 'USD'} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"><option value="BDT">BDT</option><option value="USD">USD</option></select></div>
                                <div><label className="text-sm text-slate-600">Invoice due days</label><Num name="invoice_due_days" value={settings.invoice_due_days} errors={errors} /></div>
                                <div className="md:col-span-2"><label className="text-sm text-slate-600">Payment instructions</label><textarea name="payment_instructions" rows={3} defaultValue={settings.payment_instructions || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                            </div>
                        </section>

                        <section className={tab === 'automation' ? 'space-y-6' : 'hidden'}>
                            <div className="section-label">Automation</div>
                            <div className="grid gap-6 md:grid-cols-2">
                                <Check name="enable_suspension" checked={settings.enable_suspension} label="Enable suspension" />
                                <div><label className="text-sm text-slate-600">Suspend days</label><Num name="suspend_days" value={settings.suspend_days} errors={errors} /></div>
                                <Check name="send_suspension_email" checked={settings.send_suspension_email} label="Send suspension email" />
                                <Check name="enable_unsuspension" checked={settings.enable_unsuspension} label="Enable unsuspension" />
                                <Check name="send_unsuspension_email" checked={settings.send_unsuspension_email} label="Send unsuspension email" />
                                <Check name="enable_termination" checked={settings.enable_termination} label="Enable termination" />
                                <div><label className="text-sm text-slate-600">Termination days</label><Num name="termination_days" value={settings.termination_days} errors={errors} /></div>
                                <div className="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-5">
                                    <div className="text-sm font-semibold text-slate-800">Support ticket automation</div>
                                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                                        <div><label className="text-sm text-slate-600">Auto-close days</label><Num name="ticket_auto_close_days" value={settings.ticket_auto_close_days} errors={errors} /></div>
                                        <div><label className="text-sm text-slate-600">Admin reminder days</label><Num name="ticket_admin_reminder_days" value={settings.ticket_admin_reminder_days} errors={errors} /></div>
                                        <div><label className="text-sm text-slate-600">Feedback days</label><Num name="ticket_feedback_days" value={settings.ticket_feedback_days} errors={errors} /></div>
                                        <div><label className="text-sm text-slate-600">Cleanup days</label><Num name="ticket_cleanup_days" value={settings.ticket_cleanup_days} errors={errors} max={3650} /></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className={tab === 'billing' ? 'space-y-6' : 'hidden'}>
                            <div className="section-label">Billing</div>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div><label className="text-sm text-slate-600">Invoice generation days</label><Num name="invoice_generation_days" value={settings.invoice_generation_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Grace period days</label><Num name="grace_period_days" value={settings.grace_period_days} errors={errors} /></div>
                                <Check name="payment_reminder_emails" checked={settings.payment_reminder_emails} label="Payment reminder emails" />
                                <div><label className="text-sm text-slate-600">Unpaid reminder days</label><Num name="invoice_unpaid_reminder_days" value={settings.invoice_unpaid_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">First overdue reminder</label><Num name="first_overdue_reminder_days" value={settings.first_overdue_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Second overdue reminder</label><Num name="second_overdue_reminder_days" value={settings.second_overdue_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Third overdue reminder</label><Num name="third_overdue_reminder_days" value={settings.third_overdue_reminder_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Late fee days</label><Num name="late_fee_days" value={settings.late_fee_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">Late fee type</label><select name="late_fee_type" defaultValue={settings.late_fee_type || 'fixed'} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"><option value="fixed">Fixed</option><option value="percent">Percent</option></select></div>
                                <div><label className="text-sm text-slate-600">Late fee amount</label><Num name="late_fee_amount" value={settings.late_fee_amount} errors={errors} step={0.01} min={0} max={1000000} /></div>
                                <div><label className="text-sm text-slate-600">Overage billing mode</label><select name="overage_billing_mode" defaultValue={settings.overage_billing_mode || 'last_day_separate'} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"><option value="last_day_separate">Last day separate invoice</option><option value="last_day_next_invoice">Last day include next invoice</option></select></div>
                                <Check name="change_invoice_status_on_reversal" checked={settings.change_invoice_status_on_reversal} label="Change status on reversal" />
                                <Check name="change_due_dates_on_reversal" checked={settings.change_due_dates_on_reversal} label="Change due dates on reversal" />
                                <Check name="enable_auto_cancellation" checked={settings.enable_auto_cancellation} label="Enable auto cancellation" />
                                <div><label className="text-sm text-slate-600">Auto cancellation days</label><Num name="auto_cancellation_days" value={settings.auto_cancellation_days} errors={errors} /></div>
                                <Check name="auto_bind_domains" checked={settings.auto_bind_domains} label="Auto bind domains on first check" />
                                <div><label className="text-sm text-slate-600">License first notice days</label><Num name="license_expiry_first_notice_days" value={settings.license_expiry_first_notice_days} errors={errors} /></div>
                                <div><label className="text-sm text-slate-600">License second notice days</label><Num name="license_expiry_second_notice_days" value={settings.license_expiry_second_notice_days} errors={errors} /></div>
                            </div>
                        </section>

                        <section className={tab === 'tasks' ? 'space-y-6' : 'hidden'}>
                            <div className="section-label">Tasks</div>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <label className="text-sm text-slate-600">Enabled task types</label>
                                    <div className="mt-3 grid gap-2 md:grid-cols-3">
                                        {Object.entries(task_type_labels).map(([value, label]) => (
                                            <label key={value} className="flex items-center gap-2 text-sm text-slate-600">
                                                <input type="checkbox" name="task_types_enabled[]" value={value} defaultChecked={Array.isArray(settings.task_types_enabled) && settings.task_types_enabled.includes(value)} className="rounded border-slate-300 text-teal-500" />
                                                <span>{label}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                                <div><label className="text-sm text-slate-600">Custom task label</label><input name="task_custom_type_label" defaultValue={settings.task_custom_type_label || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                <div><label className="text-sm text-slate-600">Upload size limit (MB)</label><Num name="task_upload_max_mb" value={settings.task_upload_max_mb} errors={errors} min={1} max={100} /></div>
                                <Check name="task_customer_visible_default" checked={settings.task_customer_visible_default} label="Default new tasks as customer visible" />
                            </div>
                        </section>

                        <section className={tab === 'email-templates' ? 'space-y-6' : 'hidden'}>
                            <div className="section-label">Email templates</div>
                            {email_template_groups.length === 0 ? (
                                <div className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">No templates found.</div>
                            ) : (
                                <div className="space-y-6">
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
                                                        <div><label className="text-sm text-slate-600">From email</label><input name={`templates[${template.id}][from_email]`} defaultValue={template.from_email || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                                        <div><label className="text-sm text-slate-600">Subject</label><input name={`templates[${template.id}][subject]`} defaultValue={template.subject || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                                        <div><label className="text-sm text-slate-600">Body</label><textarea name={`templates[${template.id}][body]`} rows={6} defaultValue={template.body || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                                                    </div>
                                                </details>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>

                        <div className="flex justify-end pt-6">
                            <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">Save settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
