import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import mediaUrl from '../../../utils/mediaUrl';
import useObjectUrlPreview from '../../../hooks/useObjectUrlPreview';
import PasswordInput from '../../../Components/Form/PasswordInput';

function PayoutAccountForm({ account = {}, methods = [], action, csrfToken, errors = {} }) {
    const inputClass = 'mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm';
    const labelClass = 'block text-xs font-semibold text-slate-600';
    const saved = Boolean(account?.method && account?.account_number);

    return (
        <div className="card p-6">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div className="text-sm font-semibold text-slate-800">Payout account</div>
                    <p className="text-xs text-slate-500">Where the company sends your commission. The admin sees this when paying your requests.</p>
                </div>
                {!saved ? (
                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Not set yet</span>
                ) : null}
            </div>

            <form method="POST" action={action} data-native="true" className="mt-4 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="_token" value={csrfToken} />
                <input type="hidden" name="_method" value="PUT" />
                <label>
                    <span className={labelClass}>Method</span>
                    <select name="payout_method" required defaultValue={account?.method || ''} className={inputClass}>
                        <option value="" disabled>Choose…</option>
                        {methods.map((method) => (
                            <option key={method.code} value={method.code}>{method.name}</option>
                        ))}
                    </select>
                    {errors?.payout_method ? <span className="mt-1 block text-xs text-rose-600">{errors.payout_method}</span> : null}
                </label>
                <label>
                    <span className={labelClass}>Account / mobile number</span>
                    <input name="account_number" required defaultValue={account?.account_number || ''} placeholder="e.g. 01711000000" className={inputClass} />
                    {errors?.account_number ? <span className="mt-1 block text-xs text-rose-600">{errors.account_number}</span> : null}
                </label>
                <label>
                    <span className={labelClass}>Account holder name</span>
                    <input name="account_name" required defaultValue={account?.account_name || ''} className={inputClass} />
                    {errors?.account_name ? <span className="mt-1 block text-xs text-rose-600">{errors.account_name}</span> : null}
                </label>
                <label>
                    <span className={labelClass}>Bank name (for bank transfer)</span>
                    <input name="bank_name" defaultValue={account?.bank_name || ''} className={inputClass} />
                </label>
                <label>
                    <span className={labelClass}>Branch (for bank transfer)</span>
                    <input name="branch" defaultValue={account?.branch || ''} className={inputClass} />
                </label>
                <label>
                    <span className={labelClass}>Note (optional)</span>
                    <input name="note" defaultValue={account?.note || ''} placeholder="e.g. Personal bKash" className={inputClass} />
                </label>
                <div className="flex justify-end md:col-span-2">
                    <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">Save payout account</button>
                </div>
            </form>
        </div>
    );
}

export default function Edit({ user = {}, sales_rep = {}, form = {}, payout_account: payoutAccount = null, payout_methods: payoutMethods = [], payout_account_action: payoutAccountAction = '' }) {
    const page = usePage();
    const csrfToken = page?.props?.csrf_token || '';
    const errors = page?.props?.errors || {};
    const [avatarFile, setAvatarFile] = useState(null);
    const previewUrl = useObjectUrlPreview(avatarFile, { enabled: String(avatarFile?.type || '').startsWith('image/') });
    const image = previewUrl || mediaUrl(sales_rep?.avatar_path || user?.avatar_path) || '';

    return (
        <>
            <Head title="Profile" />

            <div className="card p-6">

                <form method="POST" action={form?.action} className="mt-6 space-y-6" encType="multipart/form-data" data-native="true">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <input type="hidden" name="_method" value={form?.method || 'PUT'} />

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 overflow-hidden rounded-full border border-slate-200 bg-white">
                                {image ? <img src={image} alt="Avatar" className="h-16 w-16 object-cover" /> : <div className="flex h-full w-full items-center justify-center text-xs text-slate-400">No photo</div>}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Profile photo</label>
                                <input
                                    name="avatar"
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) => setAvatarFile(event.target.files?.[0] || null)}
                                    className="mt-2 text-sm text-slate-600"
                                />
                                {errors?.avatar ? <div className="mt-1 text-xs text-rose-600">{errors.avatar}</div> : null}
                            </div>
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Full name</label>
                            <input name="name" defaultValue={user?.name || ''} required className="ui-input mt-2" />
                            {errors?.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Email</label>
                            <input name="email" type="email" defaultValue={user?.email || ''} required className="ui-input mt-2" />
                            {errors?.email ? <div className="mt-1 text-xs text-rose-600">{errors.email}</div> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Phone</label>
                            <input name="phone" type="text" defaultValue={sales_rep?.phone || ''} className="ui-input mt-2" />
                            {errors?.phone ? <div className="mt-1 text-xs text-rose-600">{errors.phone}</div> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div><label className="text-sm text-slate-600">Current password</label><PasswordInput name="current_password" wrapperClassName="mt-2" /></div>
                        <div><label className="text-sm text-slate-600">New password</label><PasswordInput name="password" wrapperClassName="mt-2" /></div>
                        <div><label className="text-sm text-slate-600">Confirm new password</label><PasswordInput name="password_confirmation" wrapperClassName="mt-2" /></div>
                    </div>

                    <div className="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-5">
                        <div className="flex items-start justify-between gap-4">
                            <div className="flex items-start gap-3">
                                <div className="mt-0.5 rounded-xl bg-teal-50 p-2 text-teal-600 border border-teal-100">
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-slate-800">Email OTP Login Security</h3>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        When enabled, an email with a 6-digit verification code will be required each time you sign in to the Sales portal.
                                    </p>
                                </div>
                            </div>
                            <label className="relative inline-flex cursor-pointer items-center shrink-0">
                                <input
                                    type="checkbox"
                                    name="otp_enabled"
                                    value="1"
                                    defaultChecked={Boolean(user?.otp_enabled || form?.otp_enabled)}
                                    className="peer sr-only"
                                />
                                <div className="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-teal-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none" />
                            </label>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">Save profile</button>
                    </div>
                </form>
            </div>

            {payoutAccountAction ? (
                <div className="mt-6">
                    <PayoutAccountForm
                        account={payoutAccount || {}}
                        methods={payoutMethods}
                        action={payoutAccountAction}
                        csrfToken={csrfToken}
                        errors={errors}
                    />
                </div>
            ) : null}
        </>
    );
}
