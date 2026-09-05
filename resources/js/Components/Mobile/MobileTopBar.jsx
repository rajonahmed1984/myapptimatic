import React from 'react';
import { usePage } from '@inertiajs/react';
import UserDropdown from '../Layout/UserDropdown';

const ROOT_DASHBOARDS = new Set([
    '',
    '/',
    '/admin',
    '/admin/dashboard',
    '/employee',
    '/employee/dashboard',
    '/client',
    '/client/dashboard',
    '/sales',
    '/sales/dashboard',
    '/rep',
    '/rep/dashboard',
    '/support',
    '/support/dashboard',
]);

export function isInnerPagePath(url = '') {
    const clean = String(url || '').split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';
    return !ROOT_DASHBOARDS.has(clean);
}

export default function MobileTopBar({
    title = 'Overview',
    subtitle = '',
    backHref = null,
    onBack = null,
    forwardHref = null,
    onForward = null,
    onOpenMenu = null,
    showNavControls = null,
    actions = null,
    branding = {},
    user = null,
    roleLabel = '',
    profileRoute = '',
    className = '',
}) {
    let page = null;
    try {
        page = usePage();
    } catch (e) {
        page = null;
    }

    const currentUrl = page?.url || (typeof window !== 'undefined' ? window.location.pathname : '');
    const cleanPath = String(currentUrl).split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';

    const isInner = typeof showNavControls === 'boolean'
        ? showNavControls
        : Boolean(backHref || onBack) || isInnerPagePath(cleanPath);

    const handleBack = () => {
        if (typeof onBack === 'function') {
            onBack();
            return;
        }
        if (backHref) {
            window.location.href = backHref;
            return;
        }
        if (typeof window !== 'undefined') {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                const portalFallback = cleanPath.startsWith('/client')
                    ? '/client/dashboard'
                    : cleanPath.startsWith('/rep') || cleanPath.startsWith('/sales')
                    ? '/sales/dashboard'
                    : cleanPath.startsWith('/support')
                    ? '/support/dashboard'
                    : cleanPath.startsWith('/employee')
                    ? '/employee/dashboard'
                    : '/admin/dashboard';
                window.location.href = portalFallback;
            }
        }
    };

    const handleForward = () => {
        if (typeof onForward === 'function') {
            onForward();
            return;
        }
        if (forwardHref) {
            window.location.href = forwardHref;
            return;
        }
        if (typeof window !== 'undefined') {
            window.history.forward();
        }
    };

    return (
        <header
            className={`sticky top-0 z-30 flex h-14 w-full items-center justify-between border-b border-slate-200/80 bg-white/95 px-2.5 backdrop-blur-md transition-all md:hidden safe-area-top shadow-sm ${className}`}
        >
            <div className="flex min-w-0 items-center gap-1.5 flex-1 pr-1.5">
                {/* 1. Sidebar Toggle Button */}
                {onOpenMenu ? (
                    <button
                        type="button"
                        onClick={onOpenMenu}
                        className="mobile-sidebar-toggle inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 shadow-2xs transition active:scale-95 cursor-pointer flex-shrink-0"
                        aria-label="Open menu"
                        title="Toggle menu"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            className="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2.2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
                            <rect width="18" height="18" x="3" y="3" rx="3" />
                            <path d="M9 3v18" />
                        </svg>
                    </button>
                ) : branding?.logo_url ? (
                    <img src={branding.logo_url} alt="Brand" className="h-8 w-8 rounded-lg object-contain" />
                ) : null}

                {/* 2. Dynamic Back & Forward Navigation Controls (Only for Inner Pages) */}
                {isInner && (
                    <div className="inline-flex items-center gap-1 shrink-0">
                        <button
                            type="button"
                            onClick={handleBack}
                            className="mobile-nav-btn inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 shadow-2xs transition hover:bg-slate-50 hover:border-teal-300 hover:text-teal-600 active:scale-95 cursor-pointer flex-shrink-0"
                            aria-label="Go back"
                            title="Go back"
                        >
                            <svg className="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.3">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            onClick={handleForward}
                            className="mobile-nav-btn inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 shadow-2xs transition hover:bg-slate-50 hover:border-teal-300 hover:text-teal-600 active:scale-95 cursor-pointer flex-shrink-0"
                            aria-label="Go forward"
                            title="Go forward"
                        >
                            <svg className="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.3">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                )}

                {/* 3. Title & Subtitle */}
                <div className="min-w-0 flex-1 pl-1">
                    <h1 className="truncate text-sm sm:text-base font-bold text-slate-900 leading-tight">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="truncate text-[10px] sm:text-[11px] font-medium text-slate-500 leading-none mt-0.5">
                            {subtitle}
                        </p>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-1.5 shrink-0">
                {actions}

                {user && (
                    <UserDropdown user={user} roleLabel={roleLabel} profileRoute={profileRoute} />
                )}
            </div>
        </header>
    );
}
