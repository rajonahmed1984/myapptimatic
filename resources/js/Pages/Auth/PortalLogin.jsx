import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import InputField from '../../Components/Form/InputField';
import RecaptchaField from '../../Components/Form/RecaptchaField';
import SubmitButton from '../../Components/Form/SubmitButton';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

// SVGs
const EmailIcon = ({ className = "w-5 h-5" }) => (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
);

const GoogleIcon = () => (
    <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none">
        <path
            fill="#EA4335"
            d="M12.24 10.285V14.4h6.887c-.275 1.565-1.88 4.604-6.887 4.604-4.33 0-7.859-3.579-7.859-8s3.53-8 7.859-8c2.46 0 4.105 1.025 5.047 1.926l3.227-3.103C18.28 1.845 15.547 1 12.24 1 6.033 1 1 6.033 1 12.24s5.033 11.24 11.24 11.24c6.478 0 10.793-4.537 10.793-10.985 0-.742-.08-1.305-.177-1.885H12.24z"
        />
    </svg>
);

const MicrosoftIcon = () => (
    <svg className="w-5 h-5" viewBox="0 0 23 23">
        <path fill="#f35022" d="M1 1h10v10H1z" />
        <path fill="#80bb0a" d="M12 1h10v10H12z" />
        <path fill="#00a1f1" d="M1 12h10v10H1z" />
        <path fill="#fca103" d="M12 12h10v10H12z" />
    </svg>
);

const AppleIcon = () => (
    <svg className="w-5 h-5 text-black fill-current" viewBox="0 0 24 24">
        <path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.54 9.103 1.51 12.06 1.005 1.45 2.19 3.078 3.766 3.02 1.514-.064 2.09-.975 3.916-.975s2.348.975 3.93.94c1.61-.03 2.65-1.477 3.637-2.91 1.135-1.66 1.603-3.266 1.632-3.347-.03-.015-3.13-1.2-3.163-4.757-.03-2.975 2.435-4.402 2.547-4.472-1.393-2.04-3.53-2.27-4.288-2.322-1.99-.162-3.882 1.212-4.969 1.212zM15.96 3.69c.816-.99 1.36-2.37 1.21-3.69-1.13.045-2.5 0.75-3.31 1.7-0.7 0.81-1.31 2.2-1.14 3.5 1.26.1 2.53-.6 3.24-1.51z" />
    </svg>
);

