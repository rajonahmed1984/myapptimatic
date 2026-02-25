import React from 'react';
import { usePage } from '@inertiajs/react';

export default function GuestAuthLayout({ children, wide = false }) {
    const { branding = {}, routes = {} } = usePage().props;
    const companyName = branding?.company_name || 'MyApptimatic';
    const homeUrl = routes?.home || '/';
    const loginUrl = routes?.login || '/login';
    const registerUrl = routes?.register || '/register';

    return (
        <>
            <div className="mb-8 flex flex-wrap items-center justify-between gap-4 border-b py-2">
                <a href={homeUrl} className="flex items-center gap-3" data-native="true">
                    {branding?.logo_url ? (
                        <img src={branding.logo_url} alt="Company logo" className="h-12 rounded-xl p-1" />
                    ) : (
                        <div className="grid h-12 w-12 place-items-center rounded-xl bg-white/20 text-sm font-semibold text-white">
                            {companyName}
                        </div>
                    )}
                </a>
                <div className="text-sm text-slate-600">
                    <a href={loginUrl} className="text-teal-600 hover:text-teal-500" data-native="true">
                        Sign in
                    </a>
                    <span className="mx-2 text-slate-300">|</span>
                    <a href={registerUrl} className="text-teal-600 hover:text-teal-500" data-native="true">
                        Register
                    </a>
                </div>
            </div>

            <div className={`mx-auto w-full max-w-md ${wide ? 'md:max-w-[50rem]' : ''}`}>
                <div className="card overflow-hidden p-8">{children}</div>
                <div className="mt-6 text-center text-xs text-slate-500">
                    Copyright &copy; {new Date().getFullYear()}{' '}
                    <a href="https://apptimatic.com" className="font-semibold text-teal-600 hover:text-teal-500">
                        Apptimatic
                    </a>
                    . All Rights Reserved.
                </div>
            </div>
        </>
    );
}
