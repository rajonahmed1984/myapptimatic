import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import RecaptchaField from '../../Components/Form/RecaptchaField';
import SubmitButton from '../../Components/Form/SubmitButton';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

export default function PortalLogin({ pageTitle = 'Sign In', portal = 'web', form = {}, routes = {}, hint = null, recaptcha = {} }) {
    const { errors = {}, flash = {}, branding = {}, csrf_token: csrfToken = '' } = usePage().props;

    return (
        <>
            <Head title={pageTitle} />
            <GuestAuthLayout>
                <section className="auth-glass-card relative overflow-hidden rounded-2xl px-8 py-10 text-white sm:px-10">
                    <div className="auth-glass-overlay absolute inset-0"></div>
                    <div className="auth-glass-readable relative z-10 text-center">
                        <div className="mb-6 flex justify-center">
                            <a href="/" className="flex items-center gap-3" data-native="true">
                                {branding?.logo_url ? (
                                    <img src={branding.logo_url} alt="Company logo" className="h-12 rounded-xl p-1" />
                                ) : (
                                    <div className="text-lg font-semibold text-white">MyApptimatic</div>
                                )}
                            </a>
                        </div>
                        <AlertStack status={flash?.status} errors={errors} singleError />
                        <p className="text-sm font-semibold uppercase tracking-[0.32em] text-white">Welcome Back</p>
                        <p className="mt-2 text-sm text-slate-100/95">Sign in to continue</p>

                        <form className="mt-8 space-y-5 text-left" method="POST" action={routes?.submit || '/login'} data-native="true">
                            <input type="hidden" name="_token" value={csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                            {form?.redirect ? <input type="hidden" name="redirect" value={form.redirect} /> : null}

                            <InputField
                                name="email"
                                type="email"
                                defaultValue={form?.email || ''}
                                placeholder="Email"
                                required
                                autoFocus
                                error={errors?.email}
                            />

                            <InputField
                                name="password"
                                type="password"
                                placeholder="Password"
                                required
                                error={errors?.password}
                            />

                            <div className="flex items-center justify-between text-sm text-slate-100/95">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        value="1"
                                        defaultChecked={Boolean(form?.remember)}
                                        className="rounded border-white/30 text-teal-500 focus:ring-teal-200"
                                    />
                                    Remember me
                                </label>
                                {routes?.forgot ? (
                                    <a href={routes.forgot} className="text-teal-300 hover:text-teal-200" data-native="true">
                                        Forgot password?
                                    </a>
                                ) : null}
                            </div>

                            <RecaptchaField
                                enabled={Boolean(recaptcha?.enabled)}
                                siteKey={recaptcha?.site_key || ''}
                                action={recaptcha?.action || 'LOGIN'}
                            />

                            <div className="flex justify-center">
                                <SubmitButton className="max-w-xs">Sign in</SubmitButton>
                            </div>
                        </form>

                        {hint?.href && hint?.text ? (
                            <p className="mt-6 text-center text-xs text-slate-100/95">
                                {hint?.label ? `${hint.label} ` : ''}
                                <a href={hint.href} className="font-semibold text-teal-300 hover:text-teal-200" data-native="true">
                                    {hint.text}
                                </a>
                                {portal === 'web' ? '.' : null}
                            </p>
                        ) : null}
                    </div>
                </section>
            </GuestAuthLayout>
        </>
    );
}
