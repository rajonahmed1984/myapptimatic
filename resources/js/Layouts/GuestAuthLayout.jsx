import React from 'react';
import { PWA_INSTALL_PATH, isNativeShell, isStandalone } from '../utils/pwa';

export default function GuestAuthLayout({ children, wide = false }) {
    const showInstallLink = !isNativeShell() && !isStandalone() && window.location.pathname !== PWA_INSTALL_PATH;

    return (
        <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-6 md:py-8">
            <div className="flex flex-1 items-center justify-center">
                <div className={`w-[90%] max-w-[25.2rem] ${wide ? 'md:max-w-[45rem]' : ''}`}>
                    {children}
                </div>
            </div>

            {showInstallLink && (
                <div className="mt-6 text-center">
                    <a href={PWA_INSTALL_PATH} className="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-600 hover:text-teal-500">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="7" y="2" width="10" height="20" rx="2" />
                            <path strokeLinecap="round" d="M11 18h2" />
                        </svg>
                        Install the app on your phone
                    </a>
                </div>
            )}

            <div className="mt-3 text-center text-xs text-slate-500">
                By continuing, you agree to <b>Apptimatic&apos;s</b> {' '}
                <a href="https://apptimatic.com/terms" className="font-semibold text-teal-600 hover:text-teal-500">
                    Terms of Service
                </a>{' '}
                and{' '}
                <a href="https://apptimatic.com/privacy" className="font-semibold text-teal-600 hover:text-teal-500">
                    Privacy Policy
                </a>
                .
            </div>
        </div>
    );
}
