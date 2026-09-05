import React from 'react';
import { usePage } from '@inertiajs/react';

export default function PublicLayout({ children, title, pageHeading }) {
    const page = usePage();
    const { auth, branding, flash } = page.props;

    const user = auth?.user;
    const companyName = branding?.company_name || 'Apptimatic';
    const logoUrl = branding?.logo_url;

    let dashboardUrl = '/client/dashboard';
    if (user?.role === 'admin' || user?.role === 'master_admin' || user?.role === 'sub_admin') {
        dashboardUrl = '/admin/dashboard';
    } else if (user?.role === 'employee') {
        dashboardUrl = '/employee/dashboard';
    } else if (user?.role === 'sales_rep') {
        dashboardUrl = '/sales/dashboard';
    } else if (user?.role === 'support') {
        dashboardUrl = '/support/dashboard';
    }

    return (
        <div className="min-h-screen bg-guest px-6 py-12">
            <div className="mx-auto max-w-6xl">
                <header className="mb-8 flex flex-wrap items-center justify-between border-b pb-4 gap-4">
                    <a href="/" data-native="true" className="flex items-center gap-3">
                        {logoUrl ? (
                            <img src={logoUrl} alt={companyName} className="h-12 rounded-xl p-1" />
                        ) : (
                            <div className="grid h-12 w-12 place-items-center rounded-xl bg-white/20 text-lg font-semibold text-white">
                                {companyName}
                            </div>
                        )}
                    </a>
                    <div className="text-sm text-slate-600">
                        {user ? (
                            <a href={dashboardUrl} data-native="true" className="text-teal-600 hover:text-teal-500 font-semibold">
                                Go to dashboard
                            </a>
                        ) : (
                            <>
                                <a href="/login" data-native="true" className="text-teal-600 hover:text-teal-500 font-semibold">
                                    Sign in
                                </a>
                                <span className="mx-2 text-slate-300">|</span>
                                <a href="/register" data-native="true" className="text-teal-600 hover:text-teal-500 font-semibold">
                                    Register
                                </a>
                            </>
                        )}
                    </div>
                </header>

                <div className="mb-6">
                    <div className="text-lg font-semibold text-slate-900" data-current-page-title>
                        {pageHeading || title || 'Overview'}
                    </div>
                </div>

                {flash?.error && (
                    <div className="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}
                {flash?.status && (
                    <div className="mb-6 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-700">
                        {flash.status}
                    </div>
                )}

                <main>{children}</main>

                <footer className="mt-10 text-center text-xs text-slate-500">
                    Copyright © {new Date().getFullYear()} <a href="https://apptimatic.com" target="_blank" rel="noreferrer" className="font-semibold text-teal-600 hover:text-teal-500">Apptimatic</a>. All Rights Reserved.
                </footer>
            </div>
        </div>
    );
}
