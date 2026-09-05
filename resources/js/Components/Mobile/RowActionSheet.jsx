import React, { useState } from 'react';
import MobileBottomSheet from './MobileBottomSheet';

/**
 * Replaces a "..." dropdown menu (built for a mouse) with a bottom sheet on
 * mobile. Pass a list of { label, href, onClick, tone } actions; anything
 * with tone: 'danger' renders in rose so destructive actions stay visually
 * distinct even in a plain list.
 */
export default function RowActionSheet({ trigger, title = 'Actions', actions = [] }) {
    const [open, setOpen] = useState(false);
    const visibleActions = actions.filter(Boolean);

    if (visibleActions.length === 0) {
        return null;
    }

    return (
        <>
            <button
                type="button"
                onClick={(event) => {
                    event.stopPropagation();
                    setOpen(true);
                }}
                className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-800 active:scale-95 transition"
                aria-label="More actions"
            >
                {trigger || (
                    <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 100-4 2 2 0 000 4zm0 6a2 2 0 100-4 2 2 0 000 4zm0 6a2 2 0 100-4 2 2 0 000 4z" />
                    </svg>
                )}
            </button>

            <MobileBottomSheet isOpen={open} onClose={() => setOpen(false)} title={title}>
                <div className="space-y-1 py-1">
                    {visibleActions.map((action, index) =>
                        action.href ? (
                            <a
                                key={action.label || index}
                                href={action.href}
                                data-native="true"
                                onClick={() => setOpen(false)}
                                className={`block rounded-xl px-3 py-3 text-sm font-semibold transition active:scale-[0.99] ${
                                    action.tone === 'danger' ? 'text-rose-600 hover:bg-rose-50' : 'text-slate-800 hover:bg-slate-50'
                                }`}
                            >
                                {action.label}
                            </a>
                        ) : action.form ? (
                            <form
                                key={action.label || index}
                                method="POST"
                                action={action.form.action}
                                data-native="true"
                                onSubmit={(event) => {
                                    setOpen(false);
                                    if (action.confirm && !window.confirm(action.confirm)) {
                                        event.preventDefault();
                                    }
                                }}
                            >
                                {action.form.token ? <input type="hidden" name="_token" value={action.form.token} /> : null}
                                {action.form.method && action.form.method !== 'POST' ? <input type="hidden" name="_method" value={action.form.method} /> : null}
                                {Object.entries(action.form.fields || {}).map(([name, value]) => (
                                    <input key={name} type="hidden" name={name} value={value} />
                                ))}
                                <button
                                    type="submit"
                                    className={`block w-full rounded-xl px-3 py-3 text-left text-sm font-semibold transition active:scale-[0.99] ${
                                        action.tone === 'danger' ? 'text-rose-600 hover:bg-rose-50' : 'text-slate-800 hover:bg-slate-50'
                                    }`}
                                >
                                    {action.label}
                                </button>
                            </form>
                        ) : (
                            <button
                                key={action.label || index}
                                type="button"
                                onClick={() => {
                                    setOpen(false);
                                    action.onClick?.();
                                }}
                                className={`block w-full rounded-xl px-3 py-3 text-left text-sm font-semibold transition active:scale-[0.99] ${
                                    action.tone === 'danger' ? 'text-rose-600 hover:bg-rose-50' : 'text-slate-800 hover:bg-slate-50'
                                }`}
                            >
                                {action.label}
                            </button>
                        )
                    )}
                </div>
            </MobileBottomSheet>
        </>
    );
}
