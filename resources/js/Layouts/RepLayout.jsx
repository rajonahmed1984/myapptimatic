import React from 'react';
import { usePage } from '@inertiajs/react';
import { isActiveRoute, NavLink } from '../Components/Layout/PortalNav';
import MobileAppShell from '../Components/Mobile/MobileAppShell';
import { HomeIcon, EarningsIcon, ProjectsIcon, ChatIcon, ProfileIcon } from '../Components/Icons';

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
            icon: HomeIcon,
        },
        {
            label: 'Earnings',
            href: '/sales/earnings',
            active: isActiveRoute(currentUrl, ['/sales/earnings*', '/sales/payouts*']),
            icon: EarningsIcon,
        },
        {
            label: 'Projects',
            href: '/sales/projects',
            active: isActiveRoute(currentUrl, ['/sales/projects*', '/sales/tasks*']),
            badge: repStats?.task_badge,
            icon: ProjectsIcon,
        },
        {
            label: 'Chat',
            href: '/sales/chats',
            active: isActiveRoute(currentUrl, ['/sales/chats*', '/sales/projects/chat*']),
            badge: repStats?.unread_chat,
            icon: ChatIcon,
        },
        {
            label: 'Profile',
            href: '/sales/profile',
            active: isActiveRoute(currentUrl, '/sales/profile*'),
            icon: ProfileIcon,
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
