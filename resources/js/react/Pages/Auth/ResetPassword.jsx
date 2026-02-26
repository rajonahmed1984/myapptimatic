import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import SubmitButton from '../../Components/Form/SubmitButton';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

export default function ResetPassword({ pageTitle = 'Reset Password', form = {}, routes = {}, messages = {} }) {
    const { errors = {}, flash = {}, branding = {} } = usePage().props;

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
                        <AlertStack status={flash?.status || messages?.status} errors={errors} singleError />

                        <form className="mt-8 space-y-5" method="POST" action={routes.submit} data-native="true">
                            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                            <input type="hidden" name="token" value={form?.token || ''} />
                            <InputField
                                name="email"
                                type="email"
                                defaultValue={form?.email || ''}
                                placeholder="Email"
                                required
                                error={errors?.email}
                            />
                            <InputField name="password" type="password" placeholder="New password" required error={errors?.password} />
                            <InputField
                                name="password_confirmation"
                                type="password"
                                placeholder="Confirm password"
                                required
                                error={errors?.password_confirmation}
                            />
                            <SubmitButton>Reset password</SubmitButton>
                        </form>

                        <p className="mt-6 text-xs text-slate-200/85">
                            Back to{' '}
                            <a href={routes.login || '/login'} className="font-semibold text-teal-300 hover:text-teal-200" data-native="true">
                                sign in
                            </a>
                            .
                        </p>
                    </div>
                </section>
            </GuestAuthLayout>
        </>
    );
}
