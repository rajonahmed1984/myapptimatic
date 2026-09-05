import React from 'react';
import { usePage } from '@inertiajs/react';
import { isActiveRoute, NavLink, NavMenu } from '../Components/Layout/PortalNav';
import MobileAppShell from '../Components/Mobile/MobileAppShell';
import { HomeIcon, TicketIcon, TasksIcon, EmailIcon } from '../Components/Icons';

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
        {
            label: 'Email',
            href: '/support/apptimatic-email/inbox',
            active: isActiveRoute(currentUrl, '/support/apptimatic-email*'),
            icon: EmailIcon,
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

            <div className="space-y-2">
                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                    Communication
                </div>
                <NavMenu label="Email" active={isActiveRoute(currentUrl, '/support/apptimatic-email*')}>
                    <a href="/support/apptimatic-email/inbox?compose=new" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2.5 my-1 rounded-lg bg-teal-500/15 text-teal-300 hover:bg-teal-500/25 hover:text-white font-medium transition text-xs">
                        <svg className="w-3.5 h-3.5 text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.2" d="M12 4v16m8-8H4" /></svg>
                        <span>Compose</span>
                    </a>
                    <a href="/support/apptimatic-email/inbox" data-native="true" className="flex items-center justify-between py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                        <span className="flex items-center gap-2.5">
                            <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                            <span>Inbox</span>
                        </span>
                    </a>
                    <a href="/support/apptimatic-email/inbox?folder=sent" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                        <span>Sent</span>
                    </a>
                    <a href="/support/apptimatic-email/inbox?folder=drafts" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span>Drafts</span>
                    </a>
                    <a href="/support/apptimatic-email/inbox?folder=spam" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Spam</span>
                    </a>
                </NavMenu>
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
