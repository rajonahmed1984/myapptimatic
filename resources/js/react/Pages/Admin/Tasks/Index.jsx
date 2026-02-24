import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ pageTitle = 'Tasks', table_html = '' }) {
    return (
        <>
            <Head title={pageTitle} />
            <div dangerouslySetInnerHTML={{ __html: table_html }} />
        </>
    );
}
