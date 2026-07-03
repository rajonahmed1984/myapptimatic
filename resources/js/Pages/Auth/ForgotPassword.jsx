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
                <section className="bg-white border border-slate-200/60 relative overflow-hidden rounded-3xl p-8 text-slate-800 shadow-xl shadow-slate-200/30 sm:p-10">
                    <div className="relative z-10">
                        <div className="mb-6 flex justify-center">
                            <a href="/" className="flex items-center gap-3" data-native="true">
                                {branding?.logo_url ? (
                                    <img src={branding.logo_url} alt="Company logo" className="h-12 rounded-xl p-1" />
                                ) : (
                                    <div className="text-lg font-bold text-slate-900 tracking-tight">MyApptimatic</div>
                                )}
                            </a>
                        </div>

                        <div className="text-center mb-6">
                            <p className="text-xs font-semibold uppercase tracking-[0.36em] text-teal-600">Password reset</p>
                            <h1 className="mt-2 text-2xl font-bold text-slate-900 tracking-tight">Forgot Password</h1>
                            <p className="mt-2 text-xs text-slate-500">Enter your email and we will send a reset link.</p>
                        </div>

                        {isWarningEmailError ? (
                            <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 text-left">{emailError}</div>
                        ) : null}
                        {!isWarningEmailError ? (
                            <AlertStack
                                status={flash?.status || messages?.status}
                                errors={{ email: emailError }}
                                singleError
                                statusClassName="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 text-left"
                                errorClassName="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 text-left"
                            />
                        ) : null}
                        {isWarningEmailError && (flash?.status || messages?.status) ? (
                            <AlertStack
                                status={flash?.status || messages?.status}
                                errors={null}
                                singleError
                                statusClassName="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 text-left"
                                errorClassName="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 text-left"
                            />
                        ) : null}
                        <AlertStack
                            status={null}
                            errors={Object.fromEntries(Object.entries(errors).filter(([key]) => key !== 'email'))}
                            singleError
                            statusClassName="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 text-left"
                            errorClassName="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 text-left"
                        />

                        <form className="mt-6 space-y-5 text-left" method="POST" action={routes.email} data-native="true">
                            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                            <InputField
                                name="email"
                                type="email"
                                defaultValue={form?.email || ''}
                                placeholder="Email"
                                required
                                error={isThrottled ? null : emailError}
                                inputClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full"
                            />
                            <RecaptchaField
                                enabled={Boolean(recaptcha?.enabled)}
                                siteKey={recaptcha?.site_key || ''}
                                action={recaptcha?.action || 'FORGOT_PASSWORD'}
                            />
                            <div className="pt-2 flex justify-center">
                                <SubmitButton className="h-10 rounded-full bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold tracking-wide transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg active:scale-[0.98] max-w-xs">
                                    Send reset link
                                </SubmitButton>
                            </div>
                        </form>

                        <p className="mx-auto mt-6 w-full text-center text-xs text-slate-500">
                            Remember your password?{' '}
                            <a href={routes.login || '/login'} className="font-semibold text-teal-600 hover:text-teal-500" data-native="true">
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
