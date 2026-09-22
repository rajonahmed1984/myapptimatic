import React from 'react';
import { router } from '@inertiajs/react';

/**
 * Numbered pager for a Laravel LengthAwarePaginator.
 *
 * Expects the payload built by the controller:
 *   { current_page, last_page, per_page, total, from, to, path, query }
 * where `query` is the current query string (search etc.) so every page link
 * keeps the active filters. Navigation goes through Inertia, keeping the
 * layout mounted instead of reloading the whole page.
 */
const buildPages = (current, last) => {
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const pages = new Set([1, 2, last - 1, last, current - 1, current, current + 1]);
    const sorted = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);

    const withGaps = [];
    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) {
            withGaps.push(`gap-${page}`);
        }
        withGaps.push(page);
    });

    return withGaps;
};

export default function Pagination({ pagination = {}, label = 'results', className = '' }) {
    const current = Number(pagination.current_page || 1);
    const last = Number(pagination.last_page || 1);
    const perPage = Number(pagination.per_page || 15);
    const total = Number(pagination.total || 0);

    const from = pagination.from !== undefined && pagination.from !== null
        ? pagination.from
        : (total > 0 ? (current - 1) * perPage + 1 : 0);
    const to = pagination.to !== undefined && pagination.to !== null
        ? pagination.to
        : (total > 0 ? Math.min(current * perPage, total) : 0);

    const go = (page) => {
        if (page < 1 || page > last || page === current) {
            return;
        }

        const query = { ...(pagination.query || {}) };
        if (page === 1) {
            delete query.page;
        } else {
            query.page = page;
        }

        router.get(pagination.path || window.location.pathname, query, {
            preserveState: true,
            preserveScroll: false,
        });
    };

    const base = 'inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2.5 text-xs font-semibold transition';
    const idle = 'border-slate-200 bg-white text-slate-600 hover:border-teal-300 hover:text-teal-600';
    const active = 'border-teal-600 bg-teal-600 text-white';
    const disabled = 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300';

    return (
        <div className={`flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between ${className}`}>
            <div className="text-xs text-slate-500">
                {total > 0 ? (
                    <>
                        Showing <span className="font-semibold text-slate-700">{from}</span>
                        {' – '}
                        <span className="font-semibold text-slate-700">{to}</span> of{' '}
                        <span className="font-semibold text-slate-700">{total}</span> {label}
                    </>
                ) : (
                    <>No {label}</>
                )}
            </div>

            {last > 1 ? (
                <nav className="flex flex-wrap items-center gap-1" aria-label="Pagination">
                    <button type="button" onClick={() => go(1)} disabled={current === 1} className={`${base} ${current === 1 ? disabled : idle}`} aria-label="First page">
                        «
                    </button>
                    <button type="button" onClick={() => go(current - 1)} disabled={current === 1} className={`${base} ${current === 1 ? disabled : idle}`}>
                        Prev
                    </button>
                    {buildPages(current, last).map((page) =>
                        typeof page === 'string' ? (
                            <span key={page} className="px-1 text-xs text-slate-400">…</span>
                        ) : (
                            <button
                                key={page}
                                type="button"
                                onClick={() => go(page)}
                                aria-current={page === current ? 'page' : undefined}
                                className={`${base} ${page === current ? active : idle}`}
                            >
                                {page}
                            </button>
                        )
                    )}
                    <button type="button" onClick={() => go(current + 1)} disabled={current === last} className={`${base} ${current === last ? disabled : idle}`}>
                        Next
                    </button>
                    <button type="button" onClick={() => go(last)} disabled={current === last} className={`${base} ${current === last ? disabled : idle}`} aria-label="Last page">
                        »
                    </button>
                </nav>
            ) : null}
        </div>
    );
}
