import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import SidebarToggle from '../Layout/SidebarToggle';
import UserDropdown from '../Layout/UserDropdown';
import ImpersonationBanner from '../Layout/ImpersonationBanner';
import MobileTopBar, { isInnerPagePath } from './MobileTopBar';
import MobileBottomNav from './MobileBottomNav';
import MobileBottomSheet from './MobileBottomSheet';

function HeaderStat({ href, count, label, tone }) {
    const value = Number(count || 0);
    const badgeClass = value > 0 ? tone : 'bg-slate-100 text-slate-400 border-slate-200';

    return (
        <a
            href={href}
            data-native="true"
            className={`flex items-center gap-2 font-medium transition-colors ${
                value > 0 ? 'text-slate-600 hover:text-slate-900' : 'text-slate-400 hover:text-slate-600'
            }`}
        >
            <span className={`min-w-[24px] h-6 px-1.5 rounded-full border flex items-center justify-center font-bold text-[11px] leading-none tabular-nums ${badgeClass}`}>
                {value}
            </span>
            <span>{label}</span>
        </a>
    );
}

/**
 * Shared chrome for every authenticated portal (Admin, Employee, Client, Sales Rep, Support):
 * mobile overlay + slide-in sidebar, sticky mobile top bar, desktop header with
 * back/forward + optional header stats + user menu, flash messages, mobile bottom
 * nav, and an optional "More" bottom sheet. Each portal layout supplies its own
 * sidebar nav tree (sidebarContent) and nav-item/more-section config; everything
 * else lives here once instead of being duplicated per portal.
 */
