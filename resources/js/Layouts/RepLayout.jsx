import React from 'react';
import { usePage } from '@inertiajs/react';
import { isActiveRoute, NavLink } from '../Components/Layout/PortalNav';
import MobileAppShell from '../Components/Mobile/MobileAppShell';

export default function RepLayout({ children, title, pageHeading }) {
    const page = usePage();
    const { auth, branding, stats, permissions } = page.props;
    const currentUrl = page.url || window.location.pathname;
    // Matching the bare portal prefix would highlight Dashboard on every page.
    const portalRootPath = String(currentUrl).split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';

    const user = auth?.user;
    const repStats = stats?.rep || {};
    const canViewTasks = permissions?.can_view_tasks;

    const companyName = branding?.company_name || 'License Portal';
    const logoUrl = branding?.favicon_url || branding?.logo_url;

    const repNavItems = [
        {
            label: 'Dashboard',
            href: '/sales/dashboard',
            active: portalRootPath === '/sales' || isActiveRoute(currentUrl, '/sales/dashboard'),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            ),
        },
        {
            label: 'Earnings',
            href: '/sales/earnings',
            active: isActiveRoute(currentUrl, ['/sales/earnings*', '/sales/payouts*']),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
        },
        {
            label: 'Projects',
            href: '/sales/projects',
            active: isActiveRoute(currentUrl, ['/sales/projects*', '/sales/tasks*']),
            badge: repStats?.task_badge,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
            ),
        },
        {
            label: 'Chat',
            href: '/sales/chats',
            active: isActiveRoute(currentUrl, ['/sales/chats*', '/sales/projects/chat*']),
            badge: repStats?.unread_chat,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            ),
        },
        {
            label: 'Profile',
            href: '/sales/profile',
            active: isActiveRoute(currentUrl, '/sales/profile*'),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            ),
        },
    ];

    const sidebarContent = (
        <>
            <div>
                <NavLink href="/sales/dashboard" active={portalRootPath === '/sales' || isActiveRoute(currentUrl, '/sales/dashboard')}>
                    Dashboard
                </NavLink>
            </div>

            <div className="space-y-2">
                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                    Sales & Work
                </div>
                <NavLink href="/sales/projects" active={isActiveRoute(currentUrl, '/sales/projects*')}>
                    My Projects
                </NavLink>
                {canViewTasks && (
                    <NavLink
                        href="/sales/tasks"
                        active={isActiveRoute(currentUrl, '/sales/tasks*')}
                        badge={repStats?.task_badge}
                        badgeColor="bg-teal-100 text-teal-700"
                    >
                        Tasks
                    </NavLink>
                )}
                <NavLink
                    href="/sales/chats"
                    active={isActiveRoute(currentUrl, ['/sales/chats*', '/sales/projects/chat*'])}
                    badge={repStats?.unread_chat}
                    badgeColor="bg-teal-100 text-teal-700"
                >
                    Chat
                </NavLink>
            </div>

            <div className="space-y-2">
                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                    Finance & Earnings
                </div>
                <NavLink href="/sales/earnings" active={isActiveRoute(currentUrl, '/sales/earnings*')}>
                    Commissions
                </NavLink>
                <NavLink href="/sales/payouts" active={isActiveRoute(currentUrl, '/sales/payouts*')}>
                    Payouts
                </NavLink>
            </div>

            <div className="space-y-2">
                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                    Account
                </div>
                <NavLink href="/sales/profile" active={isActiveRoute(currentUrl, '/sales/profile*')}>
                    Profile Settings
                </NavLink>
            </div>
        </>
    );

    return (
        <MobileAppShell
            portalKey="rep"
            portalLabel="Sales Rep"
            companyName={companyName}
            logoUrl={logoUrl}
            brandInitials="SR"
            sidebarContent={sidebarContent}
            title={title}
            pageHeading={pageHeading}
            user={user}
            roleLabel="Sales Representative"
            profileRoute="/sales/profile"
            branding={branding}
            navItems={repNavItems}
        >
            {children}
        </MobileAppShell>
    );
}
