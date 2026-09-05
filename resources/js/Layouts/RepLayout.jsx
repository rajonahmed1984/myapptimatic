import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import SidebarToggle from '../Components/Layout/SidebarToggle';
import UserDropdown from '../Components/Layout/UserDropdown';
import ImpersonationBanner from '../Components/Layout/ImpersonationBanner';
import MobileTopBar from '../Components/Mobile/MobileTopBar';
import MobileBottomNav from '../Components/Mobile/MobileBottomNav';

const isActiveRoute = (currentUrl, patterns) => {
    if (!currentUrl) return false;
    const urlPath = currentUrl.split('?')[0].split('#')[0];
    const list = Array.isArray(patterns) ? patterns : [patterns];
    return list.some((p) => {
        if (!p) return false;
        if (p === urlPath) return true;
        if (p.endsWith('*')) {
            return urlPath.startsWith(p.slice(0, -1));
        }
        return urlPath === p || urlPath.startsWith(`${p}/`);
    });
};

function NavLink({ href, active, badge, children }) {
    return (
        <a
            href={href}
            data-native="true"
            className={active ? 'nav-link nav-link-active' : 'nav-link'}
        >
            <span className="h-2 w-2 rounded-full bg-current flex-shrink-0" />
            <span className="truncate">{children}</span>
            {badge !== undefined && badge !== null && Number(badge) > 0 && (
                <span className="ml-auto rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">
                    {badge}
                </span>
            )}
        </a>
    );
}

export default function RepLayout({ children, title, pageHeading }) {
    const page = usePage();
    const { auth, branding, stats, permissions, flash } = page.props;
    const currentUrl = page.url || window.location.pathname;
    // Matching the bare portal prefix would highlight Dashboard on every page.
    const portalRootPath = String(currentUrl).split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';

    const [mobileOpen, setMobileOpen] = useState(false);

    const user = auth?.user;
    const repStats = stats?.rep || {};
    const canViewTasks = permissions?.can_view_tasks;

    const companyName = branding?.company_name || 'License Portal';
    const logoUrl = branding?.favicon_url || branding?.logo_url;

    const repNavItems = [
        {
            label: 'Dashboard',
            href: '/rep/dashboard',
            active: portalRootPath === '/rep' || isActiveRoute(currentUrl, '/rep/dashboard'),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            ),
        },
        {
            label: 'Earnings',
            href: '/rep/earnings',
            active: isActiveRoute(currentUrl, ['/rep/earnings*', '/rep/payouts*']),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
        },
        {
            label: 'Projects',
            href: '/rep/projects',
            active: isActiveRoute(currentUrl, ['/rep/projects*', '/rep/tasks*']),
            badge: repStats?.task_badge,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
            ),
        },
        {
            label: 'Chat',
            href: '/rep/chats',
            active: isActiveRoute(currentUrl, ['/rep/chats*', '/rep/projects/chat*']),
            badge: repStats?.unread_chat,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            ),
        },
        {
            label: 'Profile',
            href: '/rep/profile',
            active: isActiveRoute(currentUrl, '/rep/profile*'),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            ),
        },
    ];

    return (
        <div className="min-h-screen flex flex-col md:flex-row bg-dashboard">
            {/* Mobile Overlay */}
            <div
                id="repSidebarOverlay"
                onClick={() => setMobileOpen(false)}
                className={`fixed inset-0 z-20 bg-slate-900/60 transition-opacity duration-200 md:hidden ${
                    mobileOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
                }`}
            />

            {/* Sidebar */}
            <aside
                id="repSidebar"
                className={`sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out md:w-64 md:max-w-none md:translate-x-0 md:sticky md:top-0 ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex items-center gap-3">
                    {logoUrl ? (
                        <img src={logoUrl} alt="Brand mark" className="h-11 w-11 rounded-2xl bg-white p-1 object-contain" />
                    ) : (
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">
                            SR
                        </div>
                    )}
                    <div className="min-w-0">
                        <div className="text-xs uppercase tracking-[0.35em] text-slate-400">Sales Rep</div>
                        <div className="text-lg font-semibold text-white truncate">{companyName}</div>
                    </div>
                </div>

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

                <nav className="mt-8 space-y-4 text-sm flex-1">
                    <div>
                        <NavLink href="/rep/dashboard" active={portalRootPath === '/rep' || isActiveRoute(currentUrl, '/rep/dashboard')}>
                            Dashboard
                        </NavLink>
                    </div>

                    <div className="space-y-2">
                        <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                            Sales & Work
                        </div>
                        <NavLink href="/rep/projects" active={isActiveRoute(currentUrl, '/rep/projects*')}>
                            My Projects
                        </NavLink>
                        {canViewTasks && (
                            <NavLink
                                href="/rep/tasks"
                                active={isActiveRoute(currentUrl, '/rep/tasks*')}
                                badge={repStats?.task_badge}
                            >
                                Tasks
                            </NavLink>
                        )}
                        <NavLink
                            href="/rep/chats"
                            active={isActiveRoute(currentUrl, ['/rep/chats*', '/rep/projects/chat*'])}
                            badge={repStats?.unread_chat}
                        >
                            Chat
                        </NavLink>
                    </div>

                    <div className="space-y-2">
                        <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                            Finance & Earnings
                        </div>
                        <NavLink href="/rep/earnings" active={isActiveRoute(currentUrl, '/rep/earnings*')}>
                            Commissions
                        </NavLink>
                        <NavLink href="/rep/payouts" active={isActiveRoute(currentUrl, '/rep/payouts*')}>
                            Payouts
                        </NavLink>
                    </div>

                    <div className="space-y-2">
                        <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                            Account
                        </div>
                        <NavLink href="/rep/profile" active={isActiveRoute(currentUrl, '/rep/profile*')}>
                            Profile Settings
                        </NavLink>
                    </div>
                </nav>
            </aside>

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col w-full min-w-0">
                {/* Mobile Top App Bar (<md) */}
                <MobileTopBar
                    title={pageHeading || title || 'Overview'}
                    user={user}
                    roleLabel="Sales Representative"
                    profileRoute="/rep/profile"
                    branding={branding}
                    onOpenMenu={() => setMobileOpen(true)}
                />

                {/* Desktop Sticky Header (>=md) */}
                <header className="hidden md:block sticky top-0 z-20 border-b border-slate-300/70 bg-white/80 backdrop-blur">
                    <div className="flex w-full items-center justify-between gap-6 px-6 py-4">
                        <div className="flex items-center gap-3 min-w-[240px]">
                            <SidebarToggle onToggleMobile={() => setMobileOpen(!mobileOpen)} />
                            <div>
                                <div className="text-lg font-semibold text-slate-900" data-current-page-title>
                                    {pageHeading || title || 'Overview'}
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-end">
                            <UserDropdown user={user} roleLabel="Sales Representative" profileRoute="/rep/profile" />
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
            <MobileBottomNav items={repNavItems} />
        </div>
    );
}