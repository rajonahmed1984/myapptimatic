import React from 'react';

function getErrorBag(errors, bagName) {
    if (!bagName) {
        return {};
    }

    return errors?.[bagName] || {};
}

function getFieldError(errorBag, key) {
    return errorBag?.[`license.${key}`] || errorBag?.[key] || '';
}

function LicenseFormCard({
    title,
    description = '',
    submitLabel,
    form,
    fields,
    subscriptionLabel = '',
    errorBagName,
    errors,
    csrfToken,
    productName = '',
    licenseId = null,
    routes = {},
    domains = [],
}) {
    const errorBag = getErrorBag(errors, errorBagName);
    const currentStatus = String(fields?.status || '').toLowerCase();
    const canSuspendNow = Boolean(routes?.suspend) && currentStatus !== 'revoked';
    const canUnsuspendNow = Boolean(routes?.unsuspend) && currentStatus !== 'revoked';
    const canManageImmediateStatus = currentStatus !== 'revoked' && (Boolean(routes?.suspend) || Boolean(routes?.unsuspend));
    const targetId = licenseId ? `license-${licenseId}` : 'license-create';

    return (
        <div id={targetId} className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="mb-4">
                <div>
                    <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                    {description ? <p className="mt-1 text-xs text-slate-500">{description}</p> : null}
                </div>
            </div>

            <form action={form?.action} method="POST" data-native="true" className="space-y-4">
                <input type="hidden" name="_token" value={csrfToken} />
                {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                    <input type="hidden" name="_method" value={form?.method} />
                ) : null}
                <input type="hidden" name="_error_bag" value={errorBagName} />
                <input type="hidden" name="return_to_subscription" value="1" />
                <input type="hidden" name="return_target" value={targetId} />
                <input type="hidden" name="license[mode]" value={licenseId ? 'edit' : 'create'} />
                <input type="hidden" name="license[id]" value={licenseId || ''} />
                <input type="hidden" name="license[subscription_id]" value={fields?.subscription_id || ''} />
                <input type="hidden" name="license[product_id]" value={fields?.product_id || ''} />

                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Subscription</label>
                        <input
                            value={subscriptionLabel || ''}
                            readOnly
                            disabled
                            className="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-slate-500"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Product</label>
                        <input
                            value={productName || ''}
                            readOnly
                            disabled
                            className="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-slate-500"
                        />
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">License Key</label>
                        <input
                            name="license[license_key]"
                            defaultValue={fields?.license_key || ''}
                            placeholder={licenseId ? '' : 'Leave blank to auto-generate'}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                        {getFieldError(errorBag, 'license_key') ? <p className="mt-1 text-xs text-rose-600">{getFieldError(errorBag, 'license_key')}</p> : null}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Status</label>
                        <select name="license[status]" defaultValue={fields?.status || 'active'} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="revoked">Revoked</option>
                        </select>
                        {getFieldError(errorBag, 'status') ? <p className="mt-1 text-xs text-rose-600">{getFieldError(errorBag, 'status')}</p> : null}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Starts At</label>
                        <input
                            type="text"
                            placeholder="DD-MM-YYYY"
                            inputMode="numeric"
                            name="license[starts_at]"
                            defaultValue={fields?.starts_at || ''}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                        {getFieldError(errorBag, 'starts_at') ? <p className="mt-1 text-xs text-rose-600">{getFieldError(errorBag, 'starts_at')}</p> : null}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Expires At</label>
                        <input
                            type="text"
                            placeholder="DD-MM-YYYY"
                            inputMode="numeric"
                            name="license[expires_at]"
                            defaultValue={fields?.expires_at || ''}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                        {getFieldError(errorBag, 'expires_at') ? <p className="mt-1 text-xs text-rose-600">{getFieldError(errorBag, 'expires_at')}</p> : null}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Override Auto-Suspend until</label>
                        <input
                            type="date"
                            name="license[auto_suspend_override_until]"
                            defaultValue={fields?.auto_suspend_override_until || ''}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            If set, this license stays active until the selected date.
                        </p>
                        {getFieldError(errorBag, 'auto_suspend_override_until') ? (
                            <p className="mt-1 text-xs text-rose-600">{getFieldError(errorBag, 'auto_suspend_override_until')}</p>
                        ) : null}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Allowed Domain</label>
                        <input
                            name="license[allowed_domains]"
                            defaultValue={fields?.allowed_domains || ''}
                            placeholder="example.com"
                            className="w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                        {getFieldError(errorBag, 'allowed_domains') ? <p className="mt-1 text-xs text-rose-600">{getFieldError(errorBag, 'allowed_domains')}</p> : null}
                    </div>
                </div>

                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                    <textarea
                        name="license[notes]"
                        rows={4}
                        defaultValue={fields?.notes || ''}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2"
                    />
                    {getFieldError(errorBag, 'notes') ? <p className="mt-1 text-xs text-rose-600">{getFieldError(errorBag, 'notes')}</p> : null}
                </div>

                <div className="flex flex-wrap items-center gap-3 pt-2">
                    <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        {submitLabel}
                    </button>
                </div>
            </form>

            {licenseId && canManageImmediateStatus ? (
                <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <div className="mb-2 text-sm font-semibold text-rose-800">Immediate Suspend</div>
                    <p className="mb-3 text-xs text-rose-700">
                        Use these actions to change the license status immediately without waiting for automation.
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        {canSuspendNow ? (
                            <form
                                action={routes?.suspend}
                                method="POST"
                                data-native="true"
                                onSubmit={(event) => {
                                    if (!window.confirm('Suspend this license now?')) {
                                        event.preventDefault();
                                    }
                                }}
                            >
                                <input type="hidden" name="_token" value={csrfToken} />
                                <input type="hidden" name="return_to_subscription" value="1" />
                                <input type="hidden" name="return_target" value={targetId} />
                                <button type="submit" className="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                    Suspend License
                                </button>
                            </form>
                        ) : null}
                        {canUnsuspendNow ? (
                            <form
                                action={routes?.unsuspend}
                                method="POST"
                                data-native="true"
                                onSubmit={(event) => {
                                    if (!window.confirm('Unsuspend this license now?')) {
                                        event.preventDefault();
                                    }
                                }}
                            >
                                <input type="hidden" name="_token" value={csrfToken} />
                                <input type="hidden" name="return_to_subscription" value="1" />
                                <input type="hidden" name="return_target" value={targetId} />
                                <button type="submit" className="rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                                    Unsuspend License
                                </button>
                            </form>
                        ) : null}
                    </div>
                </div>
            ) : null}

            {licenseId && domains.length > 0 ? (
                <div className="mt-6">
                    <h4 className="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Domains</h4>
                    <div className="space-y-2">
                        {domains.map((domain) => (
                            <div key={domain.id} className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2">
                                <div>
                                    <div className="text-sm font-medium text-slate-900">{domain.domain}</div>
                                    <div className="text-xs text-slate-500">{domain.status_label}</div>
                                </div>
                                {domain.can_revoke ? (
                                    <form action={domain.routes?.revoke} method="POST" data-native="true">
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="return_to_subscription" value="1" />
                                        <input type="hidden" name="return_target" value={targetId} />
                                        <button type="submit" className="rounded-lg border border-rose-300 px-3 py-1 text-xs font-semibold text-rose-700">
                                            Revoke
                                        </button>
                                    </form>
                                ) : null}
                            </div>
                        ))}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

export default function LicenseManager({ isEdit = false, licenseManager = {}, errors = {}, csrfToken = '' }) {
    if (!isEdit) {
        return null;
    }

    const licenses = licenseManager?.licenses || [];
    const createConfig = licenseManager?.create || null;
    const productName = licenseManager?.product?.name || '';

    return (
        <div className="mt-8 space-y-4">
            <div>
                <h2 className="text-lg font-semibold text-slate-900">Licenses</h2>
                <p className="mt-1 text-sm text-slate-500">Manage this subscription&apos;s licenses from one place.</p>
            </div>

            {licenses.length > 0 ? (
                <div className="space-y-4">
                    {licenses.map((license) => (
                        <LicenseFormCard
                            key={license.id}
                            title={`License #${license.id}`}
                            submitLabel="Update License"
                            form={license.form}
                            fields={license.fields}
                            subscriptionLabel={license.subscription_label}
                            errorBagName={license.error_bag}
                            errors={errors}
                            csrfToken={csrfToken}
                            productName={license.product_name || productName}
                            licenseId={license.id}
                            routes={license.routes}
                            domains={license.domains}
                        />
                    ))}
                </div>
            ) : (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                    No licenses added to this subscription yet.
                </div>
            )}

            {createConfig ? (
                createConfig.enabled ? (
                    <LicenseFormCard
                        title="Add License"
                        description="Create a license for this subscription."
                        submitLabel="Create License"
                        form={createConfig.form}
                        fields={createConfig.fields}
                        subscriptionLabel={createConfig.subscription_label}
                        errorBagName={createConfig.error_bag}
                        errors={errors}
                        csrfToken={csrfToken}
                        productName={createConfig.product_name || productName}
                    />
                ) : (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                        This subscription does not have a product on its selected plan, so new licenses cannot be created here yet.
                    </div>
                )
            ) : null}
        </div>
    );
}
