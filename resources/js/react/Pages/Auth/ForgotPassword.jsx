import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import RecaptchaField from '../../Components/Form/RecaptchaField';
import SubmitButton from '../../Components/Form/SubmitButton';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

export default function ForgotPassword({ pageTitle = 'Forgot Password', form = {}, routes = {}, recaptcha = {}, messages = {} }) {
    const { errors = {}, flash = {}, branding = {} } = usePage().props;
    const emailError = errors?.email || null;
    const isThrottled = typeof emailError === 'string' && emailError === messages?.throttled;
    const isWarningEmailError = Boolean(emailError) && (isThrottled || Boolean(messages?.email_error_warning));

    return (
        <>
            <Head title={pageTitle} />
            <GuestAuthLayout>
                <section className="auth-glass-card relative overflow-hidden rounded-2xl px-8 py-10 text-white sm:px-10">
                    <div className="auth-glass-overlay absolute inset-0"></div>
                    <div className="relative z-10">
                        <div className="mb-6 flex justify-center">
                            <a href="/" className="flex items-center gap-3" data-native="true">
                                {branding?.logo_url ? (
                                    <img src={branding.logo_url} alt="Company logo" className="h-12 rounded-xl p-1" />
                                ) : (
                                    <div className="text-lg font-semibold text-white">MyApptimatic</div>
                                )}
                            </a>
                        </div>
                        <p className="text-xs font-semibold uppercase tracking-[0.32em] text-slate-300/80">Password reset</p>
                        <p className="mt-2 text-sm text-slate-200/85">Enter your email and we will send a reset link.</p>

                        {isWarningEmailError ? (
                            <div className="mt-5 rounded-xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm font-medium text-amber-100">{emailError}</div>
                        ) : null}
                        {!isWarningEmailError ? <AlertStack status={flash?.status || messages?.status} errors={{ email: emailError }} singleError /> : null}
                        {isWarningEmailError && (flash?.status || messages?.status) ? <AlertStack status={flash?.status || messages?.status} errors={null} singleError /> : null}
                        <AlertStack status={null} errors={Object.fromEntries(Object.entries(errors).filter(([key]) => key !== 'email'))} singleError />

                        <form className="mt-8 space-y-5" method="POST" action={routes.email} data-native="true">
                            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                            <InputField
                                name="email"
                                type="email"
                                defaultValue={form?.email || ''}
                                placeholder="Email"
                                required
                                error={isThrottled ? null : emailError}
                            />
                            <RecaptchaField
                                enabled={Boolean(recaptcha?.enabled)}
                                siteKey={recaptcha?.site_key || ''}
                                action={recaptcha?.action || 'FORGOT_PASSWORD'}
                            />
                            <SubmitButton>Send reset link</SubmitButton>
                        </form>

                        <p className="mt-6 text-xs text-slate-200/85">
                            Remember your password?{' '}
                            <a href={routes.login || '/login'} className="font-semibold text-teal-300 hover:text-teal-200" data-native="true">
                                Sign in
                            </a>
                            .
                        </p>
                    </div>
                </section>
            </GuestAuthLayout>
        </>
    );
}
