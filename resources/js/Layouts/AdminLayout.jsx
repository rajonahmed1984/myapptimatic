import React from 'react';
import { usePage } from '@inertiajs/react';
import GlobalWorkTimer from '../Components/Layout/GlobalWorkTimer';
import { isActiveRoute, NavLink, NavMenu } from '../Components/Layout/PortalNav';
import MobileAppShell from '../Components/Mobile/MobileAppShell';
import { HomeIcon, SalesIcon, ProjectsIcon, ChatIcon, MoreIcon, TasksIcon } from '../Components/Icons';

export default function AdminLayout({ children, title, pageHeading }) {
    const page = usePage();
    const { auth, branding, stats, permissions } = page.props;
    const currentUrl = page.url || window.location.pathname;
    const urlPath = String(currentUrl).split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';

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
            icon: HomeIcon,
        },
        {
            label: 'Sales',
            href: '/admin/customers',
            active: isActiveRoute(currentUrl, ['/admin/customers*', '/admin/orders*', '/admin/subscriptions*', '/admin/licenses*']),
            icon: SalesIcon,
        },
        {
            label: 'Projects',
            href: '/admin/projects',
            active: isActiveRoute(currentUrl, ['/admin/projects*', '/admin/tasks*']),
            badge: adminStats?.tasks_badge,
            icon: ProjectsIcon,
        },
        {
            label: 'Chat',
            href: '/admin/chats',
            active: isActiveRoute(currentUrl, ['/admin/chats*']),
            badge: Number(adminStats?.unread_chat || 0),
            icon: ChatIcon,
        },
        {
            label: 'More',
            isMore: true,
            icon: MoreIcon,
        },
    ];

    // Bottom Navigation items for Employee
    const employeeNavItems = [
        {
            label: 'Home',
            href: '/employee/dashboard',
            active: urlPath === '/employee' || isActiveRoute(currentUrl, '/employee/dashboard'),
            icon: HomeIcon,
        },
        {
            label: 'Tasks',
            href: '/employee/tasks',
            active: isActiveRoute(currentUrl, '/employee/tasks*'),
            badge: employeeStats?.task_badge,
            icon: TasksIcon,
        },
        {
            label: 'Projects',
            href: '/employee/projects',
            active: isActiveRoute(currentUrl, '/employee/projects*'),
            icon: ProjectsIcon,
        },
        {
            label: 'Chat',
            href: '/employee/chats',
            active: isActiveRoute(currentUrl, ['/employee/chats*']),
            badge: employeeStats?.unread_chat,
            icon: ChatIcon,
        },
        {
            label: 'More',
            isMore: true,
            icon: MoreIcon,
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
                { label: 'Cancellations', href: '/admin/cancellation-requests', badge: adminStats?.pending_cancellations },
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
                { label: 'Work Logs', href: '/admin/hr/work-logs' },
                { label: 'Leave Requests', href: '/admin/hr/leave-requests', badge: adminStats?.pending_leave_requests },
                { label: 'Attendance', href: '/admin/hr/attendance' },
                { label: 'Payroll', href: '/admin/hr/payroll' },
            ],
        },
        {
            title: 'Support & Messaging',
            items: [
                { label: 'Support Tickets', href: '/admin/support-tickets', badge: adminStats?.open_support_tickets },
                { label: 'Chatbot Leads', href: '/admin/chatbot-leads', badge: adminStats?.unread_chatbot_leads },
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
                { label: 'Work Logs', href: '/employee/work-logs' },
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
            title: 'Account',
            items: [
                { label: 'My Profile', href: '/employee/profile' },
            ],
        },
    ];

    const sidebarContent = !isEmployee ? (
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
                <NavLink
                    href="/admin/orders"
                    active={isActiveRoute(currentUrl, '/admin/orders*')}
                    badge={adminStats?.pending_orders}
                >
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
                    href="/admin/cancellation-requests"
                    active={isActiveRoute(currentUrl, '/admin/cancellation-requests*')}
                    badge={adminStats?.pending_cancellations}
                    badgeColor="bg-rose-100 text-rose-700"
                >
                    Cancellations
                </NavLink>
                <NavLink
                    href="/admin/licenses"
                    active={isActiveRoute(currentUrl, '/admin/licenses*')}
                    badge={adminStats?.active_licenses}
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
                            <a href="/admin/income/categories" data-native="true" className="block py-1 text-slate-300 hover:text-white">Categories</a>
                        </NavMenu>
                        <NavMenu label="Expenses" active={isActiveRoute(currentUrl, '/admin/expenses*')}>
                            <a href="/admin/expenses" data-native="true" className="block py-1 text-slate-300 hover:text-white">All expenses</a>
                            <a href="/admin/expenses/create" data-native="true" className="block py-1 text-slate-300 hover:text-white">One-time expense</a>
                            <a href="/admin/expenses/recurring" data-native="true" className="block py-1 text-slate-300 hover:text-white">Recurring expense</a>
                            <a href="/admin/expenses/categories" data-native="true" className="block py-1 text-slate-300 hover:text-white">Expense Categories</a>
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
                <NavLink href="/admin/hr/work-logs" active={isActiveRoute(currentUrl, ['/admin/hr/work-logs*', '/admin/hr/timesheets*'])}>
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
                <NavLink
                    href="/admin/chatbot-leads"
                    active={isActiveRoute(currentUrl, '/admin/chatbot-leads*')}
                    badge={adminStats?.unread_chatbot_leads}
                >
                    Chatbot Leads
                </NavLink>
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
                <NavLink href="/employee/dashboard" active={urlPath === '/employee' || isActiveRoute(currentUrl, '/employee/dashboard')}>
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
                {isEmployeeWorkSessionEligible && (
                    <NavLink href="/employee/work-logs" active={isActiveRoute(currentUrl, ['/employee/work-logs*', '/employee/timesheets*'])}>
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
    );

    return (
        <MobileAppShell
            portalKey="admin"
            portalLabel={isEmployee ? 'Employee' : 'Admin'}
            companyName={companyName}
            logoUrl={logoUrl}
            brandInitials="LM"
            sidebarContent={sidebarContent}
            sidebarExtra={isEmployee && isEmployeeWorkSessionEligible ? <GlobalWorkTimer /> : null}
            title={title}
            pageHeading={pageHeading}
            user={user}
            roleLabel={roleLabel}
            profileRoute={profileRoute}
            branding={branding}
            navItems={isEmployee ? employeeNavItems : adminNavItems}
            headerStats={!isEmployee ? headerStats : []}
            moreSections={isEmployee ? employeeMoreSections : adminMoreSections}
            moreTitle={isEmployee ? 'Employee Navigation' : 'All Features & Tools'}
            moreDescription={`Access all ${isEmployee ? 'work' : 'management'} modules`}
        >
            {children}
        </MobileAppShell>
    );
}
