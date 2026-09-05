import React from 'react';
import UserDropdown from '../Layout/UserDropdown';

export default function MobileTopBar({
    title = 'Overview',
    subtitle = '',
    backHref = null,
    onBack = null,
    onOpenMenu = null,
    actions = null,
    branding = {},
    user = null,
    roleLabel = '',
    profileRoute = '',
    className = '',
}) {
    const handleBack = () => {
        if (typeof onBack === 'function') {
            onBack();
            return;
        }
        if (backHref) {
            window.location.href = backHref;
            return;
        }
        if (window.history.length > 1) {
            window.history.back();
        }
    };

    const hasBackAction = Boolean(backHref || onBack);

    return (
        <header
            className={`sticky top-0 z-30 flex h-14 w-full items-center justify-between border-b border-slate-200/80 bg-white/95 px-3 backdrop-blur-md transition-all md:hidden safe-area-top shadow-sm ${className}`}
        >
            <div className="flex min-w-0 items-center gap-2.5">
                {hasBackAction ? (
                    <button
                        type="button"
                        onClick={handleBack}
                        className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition active:scale-95"
                        aria-label="Go back"
                    >
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                ) : onOpenMenu ? (
                    <button
                        type="button"
                        onClick={onOpenMenu}
                        className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition active:scale-95"
                        aria-label="Open menu"
                    >
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                ) : branding?.logo_url ? (
                    <img src={branding.logo_url} alt="Brand" className="h-8 w-8 rounded-lg object-contain" />
                ) : null}

                <div className="min-w-0 flex-1 pr-1">
                    <h1 className="truncate text-base font-bold text-slate-900 leading-tight">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="truncate text-[11px] font-medium text-slate-500 leading-none mt-0.5">
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
