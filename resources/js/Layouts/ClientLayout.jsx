import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import SidebarToggle from '../Components/Layout/SidebarToggle';
import UserDropdown from '../Components/Layout/UserDropdown';
import ImpersonationBanner from '../Components/Layout/ImpersonationBanner';
import MobileTopBar from '../Components/Mobile/MobileTopBar';
import MobileBottomNav from '../Components/Mobile/MobileBottomNav';
import MobileBottomSheet from '../Components/Mobile/MobileBottomSheet';

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

export default function ClientLayout({ children, title, pageHeading }) {
    const page = usePage();
    const { auth, branding, stats, permissions, flash } = page.props;
    const currentUrl = page.url || window.location.pathname;
    // Matching the bare portal prefix would highlight Dashboard on every page.
    const portalRootPath = String(currentUrl).split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';

    const [mobileOpen, setMobileOpen] = useState(false);
    const [isMoreSheetOpen, setIsMoreSheetOpen] = useState(false);

    const user = auth?.user;
    const clientStats = stats?.client || {};
    const canViewTasks = permissions?.can_view_tasks;

    const companyName = branding?.company_name || 'License Portal';
    const logoUrl = branding?.favicon_url || branding?.logo_url;

    const clientNavItems = [
        {
            label: 'Home',
            href: '/client/dashboard',
            active: portalRootPath === '/client' || isActiveRoute(currentUrl, '/client/dashboard'),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? 2.5 : 1.8} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            )
        },
        {
            label: 'Projects',
            href: '/client/projects',
            active: isActiveRoute(currentUrl, '/client/projects*'),
            badge: clientStats?.task_badge,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? 2.5 : 1.8} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            )
        },
        {
            label: 'Billing',
            href: '/client/invoices',
            active: isActiveRoute(currentUrl, '/client/invoices*'),
            badge: clientStats?.unpaid_invoices,
            badgeColor: 'bg-rose-500 text-white',
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? 2.5 : 1.8} d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                </svg>
            )
        },
        {
            label: 'Chat',
            href: '/client/chats',
            active: isActiveRoute(currentUrl, ['/client/chats*', '/client/projects/chat*']),
            badge: clientStats?.unread_chat,
            badgeColor: 'bg-teal-500 text-white',
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? 2.5 : 1.8} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            )
        },
        {
            label: 'More',
            onClick: () => setIsMoreSheetOpen(true),
            active: isMoreSheetOpen || isActiveRoute(currentUrl, ['/client/services*', '/client/licenses*', '/client/orders*', '/client/support-tickets*', '/client/profile*']),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? 2.5 : 1.8} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            )
        }
    ];

    return (
        <div className="min-h-screen flex flex-col md:flex-row bg-dashboard">
            {/* Mobile Overlay */}
            <div
                id="clientSidebarOverlay"
                onClick={() => setMobileOpen(false)}
                className={`fixed inset-0 z-20 bg-slate-900/60 transition-opacity duration-200 md:hidden ${
                    mobileOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
                }`}
            />

            {/* Sidebar */}
            <aside
                id="clientSidebar"
                className={`sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out md:w-64 md:max-w-none md:translate-x-0 md:sticky md:top-0 ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex items-center gap-3">
                    {logoUrl ? (
                        <img src={logoUrl} alt="Brand mark" className="h-11 w-11 rounded-2xl bg-white p-1 object-contain" />
                    ) : (
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">
                            CL
                        </div>
                    )}
                    <div className="min-w-0">
                        <div className="text-xs uppercase tracking-[0.35em] text-slate-400">Client</div>
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
                        <NavLink href="/client/dashboard" active={portalRootPath === '/client' || isActiveRoute(currentUrl, '/client/dashboard')}>
                            Overview
                        </NavLink>
                    </div>

                    <div className="space-y-2">
                        <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                            Projects & Services
                        </div>
                        <NavLink href="/client/projects" active={isActiveRoute(currentUrl, '/client/projects*')}>
                            Projects
                        </NavLink>
                        {canViewTasks && (
                            <NavLink
                                href="/client/tasks"
                                active={isActiveRoute(currentUrl, '/client/tasks*')}
                                badge={clientStats?.task_badge}
                            >
                                Tasks
                            </NavLink>
                        )}
                        <NavLink
                            href="/client/chats"
                            active={isActiveRoute(currentUrl, ['/client/chats*', '/client/projects/chat*'])}
                            badge={clientStats?.unread_chat}
                        >
                            Chat
                        </NavLink>
                        <NavLink href="/client/services" active={isActiveRoute(currentUrl, '/client/services*')}>
                            Services
                        </NavLink>
                        <NavLink href="/client/licenses" active={isActiveRoute(currentUrl, '/client/licenses*')}>
                            Licenses
                        </NavLink>
                    </div>

                    <div className="space-y-2">
                        <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                            Orders & Requests
                        </div>
                        <NavLink href="/client/orders" active={isActiveRoute(currentUrl, '/client/orders*')}>
                            My Orders
                        </NavLink>
                    </div>

                    <div className="space-y-2">
                        <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                            Billing & Payments
                        </div>
                        <NavLink
                            href="/client/invoices"
                            active={isActiveRoute(currentUrl, '/client/invoices*')}
                            badge={clientStats?.unpaid_invoices}
                        >
                            Invoices
                        </NavLink>
                    </div>

                    <div className="space-y-2">
                        <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                            Support & Help
                        </div>
                        <NavLink href="/client/support-tickets" active={isActiveRoute(currentUrl, '/client/support-tickets*')}>
                            Support Tickets
                        </NavLink>
                    </div>

                    <div className="space-y-2">
                        <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                            Account
                        </div>
                        <NavLink href="/client/profile" active={isActiveRoute(currentUrl, '/client/profile*')}>
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
                    roleLabel="Client"
                    profileRoute="/client/profile"
                    branding={branding}
                    onOpenMenu={() => setMobileOpen(true)}
                />

                {/* Desktop Header (>=md) */}
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
                            <UserDropdown user={user} roleLabel="Client" profileRoute="/client/profile" />
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
            <MobileBottomNav items={clientNavItems} />

            {/* Mobile "More" Menu Bottom Sheet (<md) */}
            <MobileBottomSheet
                isOpen={isMoreSheetOpen}
                onClose={() => setIsMoreSheetOpen(false)}
                title="Client Portal Menu"
                description="Quick access to all services and settings"
            >
                <div className="grid grid-cols-2 gap-3 py-2">
                    <a
                        href="/client/services"
                        data-native="true"
                        className="flex flex-col items-center justify-center p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 hover:bg-teal-50/50 hover:border-teal-200 transition-all text-center"
                    >
                        <div className="w-10 h-10 rounded-xl bg-teal-100 text-teal-600 flex items-center justify-center mb-2">
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span className="text-xs font-semibold text-slate-800">Services</span>
                        <span className="text-[10px] text-slate-600">Active services</span>
                    </a>

                    <a
                        href="/client/licenses"
                        data-native="true"
                        className="flex flex-col items-center justify-center p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 hover:bg-teal-50/50 hover:border-teal-200 transition-all text-center"
                    >
                        <div className="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-2">
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <span className="text-xs font-semibold text-slate-800">Licenses</span>
                        <span className="text-[10px] text-slate-600">Keys & domains</span>
                    </a>

                    <a
                        href="/client/orders"
                        data-native="true"
                        className="flex flex-col items-center justify-center p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 hover:bg-teal-50/50 hover:border-teal-200 transition-all text-center"
                    >
                        <div className="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mb-2">
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                        <span className="text-xs font-semibold text-slate-800">My Orders</span>
                        <span className="text-[10px] text-slate-600">Purchase history</span>
                    </a>

                    <a
                        href="/client/support-tickets"
                        data-native="true"
                        className="flex flex-col items-center justify-center p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 hover:bg-teal-50/50 hover:border-teal-200 transition-all text-center"
                    >
                        <div className="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2">
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <span className="text-xs font-semibold text-slate-800">Support</span>
                        <span className="text-[10px] text-slate-600">Open a ticket</span>
                    </a>

                    {canViewTasks && (
                        <a
                            href="/client/tasks"
                            data-native="true"
                            className="flex flex-col items-center justify-center p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 hover:bg-teal-50/50 hover:border-teal-200 transition-all text-center"
                        >
                            <div className="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center mb-2">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <span className="text-xs font-semibold text-slate-800">Tasks</span>
                            <span className="text-[10px] text-slate-600">Project tasks</span>
                        </a>
                    )}

                    <a
                        href="/client/profile"
                        data-native="true"
                        className="flex flex-col items-center justify-center p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 hover:bg-teal-50/50 hover:border-teal-200 transition-all text-center"
                    >
                        <div className="w-10 h-10 rounded-xl bg-slate-200 text-slate-700 flex items-center justify-center mb-2">
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <span className="text-xs font-semibold text-slate-800">Profile</span>
                        <span className="text-[10px] text-slate-600">Account settings</span>
                    </a>
                </div>
            </MobileBottomSheet>
        </div>
    );
}