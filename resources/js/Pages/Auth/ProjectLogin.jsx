import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import RecaptchaField from '../../Components/Form/RecaptchaField';
import SubmitButton from '../../Components/Form/SubmitButton';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

export default function ProjectLogin({ form = {}, routes = {}, recaptcha = {} }) {
    const { errors = {}, flash = {}, branding = {} } = usePage().props;

    return (
        <>
            <Head title="Project Client Sign In" />
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
                        <p className="mt-2 text-sm text-slate-100/95">Sign in to your project workspace</p>

                        <form className="mt-8 space-y-5 text-left" method="POST" action={routes.submit} data-native="true">
                            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                            <InputField
                                name="email"
                                type="email"
                                defaultValue={form?.email || ''}
                                placeholder="Email"
                                required
                                error={errors?.email}
                            />
                            <InputField name="password" type="password" placeholder="Password" required error={errors?.password} />
                            <div className="flex items-center justify-between text-sm text-slate-100/95">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        defaultChecked={Boolean(form?.remember)}
                                        className="rounded border-white/30 text-teal-500 focus:ring-teal-200"
                                    />
                                    Remember me
                                </label>
                            </div>
                            <RecaptchaField
                                enabled={Boolean(recaptcha?.enabled)}
                                siteKey={recaptcha?.site_key || ''}
                                action={recaptcha?.action || 'PROJECT_CLIENT_LOGIN'}
                            />
                            <div className="flex justify-center">
                                <SubmitButton className="max-w-xs">Sign in to project</SubmitButton>
                            </div>
                        </form>
                    </div>
                </section>
            </GuestAuthLayout>
        </>
    );
}
