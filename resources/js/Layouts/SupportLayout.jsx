import React from 'react';
import { usePage } from '@inertiajs/react';
import { isActiveRoute, NavLink } from '../Components/Layout/PortalNav';
import MobileAppShell from '../Components/Mobile/MobileAppShell';
import { HomeIcon, TicketIcon, TasksIcon } from '../Components/Icons';

export default function SupportLayout({ children, title, pageHeading }) {
    const page = usePage();
    const { auth, branding } = page.props;
    const currentUrl = page.url || window.location.pathname;
    // Matching the bare portal prefix would highlight Dashboard on every page.
    const portalRootPath = String(currentUrl).split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';

    const user = auth?.user;
    const companyName = branding?.company_name || 'License Portal';
    const logoUrl = branding?.favicon_url || branding?.logo_url;

    const supportNavItems = [
        {
            label: 'Dashboard',
            href: '/support/dashboard',
            active: portalRootPath === '/support' || isActiveRoute(currentUrl, '/support/dashboard'),
            icon: HomeIcon,
        },
        {
            label: 'Tickets',
            href: '/support/support-tickets',
            active: isActiveRoute(currentUrl, '/support/support-tickets*'),
            icon: TicketIcon,
        },
        {
            label: 'Tasks',
            href: '/support/tasks',
            active: isActiveRoute(currentUrl, '/support/tasks*'),
            icon: TasksIcon,
        },
    ];

    const sidebarContent = (
        <>
            <div>
                <NavLink href="/support/dashboard" active={portalRootPath === '/support' || isActiveRoute(currentUrl, '/support/dashboard')}>
                    Dashboard
                </NavLink>
            </div>

            <div className="space-y-2">
                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                    Support & Tickets
                </div>
                <NavLink href="/support/support-tickets" active={isActiveRoute(currentUrl, '/support/support-tickets*')}>
                    Support Tickets
                </NavLink>
                <NavLink href="/support/tasks" active={isActiveRoute(currentUrl, '/support/tasks*')}>
                    Tasks
                </NavLink>
            </div>


        </>
    );

    return (
        <MobileAppShell
            portalKey="support"
            portalLabel="Support"
            companyName={companyName}
            logoUrl={logoUrl}
            brandInitials="SU"
            sidebarContent={sidebarContent}
            title={title}
            pageHeading={pageHeading}
            user={user}
            roleLabel="Support"
            profileRoute="/admin/profile"
            branding={branding}
            navItems={supportNavItems}
        >
            {children}
        </MobileAppShell>
    );
}
