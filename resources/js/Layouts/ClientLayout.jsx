import React from 'react';
import { usePage } from '@inertiajs/react';
import { isActiveRoute, NavLink } from '../Components/Layout/PortalNav';
import MobileAppShell from '../Components/Mobile/MobileAppShell';

const ServicesIcon = () => (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
);
const LicensesIcon = () => (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
    </svg>
);
const OrdersIcon = () => (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
    </svg>
);
const SupportIcon = () => (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>
);
const TasksIcon = () => (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
    </svg>
);
const ProfileIcon = () => (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
);

export default function ClientLayout({ children, title, pageHeading }) {
    const page = usePage();
    const { auth, branding, stats, permissions } = page.props;
    const currentUrl = page.url || window.location.pathname;
    // Matching the bare portal prefix would highlight Dashboard on every page.
    const portalRootPath = String(currentUrl).split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';

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
            isMore: true,
            active: isActiveRoute(currentUrl, ['/client/services*', '/client/licenses*', '/client/orders*', '/client/support-tickets*', '/client/profile*']),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? 2.5 : 1.8} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            )
        }
    ];

    const clientMoreSections = [
        {
            title: 'Services & Billing',
            items: [
                { label: 'Services', href: '/client/services', icon: <ServicesIcon /> },
                { label: 'Licenses', href: '/client/licenses', icon: <LicensesIcon /> },
                { label: 'My Orders', href: '/client/orders', icon: <OrdersIcon /> },
            ],
        },
        {
            title: 'Support & Account',
            items: [
                { label: 'Support Tickets', href: '/client/support-tickets', icon: <SupportIcon /> },
                ...(canViewTasks ? [{ label: 'Tasks', href: '/client/tasks', icon: <TasksIcon /> }] : []),
                { label: 'Profile', href: '/client/profile', icon: <ProfileIcon /> },
            ],
        },
    ];

    const sidebarContent = (
        <>
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
                        badgeColor="bg-teal-100 text-teal-700"
                    >
                        Tasks
                    </NavLink>
                )}
                <NavLink
                    href="/client/chats"
                    active={isActiveRoute(currentUrl, ['/client/chats*', '/client/projects/chat*'])}
                    badge={clientStats?.unread_chat}
                    badgeColor="bg-teal-100 text-teal-700"
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
                    badgeColor="bg-teal-100 text-teal-700"
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
        </>
    );

    return (
        <MobileAppShell
            portalKey="client"
            portalLabel="Client"
            companyName={companyName}
            logoUrl={logoUrl}
            brandInitials="CL"
            sidebarContent={sidebarContent}
            title={title}
            pageHeading={pageHeading}
            user={user}
            roleLabel="Client"
            profileRoute="/client/profile"
            branding={branding}
            navItems={clientNavItems}
            moreSections={clientMoreSections}
            moreTitle="Client Portal Menu"
            moreDescription="Quick access to all services and settings"
        >
            {children}
        </MobileAppShell>
    );
}
