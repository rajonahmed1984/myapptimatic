import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import RecaptchaField from '../../Components/Form/RecaptchaField';
import SubmitButton from '../../Components/Form/SubmitButton';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

export default function ForgotPassword({ pageTitle = 'Forgot Password', form = {}, routes = {}, recaptcha = {}, messages = {} }) {
    const { errors = {}, flash = {} } = usePage().props;
    const emailError = errors?.email || null;
    const isThrottled = typeof emailError === 'string' && emailError === messages?.throttled;
    const isWarningEmailError = Boolean(emailError) && (isThrottled || Boolean(messages?.email_error_warning));

    return (
        <>
            <Head title={pageTitle} />
            <GuestAuthLayout>
                <section className="relative -m-8 overflow-hidden rounded-2xl bg-slate-900 px-8 py-10 text-white sm:px-10">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(45,212,191,0.35),transparent_45%),radial-gradient(circle_at_80%_75%,rgba(59,130,246,0.26),transparent_44%)]"></div>
                    <div className="relative z-10">
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