export default function PortalLogin({ pageTitle = 'Sign In', portal = 'web', form = {}, routes = {}, hint = null, recaptcha = {} }) {
    const { errors = {}, flash = {}, branding = {}, csrf_token: csrfToken = '' } = usePage().props;

    const hasErrors = Object.keys(errors).length > 0;
    const hasPrefilledEmail = Boolean(form?.email);
    const [showEmailForm, setShowEmailForm] = React.useState(hasErrors || hasPrefilledEmail);
    const [socialNotice, setSocialNotice] = React.useState(null);

    const handleSocialClick = (provider) => {
        setSocialNotice(`${provider} login is currently unavailable.`);
        setTimeout(() => setSocialNotice(null), 4000);
    };

    return (
        <>
            <Head title={pageTitle} />
            <GuestAuthLayout>
                <section className="bg-white border border-slate-200/60 relative overflow-hidden rounded-3xl p-8 text-slate-800 shadow-xl shadow-slate-200/30 sm:p-10">
                    <div className="relative z-10 text-center">
                        <div className="mb-6 flex justify-center">
                            <a href="/" className="flex items-center gap-3" data-native="true">
                                {branding?.logo_url ? (
                                    <img src={branding.logo_url} alt="Company logo" className="h-12 rounded-xl p-1" />
                                ) : (
                                    <div className="text-lg font-bold text-slate-900 tracking-tight">MyApptimatic</div>
                                )}
                            </a>
                        </div>

                        {socialNotice && (
                            <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800 text-left flex items-center gap-2">
                                <svg className="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{socialNotice}</span>
                            </div>
                        )}

                        <AlertStack
                            status={flash?.status}
                            errors={errors}
                            singleError
                            statusClassName="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 text-left"
                            errorClassName="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 text-left"
                        />

                        {!showEmailForm ? (
                            <div className="space-y-6">
                                <div className="text-center">
                                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Log into your account</h1>
                                    <p className="mt-1.5 text-xs text-slate-500">Choose a method to continue to {pageTitle === 'Sign In' ? 'client portal' : pageTitle.toLowerCase()}</p>
                                </div>

                                <div className="space-y-3 pt-2">
                                    <button
                                        onClick={() => setShowEmailForm(true)}
                                        className="w-full h-11 rounded-full bg-slate-900 hover:bg-black text-white text-xs font-semibold tracking-wide transition-all duration-200 flex items-center justify-center gap-3 shadow-md hover:shadow-lg active:scale-[0.98]"
                                    >
                                        <EmailIcon className="w-4 h-4 text-white" />
                                        Login with email
                                    </button>

                                    <div className="relative py-2">
                                        <div className="absolute inset-0 flex items-center" aria-hidden="true">
                                            <div className="w-full border-t border-slate-100"></div>
                                        </div>
                                    </div>

                                    <button
                                        onClick={() => handleSocialClick('Google')}
                                        className="w-full h-11 rounded-full bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-800 text-xs font-semibold tracking-wide transition-all duration-200 flex items-center justify-center gap-3 shadow-sm active:scale-[0.98]"
                                    >
                                        <GoogleIcon />
                                        Login with Google
                                    </button>

                                    <button
                                        onClick={() => handleSocialClick('Microsoft')}
                                        className="w-full h-11 rounded-full bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-800 text-xs font-semibold tracking-wide transition-all duration-200 flex items-center justify-center gap-3 shadow-sm active:scale-[0.98]"
                                    >
                                        <MicrosoftIcon />
                                        Login with Microsoft
                                    </button>

                                    <button
                                        onClick={() => handleSocialClick('Apple')}
                                        className="w-full h-11 rounded-full bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-800 text-xs font-semibold tracking-wide transition-all duration-200 flex items-center justify-center gap-3 shadow-sm active:scale-[0.98]"
                                    >
                                        <AppleIcon />
                                        Login with Apple
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-6 animate-fade-in">
                                <div className="text-center">
                                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Log in with email</h1>
                                    <p className="mt-1.5 text-xs text-slate-500">Enter your credentials to continue</p>
                                </div>

                                <form className="space-y-4 text-left" method="POST" action={routes?.submit || '/login'} data-native="true">
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
                                        inputClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full"
                                    />

                                    <InputField
                                        name="password"
                                        type="password"
                                        placeholder="Password"
                                        required
                                        error={errors?.password}
                                        inputClassName="h-10 text-xs border-slate-200 focus:ring-teal-600 focus:border-teal-600 rounded-full"
                                    />

                                    <div className="flex items-center justify-between text-xs text-slate-600">
                                        <label className="flex items-center gap-2 cursor-pointer select-none">
                                            <input
                                                type="checkbox"
                                                name="remember"
                                                value="1"
                                                defaultChecked={Boolean(form?.remember)}
                                                className="rounded border-slate-300 text-teal-600 focus:ring-teal-500/20"
                                            />
                                            Remember me
                                        </label>
                                        {routes?.forgot ? (
                                            <a href={routes.forgot} className="font-semibold text-teal-600 hover:text-teal-500" data-native="true">
                                                Forgot password?
                                            </a>
                                        ) : null}
                                    </div>

                                    <RecaptchaField
                                        enabled={Boolean(recaptcha?.enabled)}
                                        siteKey={recaptcha?.site_key || ''}
                                        action={recaptcha?.action || 'LOGIN'}
                                    />

                                    <div className="pt-2 flex flex-col gap-3">
                                        <SubmitButton className="h-10 rounded-full bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold tracking-wide transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg active:scale-[0.98]">
                                            Sign in
                                        </SubmitButton>
                                        
                                        <button
                                            type="button"
                                            onClick={() => setShowEmailForm(false)}
                                            className="w-full h-10 rounded-full bg-transparent hover:bg-slate-50 border border-slate-200 hover:border-slate-300 text-slate-600 text-xs font-semibold transition-all duration-200 flex items-center justify-center gap-2 active:scale-[0.98]"
                                        >
                                            <svg className="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                                            </svg>
                                            Other sign-in options
                                        </button>
                                    </div>
                                </form>
                            </div>
                        )}

                        {hint?.href && hint?.text ? (
                            <p className="mt-8 text-center text-xs text-slate-500">
                                {hint?.label ? `${hint.label} ` : ''}
                                <a href={hint.href} className="font-semibold text-teal-600 hover:text-teal-500" data-native="true">
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
