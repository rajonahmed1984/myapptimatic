import React from 'react';
import { usePage } from '@inertiajs/react';
import PageHeader from './PageHeader';
import { getBreadcrumb, getPageTitle } from '../utils/pageTitle';

const parsePathname = (value) => {
    const raw = String(value || '').trim();
    if (!raw) {
        return window.location.pathname;
    }

    try {
        return new URL(raw, window.location.origin).pathname;
    } catch (error) {
        return raw.split('?')[0].split('#')[0] || window.location.pathname;
    }
};

export default function PortalPageHeader() {
    const page = usePage();
    const pageProps = page?.props || {};
    const pathname = parsePathname(page?.url || pageProps?.page?.url || window.location.pathname);
    const routeName = pageProps?.page?.route_name || '';
    const title = getPageTitle({
        component: page?.component,
        props: pageProps,
        routeName,
        pathname,
    });
    const breadcrumb = getBreadcrumb({
        routeName,
        pathname,
        title,
        portal: pageProps?.auth?.portal || '',
    });

    return <PageHeader title={title} breadcrumb={breadcrumb} />;
}
