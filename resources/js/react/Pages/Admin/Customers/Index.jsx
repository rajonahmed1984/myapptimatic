import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ pageTitle = 'Customers', search = '', routes = {}, table_html = '' }) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form method="GET" action={routes?.index} data-native="true" className="flex items-center gap-3">
                        <div className="relative">
                            <input
                                type="text"
                                name="search"
                                defaultValue={search}
                                placeholder="Search customers..."
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Search
                        </button>
                    </form>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white"
                >
                    New Customer
                </a>
            </div>

            <div dangerouslySetInnerHTML={{ __html: table_html }} />
        </>
    );
}
