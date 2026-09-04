import React, { useState } from 'react';
import { usePage, Link } from '@inertiajs/react';
import SidebarToggle from '../Components/Layout/SidebarToggle';
import UserDropdown from '../Components/Layout/UserDropdown';
import GlobalWorkTimer from '../Components/Layout/GlobalWorkTimer';
import ImpersonationBanner from '../Components/Layout/ImpersonationBanner';

const isActiveRoute = (currentUrl, patterns) => {
    if (!currentUrl) return false;
    const urlPath = currentUrl.split('?')[0].split('#')[0];
    const list = Array.isArray(patterns) ? patterns : [patterns];
    return list.some((p) => {
        if (!p) return false;
        if (p === urlPath) return true;
        if (p.endsWith('*')) {
            const prefix = p.slice(0, -1);
            return urlPath.startsWith(prefix);
        }
        return urlPath === p || urlPath.startsWith(`${p}/`);
    });
};

function NavLink({ href, active, badge, badgeColor = 'bg-amber-100 text-amber-900', children }) {
    return (
        <a
            href={href}
            data-native="true"
            className={active ? 'nav-link nav-link-active' : 'nav-link'}
        >
            <span className="h-2 w-2 rounded-full bg-current flex-shrink-0" />
            <span className="truncate">{children}</span>
            {badge !== undefined && badge !== null && Number(badge) > 0 && (
                <span className={`ml-auto rounded-full px-2 py-0.5 text-xs font-semibold ${badgeColor}`}>
                    {badge}
                </span>
            )}
        </a>
    );
}

