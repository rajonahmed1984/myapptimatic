import React, { useState } from 'react';
import MobileBottomSheet from './MobileBottomSheet';

/**
 * A "Filter" button that opens the given fields in a bottom sheet instead of
 * an always-visible inline filter row — the row that's fine as a strip
 * above a desktop table but crowds a phone screen. Mobile-only: pair it with
 * the existing inline filter form for desktop (hidden md:hidden on this,
 * hidden on md: on that).
 */
export default function FilterSheet({ title = 'Filters', activeCount = 0, onApply, onClear, children }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm active:scale-95 transition"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18M6 9h12M10 15h4" />
                </svg>
                Filters
                {activeCount > 0 ? (
                    <span className="inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-teal-600 px-1 text-[10px] font-bold text-white">{activeCount}</span>
                ) : null}
            </button>

            <MobileBottomSheet
                isOpen={open}
                onClose={() => setOpen(false)}
                title={title}
                footer={
                    <div className="flex items-center gap-3">
                        {onClear ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setOpen(false);
                                    onClear();
                                }}
                                className="flex-1 rounded-xl border border-slate-300 py-2.5 text-sm font-semibold text-slate-700 active:scale-[0.99] transition"
                            >
                                Clear
                            </button>
                        ) : null}
                        <button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                onApply?.();
                            }}
                            className="flex-1 rounded-xl bg-teal-600 py-2.5 text-sm font-bold text-white active:scale-[0.99] transition"
                        >
                            Apply
                        </button>
                    </div>
                }
            >
                <div className="space-y-4">{children}</div>
            </MobileBottomSheet>
        </>
    );
}