export default function MobileAppShell({
    portalKey = 'portal',
    portalLabel = '',
    companyName = 'License Portal',
    logoUrl = null,
    brandInitials = 'LM',
    sidebarContent = null,
    sidebarExtra = null,
    title = null,
    pageHeading = null,
    user = null,
    roleLabel = '',
    profileRoute = '',
    branding = {},
    navItems = [],
    headerStats = [],
    moreSections = [],
    moreTitle = 'Menu & Features',
    moreDescription = null,
    children,
}) {
    const page = usePage();
    const { flash } = page.props || {};
    const currentUrl = page.url || (typeof window !== 'undefined' ? window.location.pathname : '');

    const [mobileOpen, setMobileOpen] = useState(false);
    const [isMoreSheetOpen, setIsMoreSheetOpen] = useState(false);

    const resolvedTitle = pageHeading || title || page.props?.pageHeading || page.props?.pageTitle || page.props?.project?.name || 'Overview';
    const resolvedMoreDescription = moreDescription || `Explore all ${roleLabel || 'system'} tools`;

    // "More" tab is wired to open the bottom sheet only when the layout actually has sections to show.
    const resolvedNavItems = navItems.map((item) =>
        item.isMore && moreSections.length > 0
            ? { ...item, onClick: () => setIsMoreSheetOpen(true), active: item.active || isMoreSheetOpen }
            : item
    );

    return (
        <div className="min-h-screen flex flex-col md:flex-row bg-dashboard">
            {/* Mobile Overlay */}
            <div
                id={`${portalKey}SidebarOverlay`}
                onClick={() => setMobileOpen(false)}
                className={`fixed inset-0 z-20 bg-slate-900/60 transition-opacity duration-200 md:hidden ${
                    mobileOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
                }`}
            />

            {/* Sidebar */}
            <aside
                id={`${portalKey}Sidebar`}
                className={`sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out md:w-64 md:max-w-none md:translate-x-0 md:sticky md:top-0 ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex items-center gap-3">
                    {logoUrl ? (
                        <img src={logoUrl} alt="Brand mark" className="h-11 w-11 rounded-2xl bg-white p-1 object-contain" />
                    ) : (
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">
                            {brandInitials}
                        </div>
                    )}
                    <div className="min-w-0">
                        <div className="text-xs uppercase tracking-[0.35em] text-slate-400">{portalLabel}</div>
                        <div className="text-lg font-semibold text-white truncate">{companyName}</div>
                    </div>
                </div>

                {sidebarExtra}

                <button
                    type="button"
                    onClick={() => setMobileOpen(false)}
                    className="absolute right-4 top-4 rounded-full border border-white/10 bg-white/10 p-2 text-slate-200 transition hover:bg-white/20 md:hidden cursor-pointer"
                    aria-label="Close menu"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <nav className="mt-8 space-y-4 text-sm flex-1">{sidebarContent}</nav>
            </aside>

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col w-full min-w-0">
                {/* Mobile Top App Bar (<md) */}
                <MobileTopBar
                    title={resolvedTitle}
                    user={user}
                    roleLabel={roleLabel}
                    profileRoute={profileRoute}
                    branding={branding}
                    onOpenMenu={() => setMobileOpen(true)}
                />

                {/* Desktop Sticky Header (>=md) */}
                <header className="hidden md:block sticky top-0 z-20 border-b border-slate-300/70 bg-white/80 backdrop-blur">
                    <div className="flex w-full items-center justify-between gap-6 px-6 py-4">
                        <div className="flex items-center gap-3 min-w-[260px]">
                            <SidebarToggle onToggleMobile={() => setMobileOpen(!mobileOpen)} />
                            {isInnerPagePath(currentUrl) && (
                                <div className="flex items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={() => window.history.back()}
                                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-teal-600 hover:border-teal-300 transition active:scale-95 cursor-pointer"
                                        aria-label="Go back"
                                        title="Go back"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => window.history.forward()}
                                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-teal-600 hover:border-teal-300 transition active:scale-95 cursor-pointer"
                                        aria-label="Go forward"
                                        title="Go forward"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                </div>
                            )}
                            <div>
                                <div className="text-lg font-semibold text-slate-900" data-current-page-title>
                                    {resolvedTitle}
                                </div>
                            </div>
                        </div>

                        {headerStats.length > 0 && (
                            <div className="stats hidden flex-wrap items-center justify-center gap-4 text-xs lg:flex flex-1">
                                {headerStats.map((stat) => (
                                    <HeaderStat key={stat.label} {...stat} />
                                ))}
                            </div>
                        )}

                        <div className="flex items-center justify-end">
                            <UserDropdown user={user} roleLabel={roleLabel} profileRoute={profileRoute} />
                        </div>
                    </div>

                    <ImpersonationBanner />
                </header>

                <main id="main-content" className="w-full px-3 sm:px-4 md:px-6 py-4 md:py-10 pb-safe-nav md:pb-10 fade-in">
                    {flash?.error && (
                        <div className="mb-4 md:mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            {flash.error}
                        </div>
                    )}
                    {flash?.status && (
                        <div className="mb-4 md:mb-6 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-700">
                            {flash.status}
                        </div>
                    )}
                    {children}
                </main>
            </div>

            {/* Mobile Bottom Navigation Bar (<md) */}
            <MobileBottomNav items={resolvedNavItems} />

            {/* Mobile "More" Menu Bottom Sheet (<md) */}
            {moreSections.length > 0 && (
                <MobileBottomSheet
                    isOpen={isMoreSheetOpen}
                    onClose={() => setIsMoreSheetOpen(false)}
                    title={moreTitle}
                    description={resolvedMoreDescription}
                >
                    <div className="space-y-5 py-2">
                        {moreSections.map((section, sIdx) => (
                            <div key={section.title || sIdx} className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-teal-600 border-b border-slate-100 pb-1">
                                    {section.title}
                                </div>
                                <div className="grid grid-cols-2 gap-2 pt-1">
                                    {section.items.map((item, iIdx) => (
                                        <a
                                            key={item.label || iIdx}
                                            href={item.href}
                                            data-native="true"
                                            className="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/70 hover:bg-teal-50/50 hover:border-teal-200 transition active:scale-98 text-left"
                                        >
                                            {item.icon ? (
                                                <div className="w-8 h-8 rounded-lg bg-white border border-slate-200/90 flex items-center justify-center shrink-0 text-teal-600 shadow-2xs">
                                                    {typeof item.icon === 'function' ? item.icon() : item.icon}
                                                </div>
                                            ) : (
                                                <div className="w-2 h-2 rounded-full bg-teal-500 shrink-0" />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <div className="text-xs font-semibold text-slate-800 truncate">
                                                    {item.label}
                                                </div>
                                                {item.badge && Number(item.badge) > 0 && (
                                                    <span className="inline-block mt-0.5 px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-rose-100 text-rose-700">
                                                        {item.badge}
                                                    </span>
                                                )}
                                            </div>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </MobileBottomSheet>
            )}
        </div>
    );
}
