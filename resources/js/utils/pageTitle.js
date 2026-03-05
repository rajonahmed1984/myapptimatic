const PORTAL_LABELS = {
    admin: 'Admin',
    client: 'Client',
    employee: 'Employee',
    rep: 'Sales Rep',
    support: 'Support',
    public: 'Public',
    guest: 'Guest',
};

const ROUTE_TITLE_MAP = {
    'admin.dashboard': 'Admin Dashboard',
    'admin.income.dashboard': 'Income Dashboard',
    'admin.expenses.dashboard': 'Expenses Dashboard',
    'admin.hr.dashboard': 'HR Dashboard',
    'client.dashboard': 'Client Dashboard',
    'employee.dashboard': 'Employee Dashboard',
    'rep.dashboard': 'Sales Dashboard',
    'support.dashboard': 'Support Dashboard',
};

const ROUTE_PREFIX_TITLE_MAP = {
    'admin.income.': 'Income',
    'admin.expenses.': 'Expenses',
    'admin.accounting.': 'Accounting',
    'admin.projects.': 'Projects',
    'admin.project-maintenances.': 'Project Maintenance',
    'admin.subscriptions.': 'Subscriptions',
    'admin.customers.': 'Customers',
    'admin.orders.': 'Orders',
    'admin.invoices.': 'Invoices',
    'admin.hr.': 'HR',
    'admin.tasks.': 'Tasks',
    'admin.support-tickets.': 'Support Tickets',
    'client.projects.': 'Projects',
    'client.services.': 'Services',
    'client.support-tickets.': 'Support Tickets',
    'client.invoices.': 'Invoices',
    'client.tasks.': 'Tasks',
    'client.orders.': 'Orders',
    'employee.projects.': 'Projects',
    'employee.tasks.': 'Tasks',
    'employee.payroll.': 'Payroll',
    'employee.timesheets.': 'Timesheets',
    'rep.projects.': 'Projects',
    'rep.tasks.': 'Tasks',
    'rep.earnings.': 'Earnings',
    'rep.payouts.': 'Payouts',
    'support.support-tickets.': 'Support Tickets',
    'products.public.': 'Products',
};

const KNOWN_SEGMENTS = {
    hr: 'HR',
    ai: 'AI',
    id: 'ID',
    api: 'API',
    ui: 'UI',
    faq: 'FAQ',
    sms: 'SMS',
};

const isNonEmptyString = (value) => typeof value === 'string' && value.trim() !== '';

const cleanText = (value) => String(value || '')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const titleCaseWord = (word) => {
    const lower = String(word || '').toLowerCase();
    if (KNOWN_SEGMENTS[lower]) {
        return KNOWN_SEGMENTS[lower];
    }

    if (lower === 'index' || lower === 'dashboard') {
        return lower === 'dashboard' ? 'Dashboard' : '';
    }

    if (/^\d+$/.test(lower)) {
        return '';
    }

    return lower.charAt(0).toUpperCase() + lower.slice(1);
};

const toTitleCase = (value) => cleanText(value)
    .split(' ')
    .map(titleCaseWord)
    .filter(Boolean)
    .join(' ')
    .trim();

const normalizePathname = (pathname) => {
    const raw = String(pathname || '/');
    const pathOnly = raw.split('?')[0].split('#')[0] || '/';
    return pathOnly.replace(/\/+/g, '/').replace(/\/$/, '') || '/';
};

const extractPortalFromPathname = (pathname) => {
    const first = normalizePathname(pathname).split('/').filter(Boolean)[0] || '';
    if (PORTAL_LABELS[first]) {
        return first;
    }

    if (first === 'products') {
        return 'public';
    }

    return 'guest';
};

const titleFromRouteName = (routeName) => {
    const name = String(routeName || '').trim();
    if (!name) {
        return '';
    }

    if (ROUTE_TITLE_MAP[name]) {
        return ROUTE_TITLE_MAP[name];
    }

    const prefix = Object.keys(ROUTE_PREFIX_TITLE_MAP).find((entry) => name.startsWith(entry));
    if (!prefix) {
        return '';
    }

    const base = ROUTE_PREFIX_TITLE_MAP[prefix];
    const remainder = name.slice(prefix.length).replace(/\./g, ' ');
    const suffix = toTitleCase(remainder);
    return suffix ? `${base} / ${suffix}` : base;
};

