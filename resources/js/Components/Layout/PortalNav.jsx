import React, { useState } from 'react';

/**
 * Shared sidebar-nav primitives used by every portal layout (Admin, Client,
 * Sales Rep, Support). Extracted because all four layouts previously defined
 * byte-for-byte identical copies of these three helpers.
 */
export const isActiveRoute = (currentUrl, patterns) => {
    if (!currentUrl) return false;
    const urlPath = currentUrl.split('?')[0].split('#')[0];
    const list = Array.isArray(patterns) ? patterns : [patterns];
    return list.some((p) => {
        if (!p) return false;
        if (p === urlPath) return true;
        if (p.endsWith('*')) {
            return urlPath.startsWith(p.slice(0, -1));
        }
        return urlPath === p || urlPath.startsWith(`${p}/`);
    });
};

export function NavLink({ href, active, badge, badgeColor = 'bg-amber-100 text-amber-900', children }) {
    return (
        <a
            href={href}
            data-native="true"
            className={active ? 'nav-link nav-link-active' : 'nav-link'}
        >
            <span className="h-2 w-2 rounded-full bg-current flex-shrink-0" />
            <span className="truncate">{children}</span>
            {badge !== undefined && badge !== null && Number(badge) > 0 && (
                <span className={`ml-auto rounded-full px-2 py-0.5 text-xs font-semibold ${badgeColor}`}>
                    {badge}
                </span>
            )}
        </a>
    );
}

export function NavMenu({ label, active, children, defaultOpen = true }) {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <div className="space-y-1">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className={`nav-link w-full justify-between cursor-pointer ${active ? 'nav-link-active' : ''}`}
            >
                <div className="flex items-center gap-3">
                    <span className="h-2 w-2 rounded-full bg-current flex-shrink-0" />
                    <span>{label}</span>
                </div>
                <svg
                    className={`h-4 w-4 transform transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            {isOpen && <div className="ml-5 space-y-1 border-l border-slate-700/60 pl-3 text-xs">{children}</div>}
        </div>
    );
}
