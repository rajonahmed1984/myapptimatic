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
                        <AlertStack status={flash?.status} errors={errors} singleError />
                        <p className="text-xs font-semibold uppercase tracking-[0.36em] text-teal-200/90">Welcome Back</p>
                        <p className="mt-3 text-xs font-semibold uppercase tracking-[0.32em] text-slate-300/80">Register</p>

                        <form
                            className="mt-6 space-y-5"
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

                            <div className="grid gap-5 md:grid-cols-2">
                                <InputField name="name" defaultValue={form?.name || ''} placeholder="Full name" required error={errors?.name} />
                                <InputField
                                    name="company_name"
                                    defaultValue={form?.company_name || ''}
                                    placeholder="Company name"
                                    error={errors?.company_name}
                                />
                                <InputField
                                    name="email"
                                    type="email"
                                    defaultValue={form?.email || ''}
                                    placeholder="Email"
                                    required
                                    error={errors?.email}
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
                                        className="mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200"
                                    />
                                    {errors?.phone ? <p className="mt-1 text-xs text-rose-300">{errors.phone}</p> : null}
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
                                        inputClassName="pr-12"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((current) => !current)}
                                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        className="absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-teal-300"
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
                                        inputClassName="pr-12"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPasswordConfirmation((current) => !current)}
                                        aria-label={showPasswordConfirmation ? 'Hide confirm password' : 'Show confirm password'}
                                        className="absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-teal-300"
                                    >
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                            <path d="M1.5 12s4.5-7.5 10.5-7.5S22.5 12 22.5 12 18 19.5 12 19.5 1.5 12 1.5 12z" />
                                            <circle cx="12" cy="12" r="3.2" />
                                            {showPasswordConfirmation ? <path d="M3 21L21 3" /> : null}
                                        </svg>
                                    </button>
                                </div>
                                {passwordMatchMessage ? (
                                    <p className={`${passwordMatchMessage.className} md:col-span-2`}>{passwordMatchMessage.text}</p>
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
                                />
                                <div className="md:self-end md:pb-3">
                                    <label className="inline-flex cursor-pointer items-start gap-2 text-xs text-slate-200/90">
                                        <input
                                            type="checkbox"
                                            name="accepttos"
                                            value="1"
                                            required
                                            defaultChecked={Boolean(form?.accepttos)}
                                            onChange={(event) => {
                                                if (event.target.checked) {
                                                    setTosError('');
                                                }
                                            }}
                                            className="accepttos mt-0.5 rounded border-white/30 text-teal-500 focus:ring-teal-200"
                                        />
                                        <span>
                                            I have read and agree to the{' '}
                                            <a
                                                href="https://carrothost.com/terms-conditions/"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="font-semibold text-teal-300 hover:text-teal-200"
                                            >
                                                Terms of Service
                                            </a>
                                        </span>
                                    </label>
                                    {tosError ? <p className="mt-1 text-xs text-rose-300">{tosError}</p> : null}
                                    {!tosError && errors?.accepttos ? <p className="mt-1 text-xs text-rose-300">{errors.accepttos}</p> : null}
                                </div>
                                <TextAreaField
                                    label="Address"
                                    name="address"
                                    defaultValue={form?.address || ''}
                                    className="md:col-span-2"
                                    rows={2}
                                    error={errors?.address}
                                />
                            </div>

                            <RecaptchaField
                                enabled={Boolean(recaptcha?.enabled)}
                                siteKey={recaptcha?.site_key || ''}
                                action={recaptcha?.action || 'REGISTER'}
                            />

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
