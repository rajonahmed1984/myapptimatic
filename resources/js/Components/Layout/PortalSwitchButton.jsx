import React from 'react';
import { usePage } from '@inertiajs/react';

// Shown to a client who is also a sales rep: one login, two portals. The server
// decides whether the link exists (shared `portal_switch` prop).
export default function PortalSwitchButton() {
    const { props } = usePage();
    const portalSwitch = props?.portal_switch;
    const csrfToken = props?.csrf_token || (typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : '');

    if (!portalSwitch?.url) {
        return null;
    }

    return (
        <form method="POST" action={portalSwitch.url} data-native="true" className="mt-6">
            <input type="hidden" name="_token" value={csrfToken || ''} />
            <button
                type="submit"
                className="flex w-full items-center justify-center gap-2 rounded-xl border border-cyan-400/40 bg-cyan-500/15 px-3 py-2 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-500/25 cursor-pointer"
            >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
                </svg>
                {portalSwitch.label}
            </button>
        </form>
    );
}
