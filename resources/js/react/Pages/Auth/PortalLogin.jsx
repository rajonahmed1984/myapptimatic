import React, { useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import SubmitButton from '../../Components/Form/SubmitButton';
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

export default function PortalLogin({ pageTitle = 'Sign In', portal = 'web', form = {}, routes = {}, hint = null, recaptcha = {} }) {
    const { errors = {}, flash = {}, csrf_token: csrfToken = '' } = usePage().props;

    useEffect(() => {
        if (recaptcha?.enabled && recaptcha?.site_key) {
            loadRecaptcha();
        }
    }, [recaptcha]);

    return (
        <>
            <Head title={pageTitle} />
            <GuestAuthLayout>
                <section className="relative -m-8 overflow-hidden rounded-2xl bg-slate-900 px-8 py-10 text-white sm:px-10">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(45,212,191,0.35),transparent_45%),radial-gradient(circle_at_80%_75%,rgba(59,130,246,0.26),transparent_44%)]"></div>
                    <div className="relative z-10">
                        <AlertStack status={flash?.status} errors={errors} singleError />
                        <p className="text-xs font-semibold uppercase tracking-[0.36em] text-teal-200/90">Welcome Back</p>

                        <form className="mt-8 space-y-5" method="POST" action={routes?.submit || '/login'} data-native="true">
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

                            <div className="flex items-center justify-between text-sm text-slate-200/85">
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

                            {recaptcha?.enabled && recaptcha?.site_key ? (
                                <div className="flex justify-center">
                                    <div
                                        className="g-recaptcha"
                                        data-sitekey={recaptcha.site_key}
                                        data-action={recaptcha.action || 'LOGIN'}
                                    ></div>
                                </div>
                            ) : null}

                            <SubmitButton>Sign in</SubmitButton>
                        </form>

                        {hint?.href && hint?.text ? (
                            <p className="mt-6 text-xs text-slate-200/85">
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
