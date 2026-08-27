import React from 'react';
import { usePage } from '@inertiajs/react';

export default function ImpersonationBanner() {
    const page = usePage();
    const isImpersonating = page.props?.auth?.is_impersonating;
    const userName = page.props?.auth?.user?.name || 'client';
    const csrfToken = page.props?.csrf_token || (typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : '');

    if (!isImpersonating) {
        return null;
    }

    return (
        <div className="w-full px-6 pb-3">
            <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                <div className="text-[11px] uppercase tracking-[0.28em] text-amber-600 font-semibold">Impersonation</div>
                <div className="text-sm text-amber-800">
                    You are logged in as <span className="font-semibold">{userName}</span>. Actions are performed on behalf of this account.
                </div>
                <form method="POST" action="/impersonate/stop" className="ml-auto m-0">
                    {csrfToken && <input type="hidden" name="_token" value={csrfToken} />}
                    <button
                        type="submit"
                        className="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-semibold text-amber-700 transition hover:border-amber-400 hover:bg-amber-100 cursor-pointer"
                    >
                        Return to Admin
                    </button>
                </form>
            </div>
        </div>
    );
}
