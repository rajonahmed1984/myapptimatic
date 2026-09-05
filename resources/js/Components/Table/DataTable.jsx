import React from 'react';

/**
 * Shared responsive list: a desktop <table> (columns/rows) at md and above,
 * and a caller-supplied card renderer (typically MobileCard) below it — driven
 * by the same row data, so there is one place that decides what a row is.
 *
 * This replaces the pattern most Admin/Client/Rep/Support list pages hand-roll
 * today (a `md:hidden` card block next to a `hidden md:block` table block):
 * see Admin/Customers/Index.jsx for the reference usage.
 */
export default function DataTable({
    columns = [],
    rows = [],
    rowKey = (row) => row.id,
    renderMobileCard,
    emptyMessage = 'Nothing to show yet.',
    className = '',
}) {
    return (
        <>
            {/* Mobile Cards List (<md) */}
            <div className="md:hidden space-y-3">
                {rows.length === 0 ? (
                    <div className="card p-6 text-center text-sm text-slate-500">{emptyMessage}</div>
                ) : (
                    rows.map((row, index) => (
                        <React.Fragment key={rowKey(row, index)}>{renderMobileCard(row)}</React.Fragment>
                    ))
                )}
            </div>

            {/* Desktop Table (>=md) */}
            <div className={`hidden md:block card overflow-hidden ${className}`}>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                {columns.map((col) => (
                                    <th key={col.key} className={`px-4 py-3 whitespace-nowrap ${col.headerClassName || ''}`}>
                                        {col.header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={columns.length} className="px-4 py-6 text-center text-slate-500 whitespace-nowrap">
                                        {emptyMessage}
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row, index) => (
                                    <tr key={rowKey(row, index)} className="border-b border-slate-100 transition hover:bg-slate-50/60">
                                        {columns.map((col) => (
                                            <td key={col.key} className={`px-4 py-3 whitespace-nowrap ${col.cellClassName || ''}`}>
                                                {col.render(row)}
                                            </td>
                                        ))}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
