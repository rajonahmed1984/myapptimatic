import React, { useEffect, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import SelectField from '../../Components/Form/SelectField';
import SubmitButton from '../../Components/Form/SubmitButton';
import TextAreaField from '../../Components/Form/TextAreaField';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

const loadRecaptcha = () => {
    if (document.querySelector('script[data-recaptcha-enterprise]')) {
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://www.google.com/recaptcha/enterprise.js';
    script.async = true;
    script.defer = true;
    script.dataset.recaptchaEnterprise = 'true';
    document.head.appendChild(script);
};

export default function Register({ form = {}, routes = {}, recaptcha = {} }) {
    const { errors = {}, flash = {} } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');

    useEffect(() => {
        if (recaptcha?.enabled && recaptcha?.site_key) {
            loadRecaptcha();
        }
    }, [recaptcha]);

    const passwordMatchMessage = useMemo(() => {
        if (!passwordConfirmation) {
            return null;
        }

        if (password === passwordConfirmation) {
            return {
                text: 'Passwords match',
                className: 'mt-1 text-xs text-emerald-300',
            };
        }

        return {
            text: 'Passwords do not match',
            className: 'mt-1 text-xs text-rose-300',
        };
    }, [password, passwordConfirmation]);

    return (
        <>
            <Head title="Create Account" />
            <GuestAuthLayout wide>
                <section className="relative -m-8 overflow-hidden rounded-2xl bg-slate-900 px-8 py-10 text-white sm:px-10">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(45,212,191,0.35),transparent_45%),radial-gradient(circle_at_80%_75%,rgba(59,130,246,0.26),transparent_44%)]"></div>
                    <div className="relative z-10">
                        <AlertStack status={flash?.status} errors={errors} singleError />
                        <p className="text-xs font-semibold uppercase tracking-[0.36em] text-teal-200/90">Welcome Back</p>
                        <p className="mt-3 text-xs font-semibold uppercase tracking-[0.32em] text-slate-300/80">Register</p>

                        <form className="mt-6 space-y-5" method="POST" action={routes.submit} data-native="true">
                            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                            {form?.redirect ? <input type="hidden" name="redirect" value={form.redirect} /> : null}

                            <div className="grid gap-5 md:grid-cols-2">
                                <InputField label="Full name" name="name" defaultValue={form?.name || ''} placeholder="Full name" required error={errors?.name} />
                                <InputField
                                    label="Company name"
                                    name="company_name"
                                    defaultValue={form?.company_name || ''}
                                    placeholder="Company name"
                                    error={errors?.company_name}
                                />
                                <InputField
                                    label="Email"
                                    name="email"
                                    type="email"
                                    defaultValue={form?.email || ''}
                                    placeholder="Email"
                                    required
                                    error={errors?.email}
                                />
                                <InputField
                                    label="Password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    placeholder="Password"
                                    required
                                    error={errors?.password}
                                    onChange={(event) => setPassword(event.target.value)}
                                    inputClassName="pr-20"
                                />
                                <button
                                    type="button"
                                    className="relative -mt-[4.1rem] ml-auto mr-3 inline-flex rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700"
                                    onClick={() => setShowPassword((current) => !current)}
                                >
                                    {showPassword ? 'Hide' : 'Show'}
                                </button>
                                <InputField
                                    label="Confirm password"
                                    name="password_confirmation"
                                    type={showPasswordConfirmation ? 'text' : 'password'}
                                    placeholder="Confirm password"
                                    required
                                    error={errors?.password_confirmation}
                                    onChange={(event) => setPasswordConfirmation(event.target.value)}
                                    inputClassName="pr-20"
                                />
                                <button
                                    type="button"
                                    className="relative -mt-[4.1rem] ml-auto mr-3 inline-flex rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700"
                                    onClick={() => setShowPasswordConfirmation((current) => !current)}
                                >
                                    {showPasswordConfirmation ? 'Hide' : 'Show'}
                                </button>
                                {passwordMatchMessage ? (
                                    <p className={`${passwordMatchMessage.className} md:col-span-2`}>{passwordMatchMessage.text}</p>
                                ) : null}
                                <InputField
                                    label="Mobile number"
                                    name="phone"
                                    type="tel"
                                    defaultValue={form?.phone || ''}
                                    placeholder="Mobile number"
                                    error={errors?.phone}
                                />
                                <SelectField
                                    label="Currency"
                                    name="currency"
                                    defaultValue={form?.currency || 'BDT'}
                                    options={[
                                        { value: 'BDT', label: 'BDT (Tk)' },
                                        { value: 'USD', label: 'USD ($)' },
                                    ]}
                                    error={errors?.currency}
                                />
                                <TextAreaField
                                    label="Address"
                                    name="address"
                                    defaultValue={form?.address || ''}
                                    className="md:col-span-2"
                                    rows={2}
                                    error={errors?.address}
                                />
                            </div>

                            {recaptcha?.enabled && recaptcha?.site_key ? (
                                <div className="flex justify-center">
                                    <div className="g-recaptcha" data-sitekey={recaptcha.site_key} data-action={recaptcha.action || 'REGISTER'}></div>
                                </div>
                            ) : null}

                            <SubmitButton>Create account</SubmitButton>
                        </form>

                        <p className="mt-6 text-xs text-slate-200/85">
                            Already have an account?{' '}
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