function NavMenu({ label, active, children, defaultOpen = true }) {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <div className="space-y-1">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className={`nav-link w-full justify-between cursor-pointer ${active ? 'nav-link-active' : ''}`}
            >
                <div className="flex items-center gap-3">
                    <span className="h-2 w-2 rounded-full bg-current flex-shrink-0" />
                    <span>{label}</span>
                </div>
                <svg
                    className={`h-4 w-4 transform transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            {isOpen && <div className="ml-5 space-y-1 border-l border-slate-700/60 pl-3 text-xs">{children}</div>}
        </div>
    );
}


function HeaderStat({ href, count, label, tone }) {
    const value = Number(count || 0);
    const badgeClass = value > 0
        ? tone
        : 'bg-slate-100 text-slate-400 border-slate-200';

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

export default function AdminLayout({ children, title, pageHeading }) {
    const page = usePage();
    const { auth, branding, stats, permissions, flash } = page.props;
    const currentUrl = page.url || window.location.pathname;
    const urlPath = String(currentUrl).split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';

    const [mobileOpen, setMobileOpen] = useState(false);

    const user = auth?.user;
    const isEmployee = auth?.portal === 'employee' || currentUrl.startsWith('/employee');
    const isMasterAdmin = permissions?.is_master_admin;
    const canViewTasks = permissions?.can_view_tasks;

    const adminStats = stats?.admin || {};
    const employeeStats = stats?.employee || {};

    // The three things an admin is expected to act on. Always rendered, even at
    // zero — a chip that vanishes on a quiet day reads as a missing feature and
    // takes the shortcut to the list with it.
    const headerStats = [
        {
            href: '/admin/orders?status=pending',
            count: adminStats?.pending_orders,
            label: 'Pending Orders',
            tone: 'bg-amber-100 text-amber-700 border-amber-200/80',
        },
        {
            href: '/admin/invoices/overdue',
            count: adminStats?.overdue_invoices,
            label: 'Overdue Invoices',
            tone: 'bg-indigo-100 text-indigo-700 border-indigo-200/80',
        },
        {
            // Everything still needing an answer: newly opened plus the ones a
            // customer has replied to.
            href: '/admin/support-tickets',
            count: Number(adminStats?.open_support_tickets || 0) + Number(adminStats?.tickets_waiting || 0),
            label: 'Support Tickets',
            tone: 'bg-emerald-100 text-emerald-700 border-emerald-200/80',
        },
    ];

    const workMode = String(user?.employee?.work_mode || '').toLowerCase();
    const employmentType = String(user?.employee?.employment_type || '').toLowerCase();
    const isRemoteEmployee = ['remote', 'work_from_home', 'wfh'].includes(workMode);
    const isEmployeeWorkSessionEligible = isRemoteEmployee && ['full_time', 'part_time'].includes(employmentType);

    const companyName = branding?.company_name || 'License Portal';
    const logoUrl = branding?.favicon_url || branding?.logo_url;

    const roleLabel = isEmployee ? 'Employee' : isMasterAdmin ? 'Master Administrator' : (user?.role || 'Administrator');
    const profileRoute = isEmployee ? '/employee/profile' : '/admin/profile';

    return (
        <div className="min-h-screen flex flex-col md:flex-row bg-dashboard">
            {/* Mobile Overlay */}
            <div
                id="sidebarOverlay"
                onClick={() => setMobileOpen(false)}
                className={`fixed inset-0 z-20 bg-slate-900/60 transition-opacity duration-200 md:hidden ${
                    mobileOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
                }`}
            />

            {/* Sidebar */}
            <aside
                id="adminSidebar"
                className={`sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out md:w-64 md:max-w-none md:translate-x-0 md:sticky md:top-0 ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex items-center gap-3">
                    {logoUrl ? (
                        <img src={logoUrl} alt="Brand mark" className="h-11 w-11 rounded-2xl bg-white p-1 object-contain" />
                    ) : (
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">
                            LM
                        </div>
                    )}
                    <div className="min-w-0">
                        <div className="text-xs uppercase tracking-[0.35em] text-slate-400">
                            {isEmployee ? 'Employee' : 'Admin'}
                        </div>
                        <div className="text-lg font-semibold text-white truncate">{companyName}</div>
                    </div>
                </div>

                {isEmployee && isEmployeeWorkSessionEligible && <GlobalWorkTimer />}

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
                    {!isEmployee ? (
                        <>
                            <div>
                                {/* '/admin' as a pattern would prefix-match every admin page and
                                    leave Dashboard permanently highlighted, so match it exactly. */}
                                <NavLink href="/admin/dashboard" active={urlPath === '/admin' || isActiveRoute(currentUrl, '/admin/dashboard')}>
                                    Dashboard
                                </NavLink>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Sales & Customers
                                </div>
                                <NavLink href="/admin/customers" active={isActiveRoute(currentUrl, '/admin/customers*')}>
                                    Customers
                                </NavLink>
                                <NavLink href="/admin/orders" active={isActiveRoute(currentUrl, '/admin/orders*')}>
                                    Orders
                                </NavLink>
                                <NavLink href="/admin/sales-reps" active={isActiveRoute(currentUrl, '/admin/sales-reps*')}>
                                    Sales Representatives
                                </NavLink>
                                <NavLink href="/admin/affiliates" active={isActiveRoute(currentUrl, '/admin/affiliates*')}>
                                    Affiliates
                                </NavLink>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Projects
                                </div>
                                <NavLink href="/admin/projects" active={isActiveRoute(currentUrl, ['/admin/projects', '/admin/projects/all*'])}>
                                    All Projects
                                </NavLink>
                                <NavLink href="/admin/projects/create" active={currentUrl === '/admin/projects/create'}>
                                    Create Project
                                </NavLink>
                                <NavLink href="/admin/project-maintenances" active={isActiveRoute(currentUrl, '/admin/project-maintenances*')}>
                                    Maintenance
                                </NavLink>
                                {canViewTasks && (
                                    <NavLink
                                        href="/admin/tasks"
                                        active={isActiveRoute(currentUrl, '/admin/tasks*')}
                                        badge={adminStats?.tasks_badge}
                                    >
                                        Tasks
                                    </NavLink>
                                )}
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Products
                                </div>
                                <NavLink href="/admin/products" active={isActiveRoute(currentUrl, '/admin/products*')}>
                                    Products
                                </NavLink>
                                <NavLink href="/admin/mybuilding" active={isActiveRoute(currentUrl, '/admin/mybuilding*')}>
                                    MyBuilding
                                </NavLink>
                                <NavLink href="/admin/plans" active={isActiveRoute(currentUrl, '/admin/plans*')}>
                                    Plans
                                </NavLink>
                                <NavLink href="/admin/subscriptions" active={isActiveRoute(currentUrl, '/admin/subscriptions*')}>
                                    Subscriptions
                                </NavLink>
                                <NavLink
                                    href="/admin/licenses"
                                    active={isActiveRoute(currentUrl, '/admin/licenses*')}
                                    badge={adminStats?.verified_active_synced_licenses}
                                    badgeColor="bg-teal-100 text-teal-800"
                                >
                                    Licenses
                                </NavLink>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Billing
                                </div>
                                <NavLink href="/admin/invoices" active={currentUrl === '/admin/invoices'}>
                                    All invoices
                                </NavLink>
                                <NavLink href="/admin/invoices/paid" active={currentUrl === '/admin/invoices/paid'}>
                                    Paid
                                </NavLink>
                                <NavLink href="/admin/invoices/unpaid" active={currentUrl === '/admin/invoices/unpaid'}>
                                    Unpaid
                                </NavLink>
                                <NavLink href="/admin/invoices/overdue" active={currentUrl === '/admin/invoices/overdue'}>
                                    Overdue
                                </NavLink>
                                <NavLink href="/admin/invoices/cancelled" active={currentUrl === '/admin/invoices/cancelled'}>
                                    Cancelled
                                </NavLink>
                                <NavLink href="/admin/invoices/refunded" active={currentUrl === '/admin/invoices/refunded'}>
                                    Refunded
                                </NavLink>
                                <NavLink
                                    href="/admin/payment-proofs"
                                    active={isActiveRoute(currentUrl, '/admin/payment-proofs*')}
                                    badge={adminStats?.pending_manual_payments}
                                >
                                    Manual Payments
                                </NavLink>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Finance
                                </div>
                                {isMasterAdmin && (
                                    <>
                                        <NavMenu label="Income" active={isActiveRoute(currentUrl, '/admin/income*')}>
                                            <a href="/admin/income/carrothost" data-native="true" className="block py-1 text-slate-300 hover:text-white">CarrotHost</a>
                                            <a href="/admin/income" data-native="true" className="block py-1 text-slate-300 hover:text-white">All income</a>
                                            <a href="/admin/income/create" data-native="true" className="block py-1 text-slate-300 hover:text-white">Add income</a>
                                            <a href="/admin/income-categories" data-native="true" className="block py-1 text-slate-300 hover:text-white">Categories</a>
                                        </NavMenu>
                                        <NavMenu label="Expenses" active={isActiveRoute(currentUrl, '/admin/expenses*')}>
                                            <a href="/admin/expenses" data-native="true" className="block py-1 text-slate-300 hover:text-white">All expenses</a>
                                            <a href="/admin/expenses/create" data-native="true" className="block py-1 text-slate-300 hover:text-white">One-time expense</a>
                                            <a href="/admin/expenses/recurring" data-native="true" className="block py-1 text-slate-300 hover:text-white">Recurring expense</a>
                                            <a href="/admin/expense-categories" data-native="true" className="block py-1 text-slate-300 hover:text-white">Expense Categories</a>
                                        </NavMenu>
                                        <NavLink href="/admin/finance/vat" active={isActiveRoute(currentUrl, '/admin/finance/vat*')}>
                                            VAT Settings
                                        </NavLink>
                                    </>
                                )}
                                <NavLink href="/admin/payment-gateways" active={isActiveRoute(currentUrl, '/admin/payment-gateways*')}>
                                    Payment Gateways
                                </NavLink>
                                {isMasterAdmin && (
                                    <NavLink href="/admin/finance/reports" active={isActiveRoute(currentUrl, '/admin/finance/reports*')}>
                                        Finance Reports
                                    </NavLink>
                                )}
                                <NavLink href="/admin/commission-payouts" active={isActiveRoute(currentUrl, '/admin/commission-payouts*')}>
                                    Commission Payouts
                                </NavLink>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    People (HR)
                                </div>
                                <NavLink href="/admin/hr/dashboard" active={currentUrl === '/admin/hr/dashboard'}>
                                    HR Dashboard
                                </NavLink>
                                <NavLink href="/admin/hr/employees" active={isActiveRoute(currentUrl, '/admin/hr/employees*')}>
                                    Employees
                                </NavLink>
                                <NavLink href="/admin/users/activity-summary" active={currentUrl === '/admin/users/activity-summary'}>
                                    Activity Summary
                                </NavLink>
                                <NavLink href="/admin/hr/timesheets" active={isActiveRoute(currentUrl, '/admin/hr/timesheets*')}>
                                    Work Logs
                                </NavLink>
                                <NavLink href="/admin/hr/leave-types" active={isActiveRoute(currentUrl, '/admin/hr/leave-types*')}>
                                    Leave Types
                                </NavLink>
                                <NavLink
                                    href="/admin/hr/leave-requests"
                                    active={isActiveRoute(currentUrl, '/admin/hr/leave-requests*')}
                                    badge={adminStats?.pending_leave_requests}
                                >
                                    Leave Requests
                                </NavLink>
                                <NavLink href="/admin/hr/attendance" active={isActiveRoute(currentUrl, '/admin/hr/attendance*')}>
                                    Attendance
                                </NavLink>
                                <NavLink href="/admin/hr/paid-holidays" active={isActiveRoute(currentUrl, '/admin/hr/paid-holidays*')}>
                                    Paid Holidays
                                </NavLink>
                                <NavLink href="/admin/hr/payroll" active={isActiveRoute(currentUrl, '/admin/hr/payroll*')}>
                                    Payroll
                                </NavLink>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Support & Chat
                                </div>
                                <NavLink
                                    href="/admin/support-tickets"
                                    active={isActiveRoute(currentUrl, '/admin/support-tickets*')}
                                    badge={adminStats?.open_support_tickets}
                                >
                                    Support
                                </NavLink>
                                <NavLink
                                    href="/admin/chats"
                                    active={isActiveRoute(currentUrl, ['/admin/chats*', '/admin/projects/chat*'])}
                                    badge={adminStats?.unread_chat}
                                >
                                    Chat
                                </NavLink>
                                <NavLink href="/admin/chatbot-leads" active={isActiveRoute(currentUrl, '/admin/chatbot-leads*')}>
                                    Chatbot Leads
                                </NavLink>
                                <NavMenu label="Email" active={isActiveRoute(currentUrl, '/admin/apptimatic-email*')}>
                                    <a href="/admin/apptimatic-email/inbox?compose=new" data-native="true" className="block py-1 text-slate-300 hover:text-white">Compose</a>
                                    <a href="/admin/apptimatic-email/inbox" data-native="true" className="flex items-center justify-between py-1 text-slate-300 hover:text-white">
                                        <span>Inbox</span>
                                        {Number(adminStats?.apptimatic_email_unread || 0) > 0 && (
                                            <span className="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">
                                                {adminStats.apptimatic_email_unread}
                                            </span>
                                        )}
                                    </a>
                                    <a href="/admin/apptimatic-email/inbox?folder=sent" data-native="true" className="block py-1 text-slate-300 hover:text-white">Sent</a>
                                    <a href="/admin/apptimatic-email/inbox?folder=drafts" data-native="true" className="block py-1 text-slate-300 hover:text-white">Drafts</a>
                                    <a href="/admin/apptimatic-email/inbox?folder=spam" data-native="true" className="block py-1 text-slate-300 hover:text-white">Spam</a>
                                    <a href="/admin/apptimatic-email/manage" data-native="true" className="block py-1 text-slate-300 hover:text-white">Manage</a>
                                </NavMenu>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Administration
                                </div>
                                {isMasterAdmin && (
                                    <>
                                        <NavLink href="/admin/users/master_admin" active={currentUrl.includes('/admin/users/master_admin')}>
                                            Master Admins
                                        </NavLink>
                                        <NavLink href="/admin/users/sub_admin" active={currentUrl.includes('/admin/users/sub_admin')}>
                                            Sub Admins
                                        </NavLink>
                                        <NavLink href="/admin/users/support" active={currentUrl.includes('/admin/users/support')}>
                                            Support Users
                                        </NavLink>
                                    </>
                                )}
                                <NavLink href="/admin/profile" active={isActiveRoute(currentUrl, '/admin/profile*')}>
                                    Profile
                                </NavLink>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    System & Monitoring
                                </div>
                                <NavLink href="/admin/automation-status" active={currentUrl === '/admin/automation-status'}>
                                    Automation Status
                                </NavLink>
                                <NavLink href="/admin/logs" active={isActiveRoute(currentUrl, '/admin/logs*')}>
                                    Logs
                                </NavLink>
                                <NavLink href="/admin/settings" active={isActiveRoute(currentUrl, '/admin/settings*')}>
                                    Settings
                                </NavLink>
                                <NavLink href="/admin/mass-mail" active={isActiveRoute(currentUrl, '/admin/mass-mail*')}>
                                    Mass Mail
                                </NavLink>
                            </div>
                        </>
                    ) : (
                        /* Employee Navigation */
                        <>
                            <div>
                                <NavLink href="/employee/dashboard" active={isActiveRoute(currentUrl, ['/employee/dashboard', '/employee'])}>
                                    Dashboard
                                </NavLink>
                            </div>
                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    My Work
                                </div>
                                <NavLink href="/employee/projects" active={isActiveRoute(currentUrl, '/employee/projects*')}>
                                    Projects
                                </NavLink>
                                {canViewTasks && (
                                    <NavLink
                                        href="/employee/tasks"
                                        active={isActiveRoute(currentUrl, '/employee/tasks*')}
                                        badge={employeeStats?.task_badge}
                                        badgeColor="bg-teal-100 text-teal-700"
                                    >
                                        Tasks
                                    </NavLink>
                                )}
                                <NavLink
                                    href="/employee/chats"
                                    active={isActiveRoute(currentUrl, ['/employee/chats*', '/employee/projects/chat*'])}
                                    badge={employeeStats?.unread_chat}
                                    badgeColor="bg-teal-100 text-teal-700"
                                >
                                    Chat
                                </NavLink>
                                <NavMenu label="Apptimatic Email" active={isActiveRoute(currentUrl, '/employee/apptimatic-email*')}>
                                    <a href="/employee/apptimatic-email/inbox?compose=new" data-native="true" className="block py-1 text-slate-300 hover:text-white">Compose</a>
                                    <a href="/employee/apptimatic-email/inbox" data-native="true" className="block py-1 text-slate-300 hover:text-white">Inbox</a>
                                    <a href="/employee/apptimatic-email/inbox?folder=sent" data-native="true" className="block py-1 text-slate-300 hover:text-white">Sent</a>
                                    <a href="/employee/apptimatic-email/inbox?folder=drafts" data-native="true" className="block py-1 text-slate-300 hover:text-white">Drafts</a>
                                    <a href="/employee/apptimatic-email/inbox?folder=spam" data-native="true" className="block py-1 text-slate-300 hover:text-white">Spam</a>
                                </NavMenu>
                                {isEmployeeWorkSessionEligible && (
                                    <NavLink href="/employee/timesheets" active={isActiveRoute(currentUrl, '/employee/timesheets*')}>
                                        Work Logs
                                    </NavLink>
                                )}
                                <NavLink href="/employee/leave-requests" active={isActiveRoute(currentUrl, '/employee/leave-requests*')}>
                                    Leave Requests
                                </NavLink>
                                <NavLink href="/employee/attendance" active={isActiveRoute(currentUrl, '/employee/attendance*')}>
                                    Attendance
                                </NavLink>
                                <NavLink href="/employee/payroll" active={isActiveRoute(currentUrl, '/employee/payroll*')}>
                                    Payroll
                                </NavLink>
                            </div>
                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Account
                                </div>
                                <NavLink href="/employee/profile" active={isActiveRoute(currentUrl, '/employee/profile*')}>
                                    Profile
                                </NavLink>
                            </div>
                        </>
                    )}
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

                        {!isEmployee && (
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
