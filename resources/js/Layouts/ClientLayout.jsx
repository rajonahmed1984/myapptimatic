import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import SidebarToggle from '../Components/Layout/SidebarToggle';
import UserDropdown from '../Components/Layout/UserDropdown';
import ImpersonationBanner from '../Components/Layout/ImpersonationBanner';

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

    const user = auth?.user;
    const clientStats = stats?.client || {};
    const canViewTasks = permissions?.can_view_tasks;

    const companyName = branding?.company_name || 'License Portal';
    const logoUrl = branding?.favicon_url || branding?.logo_url;

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
                <header className="sticky top-0 z-20 border-b border-slate-300/70 bg-white/80 backdrop-blur">
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

                <main id="main-content" className="w-full px-6 py-10 fade-in">
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
                    {children}
                </main>
            </div>
        </div>
    );
}