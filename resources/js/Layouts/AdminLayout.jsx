import React, { useState } from 'react';
import { usePage, Link } from '@inertiajs/react';
import SidebarToggle from '../Components/Layout/SidebarToggle';
import UserDropdown from '../Components/Layout/UserDropdown';
import GlobalWorkTimer from '../Components/Layout/GlobalWorkTimer';
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
    const [isMoreSheetOpen, setIsMoreSheetOpen] = useState(false);

    const user = auth?.user;
    const isEmployee = auth?.portal === 'employee' || currentUrl.startsWith('/employee');
    const isMasterAdmin = permissions?.is_master_admin;
    const canViewTasks = permissions?.can_view_tasks;

    const adminStats = stats?.admin || {};
    const employeeStats = stats?.employee || {};

    // Bottom Navigation items for Admin
    const adminNavItems = [
        {
            label: 'Dashboard',
            href: '/admin/dashboard',
            active: urlPath === '/admin' || isActiveRoute(currentUrl, '/admin/dashboard'),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            ),
        },
        {
            label: 'Sales',
            href: '/admin/customers',
            active: isActiveRoute(currentUrl, ['/admin/customers*', '/admin/orders*', '/admin/subscriptions*', '/admin/licenses*']),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            ),
        },
        {
            label: 'Projects',
            href: '/admin/projects',
            active: isActiveRoute(currentUrl, ['/admin/projects*', '/admin/tasks*']),
            badge: adminStats?.tasks_badge,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
            ),
        },
        {
            label: 'Chat',
            href: '/admin/chats',
            active: isActiveRoute(currentUrl, ['/admin/chats*', '/admin/apptimatic-email*']),
            badge: Number(adminStats?.unread_chat || 0) + Number(adminStats?.apptimatic_email_unread || 0),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            ),
        },
        {
            label: 'More',
            onClick: () => setIsMoreSheetOpen(true),
            active: isMoreSheetOpen,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            ),
        },
    ];

    // Bottom Navigation items for Employee
    const employeeNavItems = [
        {
            label: 'Home',
            href: '/employee/dashboard',
            active: isActiveRoute(currentUrl, ['/employee/dashboard', '/employee']),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            ),
        },
        {
            label: 'Tasks',
            href: '/employee/tasks',
            active: isActiveRoute(currentUrl, '/employee/tasks*'),
            badge: employeeStats?.task_badge,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            ),
        },
        {
            label: 'Projects',
            href: '/employee/projects',
            active: isActiveRoute(currentUrl, '/employee/projects*'),
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
            ),
        },
        {
            label: 'Chat',
            href: '/employee/chats',
            active: isActiveRoute(currentUrl, ['/employee/chats*', '/employee/apptimatic-email*']),
            badge: employeeStats?.unread_chat,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            ),
        },
        {
            label: 'More',
            onClick: () => setIsMoreSheetOpen(true),
            active: isMoreSheetOpen,
            icon: ({ active, className }) => (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={active ? '2.5' : '2'} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            ),
        },
    ];

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

    const adminMoreSections = [
        {
            title: 'Billing & Subscriptions',
            items: [
                { label: 'Invoices', href: '/admin/invoices' },
                { label: 'Orders', href: '/admin/orders', badge: adminStats?.pending_orders },
                { label: 'Subscriptions', href: '/admin/subscriptions' },
                { label: 'Licenses', href: '/admin/licenses' },
                { label: 'Manual Payments', href: '/admin/payment-proofs', badge: adminStats?.pending_manual_payments },
                { label: 'Payment Gateways', href: '/admin/payment-gateways' },
            ],
        },
        {
            title: 'Finance & Accounting',
            items: [
                { label: 'Income', href: '/admin/income' },
                { label: 'Expenses', href: '/admin/expenses' },
                { label: 'VAT Settings', href: '/admin/finance/vat' },
                { label: 'Finance Reports', href: '/admin/finance/reports' },
                { label: 'Accounting Ledger', href: '/admin/accounting' },
                { label: 'CarrotHost Sync', href: '/admin/income/carrothost' },
            ],
        },
        {
            title: 'People (HR)',
            items: [
                { label: 'HR Dashboard', href: '/admin/hr/dashboard' },
                { label: 'Employees', href: '/admin/hr/employees' },
                { label: 'Work Logs', href: '/admin/hr/timesheets' },
                { label: 'Leave Requests', href: '/admin/hr/leave-requests', badge: adminStats?.pending_leave_requests },
                { label: 'Attendance', href: '/admin/hr/attendance' },
                { label: 'Payroll', href: '/admin/hr/payroll' },
            ],
        },
        {
            title: 'Support & Messaging',
            items: [
                { label: 'Support Tickets', href: '/admin/support-tickets', badge: adminStats?.open_support_tickets },
                { label: 'Webmail Inbox', href: '/admin/apptimatic-email/inbox', badge: adminStats?.apptimatic_email_unread },
                { label: 'Compose Email', href: '/admin/apptimatic-email/inbox?compose=new' },
                { label: 'Chatbot Leads', href: '/admin/chatbot-leads' },
                { label: 'Mass Mailer', href: '/admin/mass-mail' },
            ],
        },
        {
            title: 'System & Preferences',
            items: [
                { label: 'Automation Status', href: '/admin/automation-status' },
                { label: 'Activity Logs', href: '/admin/logs' },
                { label: 'Settings', href: '/admin/settings' },
                { label: 'My Profile', href: '/admin/profile' },
            ],
        },
    ];

    const employeeMoreSections = [
        {
            title: 'Time & Attendance',
            items: [
                { label: 'Work Logs', href: '/employee/timesheets' },
                { label: 'Leave Requests', href: '/employee/leave-requests' },
                { label: 'Attendance', href: '/employee/attendance' },
            ],
        },
        {
            title: 'Payroll & Slips',
            items: [
                { label: 'My Payroll', href: '/employee/payroll' },
            ],
        },
        {
            title: 'Apptimatic Email',
            items: [
                { label: 'Email Inbox', href: '/employee/apptimatic-email/inbox' },
                { label: 'Compose Mail', href: '/employee/apptimatic-email/inbox?compose=new' },
            ],
        },
        {
            title: 'Account',
            items: [
                { label: 'My Profile', href: '/employee/profile' },
            ],
        },
    ];

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
                                    <a href="/admin/apptimatic-email/inbox?compose=new" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2.5 my-1 rounded-lg bg-teal-500/15 text-teal-300 hover:bg-teal-500/25 hover:text-white font-medium transition text-xs">
                                        <svg className="w-3.5 h-3.5 text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.2" d="M12 4v16m8-8H4" /></svg>
                                        <span>Compose</span>
                                    </a>
                                    <a href="/admin/apptimatic-email/inbox" data-native="true" className="flex items-center justify-between py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                                        <span className="flex items-center gap-2.5">
                                            <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <span>Inbox</span>
                                        </span>
                                        {Number(adminStats?.apptimatic_email_unread || 0) > 0 && (
                                            <span className="rounded-full bg-teal-500/20 text-teal-300 px-2 py-0.5 text-[11px] font-bold">
                                                {adminStats.apptimatic_email_unread}
                                            </span>
                                        )}
                                    </a>
                                    <a href="/admin/apptimatic-email/inbox?folder=sent" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                                        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                        <span>Sent</span>
                                    </a>
                                    <a href="/admin/apptimatic-email/inbox?folder=drafts" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                                        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        <span>Drafts</span>
                                    </a>
                                    <a href="/admin/apptimatic-email/inbox?folder=spam" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                                        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <span>Spam</span>
                                    </a>
                                    <a href="/admin/apptimatic-email/manage" data-native="true" className="flex items-center gap-2.5 py-1.5 px-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition text-xs">
                                        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <span>Manage</span>
                                    </a>
                                </NavMenu>
                            </div>

                            <div className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-400 border-b border-cyan-500/20 pb-1 mb-2">
                                    Administration
                                </div>
                                {isMasterAdmin && (
                                    <>
                                        <NavLink href="/admin/user/master_admin" active={currentUrl.includes('/admin/user/master_admin') || currentUrl.includes('/admin/users/master_admin')}>
                                            Master Admins
                                        </NavLink>
                                        <NavLink href="/admin/user/sub_admin" active={currentUrl.includes('/admin/user/sub_admin') || currentUrl.includes('/admin/users/sub_admin')}>
                                            Sub Admins
                                        </NavLink>
                                        <NavLink href="/admin/user/support" active={currentUrl.includes('/admin/user/support') || currentUrl.includes('/admin/users/support')}>
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
                {/* Mobile Top App Bar (<md) */}
                <MobileTopBar
                    title={pageHeading || title || 'Overview'}
                    user={user}
                    roleLabel={roleLabel}
                    profileRoute={profileRoute}
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
            <MobileBottomNav items={isEmployee ? employeeNavItems : adminNavItems} />

            {/* Mobile "More" Menu Bottom Sheet (<md) */}
            <MobileBottomSheet
                isOpen={isMoreSheetOpen}
                onClose={() => setIsMoreSheetOpen(false)}
                title={isEmployee ? "Employee Navigation" : "All Features & Tools"}
                description={`Access all ${isEmployee ? 'work' : 'management'} modules`}
            >
                <div className="space-y-5 py-2">
                    {(isEmployee ? employeeMoreSections : adminMoreSections).map((section, sIdx) => (
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
                                        <div className="w-2 h-2 rounded-full bg-teal-500 shrink-0" />
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
        </div>
    );
}
