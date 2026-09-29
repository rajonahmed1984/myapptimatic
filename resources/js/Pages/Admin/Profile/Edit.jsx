import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import useObjectUrlPreview from '../../../hooks/useObjectUrlPreview';
import PasswordInput from '../../../Components/Form/PasswordInput';

const initials = (name = '') =>
    String(name)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || '')
        .join('') || 'U';

export default function Edit({ pageTitle = 'Profile', form = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const [avatarFile, setAvatarFile] = useState(null);
    const previewUrl = useObjectUrlPreview(avatarFile, { enabled: String(avatarFile?.type || '').startsWith('image/') });
    const avatarUrl = previewUrl || form?.avatar_url || '';

    return (
        <>
            <Head title={pageTitle} />


            <div className="card p-6">
                <form method="POST" action={form?.action} className="mt-6 space-y-6" encType="multipart/form-data" data-native="true">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value={form?.method || 'PUT'} />

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-sm text-slate-600">Full name</label>
                            <input
                                name="name"
                                defaultValue={fields?.name || ''}
                                required
                                className="ui-input mt-2"
                            />
                            {errors?.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Email</label>
                            <input
                                name="email"
                                type="email"
                                defaultValue={fields?.email || ''}
                                required
                                className="ui-input mt-2"
                            />
                            {errors?.email ? <p className="mt-1 text-xs text-rose-600">{errors.email}</p> : null}
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Current password</label>
                            <PasswordInput name="current_password" wrapperClassName="mt-2" />
                            {errors?.current_password ? <p className="mt-1 text-xs text-rose-600">{errors.current_password}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-sm text-slate-600">New password</label>
                            <PasswordInput name="password" wrapperClassName="mt-2" />
                            {errors?.password ? <p className="mt-1 text-xs text-rose-600">{errors.password}</p> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Confirm new password</label>
                            <PasswordInput
                                name="password_confirmation"
                                wrapperClassName="mt-2"
                            />
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 overflow-hidden rounded-full border border-slate-200 bg-white">
                                {avatarUrl ? (
                                    <img src={avatarUrl} alt={fields?.name || 'Avatar'} className="h-16 w-16 object-cover" />
                                ) : (
                                    <div className="flex h-16 w-16 items-center justify-center text-sm font-semibold text-slate-700">
                                        {initials(fields?.name || '')}
                                    </div>
                                )}
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
                                <p className="text-xs text-slate-500">PNG/JPG up to 2MB.</p>
                                {errors?.avatar ? <p className="mt-1 text-xs text-rose-600">{errors.avatar}</p> : null}
                            </div>
                        </div>
                    </div>


                    <div className="rounded-2xl border border-teal-200/80 bg-teal-50/40 p-5">
                        <div className="flex items-start justify-between gap-4">
                            <div className="flex items-start gap-3">
                                <div className="mt-0.5 rounded-xl bg-teal-600 p-2 text-white shadow-sm">
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                    </svg>
                                </div>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-sm font-semibold text-slate-800">Admin Email OTP Security</h3>
                                        <span className="inline-flex items-center rounded-full bg-teal-100 px-2.5 py-0.5 text-[11px] font-semibold text-teal-800 border border-teal-200">
                                            Enforced & Active
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-600 leading-relaxed">
                                        Two-Factor Email OTP authentication is mandatory for all administrator logins. A 6-digit verification code is required on sign-in. In local environment, the temporary code <code className="font-mono font-bold bg-teal-100/80 px-1 py-0.5 rounded text-teal-900">123456</code> is supported.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">
                            Save profile
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
