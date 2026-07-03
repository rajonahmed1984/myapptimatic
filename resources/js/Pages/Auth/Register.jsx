import React, { useEffect, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/build/css/intlTelInput.css';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import RecaptchaField from '../../Components/Form/RecaptchaField';
import SelectField from '../../Components/Form/SelectField';
import SubmitButton from '../../Components/Form/SubmitButton';
import TextAreaField from '../../Components/Form/TextAreaField';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

export default function Register({ form = {}, routes = {}, recaptcha = {} }) {
    const { errors = {}, flash = {}, branding = {} } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [tosError, setTosError] = useState('');

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

    useEffect(() => {
        const phoneInput = document.getElementById('phone');
        const phoneCountryInput = document.getElementById('phone_country');
        if (!(phoneInput instanceof HTMLInputElement) || !(phoneCountryInput instanceof HTMLInputElement)) {
            return undefined;
        }

        let itiInstance = null;
        let countryChangeHandler = null;
        let submitHandler = null;
        let cancelled = false;

        const phoneCountryToIso2 = {
            '+880': 'bd',
            '+1': 'us',
            '+44': 'gb',
            '+91': 'in',
            '+92': 'pk',
        };

        if (cancelled || typeof intlTelInput !== 'function') {
            return undefined;
        }

        itiInstance = intlTelInput(phoneInput, {
            initialCountry: 'bd',
            preferredCountries: ['bd', 'us', 'gb'],
            separateDialCode: true,
            nationalMode: true,
            useFullscreenPopup: false,
            countrySearch: true,
        });

        const wantedCountry = phoneCountryToIso2[String(form?.phone_country || '')] || 'bd';
        if (typeof itiInstance.setCountry === 'function') {
            itiInstance.setCountry(wantedCountry);
        }

        if (typeof itiInstance.getSelectedCountryData === 'function') {
            const dialCode = itiInstance.getSelectedCountryData()?.dialCode;
            if (dialCode) {
                phoneCountryInput.value = `+${dialCode}`;
            }
        }

        countryChangeHandler = () => {
            const dialCode = itiInstance?.getSelectedCountryData?.()?.dialCode;
            phoneCountryInput.value = dialCode ? `+${dialCode}` : '+880';
        };
        phoneInput.addEventListener('countrychange', countryChangeHandler);

        const formElement = phoneInput.closest('form');
        if (formElement instanceof HTMLFormElement) {
            submitHandler = () => {
                const dialCode = itiInstance?.getSelectedCountryData?.()?.dialCode;
                phoneCountryInput.value = dialCode ? `+${dialCode}` : '+880';
            };
            formElement.addEventListener('submit', submitHandler);
        }

        return () => {
            cancelled = true;
            if (countryChangeHandler) {
                phoneInput.removeEventListener('countrychange', countryChangeHandler);
            }
            const formElement = phoneInput.closest('form');
            if (formElement instanceof HTMLFormElement && submitHandler) {
                formElement.removeEventListener('submit', submitHandler);
            }
            if (itiInstance?.destroy) {
                itiInstance.destroy();
            }
        };
    }, [form?.phone_country]);

    return (
        <>
            <Head title="Create Account" />
            <GuestAuthLayout wide>
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

                        <AlertStack
                            status={flash?.status}
                            errors={errors}
                            singleError
                            statusClassName="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 text-left"
                            errorClassName="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 text-left"
                        />

                        <div className="text-center mb-6">
                            <p className="text-xs font-semibold uppercase tracking-[0.36em] text-teal-600">Welcome Back</p>
                            <h1 className="mt-2 text-2xl font-bold text-slate-900 tracking-tight">Register</h1>
                            <p className="mt-2 text-xs text-slate-500">
                                Already have an account?{' '}
                                <a href={routes.login || '/login'} className="font-semibold text-teal-600 hover:text-teal-500" data-native="true">
                                    Sign in
                                </a>
                                .
                            </p>
                        </div>

                        <form
                            className="space-y-5"
                            method="POST"
                            action={routes.submit}
                            data-native="true"
                            onSubmit={(event) => {
                                const formElement = event.currentTarget;
                                const checkbox = formElement.querySelector('input[name="accepttos"]');
                                const isChecked = checkbox instanceof HTMLInputElement ? checkbox.checked : false;
                                if (!isChecked) {
                                    event.preventDefault();
                                    setTosError('Please accept the Terms of Service to continue registration.');
                                    return;
                                }
                                setTosError('');
                            }}
                        >
                            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                            {form?.redirect ? <input type="hidden" name="redirect" value={form.redirect} /> : null}

                            <div className="grid gap-4 md:grid-cols-2">
                                <InputField
                                    name="name"
                                    defaultValue={form?.name || ''}
                                    placeholder="Full name"
                                    required
                                    error={errors?.name}
                                    inputClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full"
                                />
                                <InputField
                                    name="company_name"
                                    defaultValue={form?.company_name || ''}
                                    placeholder="Company name"
                                    error={errors?.company_name}
                                    inputClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full"
                                />
                                <InputField
                                    name="email"
                                    type="email"
                                    defaultValue={form?.email || ''}
                                    placeholder="Email"
                                    required
                                    error={errors?.email}
                                    inputClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full"
                                />
                                <div className="register-phone-field">
                                    <input id="phone_country" type="hidden" name="phone_country" defaultValue={form?.phone_country || '+880'} />
                                    <input
                                        id="phone"
                                        type="tel"
                                        name="phone"
                                        defaultValue={form?.phone || ''}
                                        placeholder="Mobile number"
                                        autoComplete="off"
                                        className="mt-2 w-full h-10 rounded-full border border-slate-200 bg-white px-4 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-600"
                                    />
                                    {errors?.phone ? <p className="mt-1 text-xs text-rose-600">{errors.phone}</p> : null}
                                </div>
                                <div className="relative">
                                    <InputField
                                        name="password"
                                        type={showPassword ? 'text' : 'password'}
                                        placeholder="Password"
                                        required
                                        autoComplete="new-password"
                                        error={errors?.password}
                                        onChange={(event) => setPassword(event.target.value)}
                                        inputClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full pr-12"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((current) => !current)}
                                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        className="absolute right-3 top-[60%] -translate-y-1/2 inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:text-slate-600 focus:outline-none"
                                    >
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                            <path d="M1.5 12s4.5-7.5 10.5-7.5S22.5 12 22.5 12 18 19.5 12 19.5 1.5 12 1.5 12z" />
                                            <circle cx="12" cy="12" r="3.2" />
                                            {showPassword ? <path d="M3 21L21 3" /> : null}
                                        </svg>
                                    </button>
                                </div>
                                <div className="relative">
                                    <InputField
                                        name="password_confirmation"
                                        type={showPasswordConfirmation ? 'text' : 'password'}
                                        placeholder="Confirm password"
                                        required
                                        autoComplete="new-password"
                                        error={errors?.password_confirmation}
                                        onChange={(event) => setPasswordConfirmation(event.target.value)}
                                        inputClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full pr-12"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPasswordConfirmation((current) => !current)}
                                        aria-label={showPasswordConfirmation ? 'Hide confirm password' : 'Show confirm password'}
                                        className="absolute right-3 top-[60%] -translate-y-1/2 inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:text-slate-600 focus:outline-none"
                                    >
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                            <path d="M1.5 12s4.5-7.5 10.5-7.5S22.5 12 22.5 12 18 19.5 12 19.5 1.5 12 1.5 12z" />
                                            <circle cx="12" cy="12" r="3.2" />
                                            {showPasswordConfirmation ? <path d="M3 21L21 3" /> : null}
                                        </svg>
                                    </button>
                                </div>
                                {passwordMatchMessage ? (
                                    <p className={`${passwordMatchMessage.className.replace('text-emerald-300', 'text-emerald-600').replace('text-rose-300', 'text-rose-600')} md:col-span-2`}>{passwordMatchMessage.text}</p>
                                ) : null}
                                <SelectField
                                    label="Currency"
                                    name="currency"
                                    defaultValue={form?.currency || 'BDT'}
                                    options={[
                                        { value: 'BDT', label: 'BDT (Tk)' },
                                        { value: 'USD', label: 'USD ($)' },
                                    ]}
                                    error={errors?.currency}
                                    selectClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full"
                                />

                                <TextAreaField
                                    label="Address"
                                    name="address"
                                    defaultValue={form?.address || ''}
                                    className="md:col-span-2"
                                    rows={1}
                                    error={errors?.address}
                                    textAreaClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full py-2.5"
                                />
                            </div>
                            <RecaptchaField
                                enabled={Boolean(recaptcha?.enabled)}
                                siteKey={recaptcha?.site_key || ''}
                                action={recaptcha?.action || 'REGISTER'}
                            />

                            <div className="pt-2 flex justify-center">
                                <SubmitButton className="h-10 rounded-full bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold tracking-wide transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg active:scale-[0.98] max-w-xs">
                                    Create account
                                </SubmitButton>
                            </div>
                        </form>
                    </div>
                </section>
            </GuestAuthLayout>
        </>
    );
}