const titleFromPathname = (pathname) => {
    const parts = normalizePathname(pathname).split('/').filter(Boolean);
    if (parts.length === 0) {
        return 'Overview';
    }

    const filtered = parts.filter((part) => !PORTAL_LABELS[part]);
    if (filtered.length === 0) {
        return 'Overview';
    }

    const last = filtered[filtered.length - 1];
    const prev = filtered.length > 1 ? filtered[filtered.length - 2] : '';
    if (/^\d+$/.test(last)) {
        return toTitleCase(prev) || 'Details';
    }

    return toTitleCase(last) || 'Overview';
};

const titleFromComponent = (componentName) => {
    const raw = String(componentName || '').trim();
    if (!raw) {
        return '';
    }

    const parts = raw.split('/').filter(Boolean);
    if (parts.length === 0) {
        return '';
    }

    const cleaned = parts
        .filter((part) => !PORTAL_LABELS[part.toLowerCase()])
        .map((part) => toTitleCase(part))
        .filter(Boolean);

    if (cleaned.length === 0) {
        return '';
    }

    const last = cleaned[cleaned.length - 1];
    if (last === 'Index' && cleaned.length > 1) {
        return cleaned[cleaned.length - 2];
    }

    const suffixless = cleaned.filter((part) => part !== 'Index');
    if (suffixless.length === 0) {
        return cleaned[cleaned.length - 1];
    }

    const leaf = suffixless[suffixless.length - 1];
    const hasFormLeaf = ['Create', 'Edit', 'Show', 'Form', 'Details'].includes(leaf);
    if (hasFormLeaf && suffixless.length > 1) {
        return `${suffixless[suffixless.length - 2]} / ${leaf}`;
    }

    return leaf;
};

export const getPageTitle = ({
    component,
    props,
    routeName,
    pathname,
    explicitTitle,
}) => {
    const fromProps = props?.page?.title || props?.pageTitle || props?.title || '';
    if (isNonEmptyString(fromProps)) {
        return cleanText(fromProps);
    }

    if (isNonEmptyString(explicitTitle)) {
        return cleanText(explicitTitle);
    }

    const fromRoute = titleFromRouteName(routeName);
    if (isNonEmptyString(fromRoute)) {
        return fromRoute;
    }

    const fromComponent = titleFromComponent(component);
    if (isNonEmptyString(fromComponent)) {
        return fromComponent;
    }

    return titleFromPathname(pathname);
};

export const getBreadcrumb = ({ routeName, pathname, title, portal }) => {
    const normalizedPath = normalizePathname(pathname);
    const inferredPortal = portal || extractPortalFromPathname(normalizedPath);
    const items = [];

    if (PORTAL_LABELS[inferredPortal]) {
        items.push({
            label: PORTAL_LABELS[inferredPortal],
            href: inferredPortal === 'guest' ? '/' : `/${inferredPortal}`,
        });
    }

    const routeTitle = titleFromRouteName(routeName);
    if (isNonEmptyString(routeTitle)) {
        routeTitle.split('/').map((piece) => cleanText(piece)).filter(Boolean).forEach((piece, idx, arr) => {
            const isLast = idx === arr.length - 1;
            items.push({ label: piece, href: isLast ? null : null });
        });
    } else {
        const parts = normalizedPath.split('/').filter(Boolean).filter((part) => !PORTAL_LABELS[part]);
        const stack = [];
        parts.forEach((part) => {
            if (/^\d+$/.test(part)) {
                return;
            }

            const label = toTitleCase(part);
            if (!label || label === 'Index') {
                return;
            }

            stack.push(part);
            items.push({
                label,
                href: `/${[inferredPortal, ...stack].filter(Boolean).join('/')}`,
            });
        });
    }

    const cleanTitle = cleanText(title);
    if (cleanTitle) {
        const lastLabel = items.length > 0 ? cleanText(items[items.length - 1].label) : '';
        if (lastLabel.toLowerCase() !== cleanTitle.toLowerCase()) {
            items.push({ label: cleanTitle, href: null });
        } else {
            items[items.length - 1] = { ...items[items.length - 1], href: null };
        }
    }

    return items.length > 0 ? items : [{ label: cleanTitle || 'Overview', href: null }];
};
