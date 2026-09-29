import React, { useState, useEffect, useRef } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AlertStack from '../../Components/Flash/AlertStack';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';
import { refreshCsrfToken } from '../../csrf';

const ShieldCheckIcon = ({ className = 'w-10 h-10' }) => (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
    </svg>
);

export default function VerifyOtp({
    pageTitle = 'Security Verification',
    portal = 'web',
    email = '',
    isLocal = false,
    localTempOtp = '123456',
    routes = {},
}) {
    const { errors = {}, flash = {}, branding = {}, csrf_token: csrfToken = '' } = usePage().props;
    const [otp, setOtp] = useState('');
    const [processing, setProcessing] = useState(false);
    const [activeToken, setActiveToken] = useState(csrfToken || '');
    const [resendCooldown, setResendCooldown] = useState(0);
    const inputRef = useRef(null);

    useEffect(() => {
        refreshCsrfToken({ force: true }).then((fresh) => {
            if (fresh) {
                setActiveToken(fresh);
            }
        });
        if (inputRef.current) {
            inputRef.current.focus();
        }
    }, []);

    useEffect(() => {
        if (resendCooldown <= 0) return;
        const interval = setInterval(() => {
            setResendCooldown((prev) => prev - 1);
        }, 1000);
        return () => clearInterval(interval);
    }, [resendCooldown]);

    const handleOtpChange = (e) => {
        const val = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
        setOtp(val);
    };

    const handleUseTempOtp = () => {
        if (localTempOtp) {
            setOtp(localTempOtp);
            if (inputRef.current) {
                inputRef.current.focus();
            }
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (otp.length < 4 || processing) return;

        setProcessing(true);
        router.post(
            routes?.submit || '/login/otp',
            { otp, _token: activeToken },
            {
                onFinish: () => setProcessing(false),
            }
        );
    };

    const handleResend = (e) => {
        e.preventDefault();
        if (resendCooldown > 0 || processing) return;

        setProcessing(true);
        router.post(
            routes?.resend || '/login/otp/resend',
            { _token: activeToken },
            {
                onSuccess: () => setResendCooldown(60),
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <>
            <Head title={pageTitle} />
            <GuestAuthLayout>
                <section className="bg-white border border-slate-200/60 relative overflow-hidden rounded-3xl p-8 text-slate-800 shadow-xl shadow-slate-200/30 sm:p-10">
                    <div className="relative z-10 text-center">
                        <div className="mb-5 flex justify-center">
                            <a href="/" className="flex items-center gap-3" data-native="true">
                                {branding?.logo_url ? (
                                    <img src={branding.logo_url} alt="Company logo" className="h-12 rounded-xl p-1" />
                                ) : (
                                    <div className="text-lg font-bold text-slate-900 tracking-tight">MyApptimatic</div>
                                )}
                            </a>
                        </div>

                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 ring-8 ring-teal-50/50 shadow-inner">
                            <ShieldCheckIcon className="w-8 h-8 text-teal-600" />
                        </div>

                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Two-Factor Authentication</h1>
                        <p className="mt-2 text-xs text-slate-500 leading-relaxed max-w-xs mx-auto">
                            We&apos;ve sent a 6-digit one-time code to
                            {email ? (
                                <span className="block mt-1 font-semibold text-slate-800 bg-slate-100 px-2.5 py-1 rounded-full text-[11px] w-fit mx-auto border border-slate-200">
                                    {email}
                                </span>
                            ) : (
                                ' your registered email address'
                            )}
                        </p>

                        {/* Local development bypass helper banner */}
                        {isLocal && (
                            <div className="mt-5 rounded-2xl border border-amber-300 bg-amber-50/90 p-3.5 text-left text-xs text-amber-900 shadow-sm">
                                <div className="flex items-start gap-2.5">
                                    <span className="text-base leading-none">🛠️</span>
                                    <div className="flex-1">
                                        <div className="font-semibold text-amber-950">Local Development Environment</div>
                                        <p className="mt-0.5 text-[11px] text-amber-800">
                                            Temporary OTP is active: <code className="rounded bg-amber-200/70 px-1.5 py-0.5 font-mono font-bold text-amber-950">{localTempOtp}</code>
                                        </p>
                                        <button
                                            type="button"
                                            onClick={handleUseTempOtp}
                                            className="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white px-2.5 py-1 text-[11px] font-medium transition-colors"
                                        >
                                            Auto-fill Temp OTP ({localTempOtp})
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="mt-5 text-left">
                            <AlertStack
                                status={flash?.status}
                                errors={errors}
                                singleError
                                statusClassName="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-800 text-left"
                                errorClassName="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs text-rose-800 text-left"
                            />
                        </div>

                        <form onSubmit={handleSubmit} className="mt-5 space-y-5">
                            <div>
                                <label htmlFor="otp-input" className="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">
                                    Enter 6-Digit Code
                                </label>
                                <div className="relative">
                                    <input
                                        ref={inputRef}
                                        id="otp-input"
                                        type="text"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        value={otp}
                                        onChange={handleOtpChange}
                                        placeholder="••••••"
                                        className={`w-full rounded-2xl border text-center font-mono text-2xl tracking-[0.5em] py-3.5 px-4 outline-none transition-all shadow-sm ${
                                            errors?.otp
                                                ? 'border-rose-400 bg-rose-50/20 text-rose-900 focus:ring-4 focus:ring-rose-100'
                                                : 'border-slate-300 bg-slate-50/50 text-slate-900 focus:border-teal-500 focus:bg-white focus:ring-4 focus:ring-teal-100'
                                        }`}
                                    />
                                </div>
                                {errors?.otp && (
                                    <p className="mt-2 text-xs text-rose-600 font-medium text-left">{errors.otp}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={otp.length < 4 || processing}
                                className="w-full h-11 rounded-full bg-teal-600 hover:bg-teal-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white text-xs font-semibold tracking-wide transition-all duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg active:scale-[0.98]"
                            >
                                {processing ? (
                                    <div className="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                ) : (
                                    'Verify Code & Sign In'
                                )}
                            </button>
                        </form>

                        <div className="mt-6 flex flex-col items-center gap-3 border-t border-slate-100 pt-5 text-xs text-slate-500">
                            <div className="flex items-center gap-2">
                                <span>Didn&apos;t receive the code?</span>
                                <button
                                    type="button"
                                    onClick={handleResend}
                                    disabled={resendCooldown > 0 || processing}
                                    className="font-semibold text-teal-600 hover:text-teal-700 disabled:text-slate-400 disabled:cursor-not-allowed transition-colors"
                                >
                                    {resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend code'}
                                </button>
                            </div>

                            <a
                                href={routes?.cancel || '/login'}
                                className="text-slate-400 hover:text-slate-600 transition-colors text-[11px]"
                            >
                                Back to login
                            </a>
                        </div>
                    </div>
                </section>
            </GuestAuthLayout>
        </>
    );
}
