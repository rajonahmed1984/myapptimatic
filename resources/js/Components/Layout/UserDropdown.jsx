import React, { useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

const initials = (name) => {
    const text = String(name || '').trim();
    if (!text) return 'U';
    const parts = text.split(/\s+/).filter(Boolean);
    return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('');
};

export default function UserDropdown({ user, profileRoute, roleLabel }) {
    const page = usePage();
    const csrfToken = page.props?.csrf_token || (typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : '');
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);

    const userName = user?.name || 'User';
    const userEmail = user?.email || '';
    const userRole = roleLabel || user?.role || 'User';
    const avatarUrl = user?.avatar_url || null;

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, []);

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="group flex items-center gap-2.5 rounded-2xl border border-slate-200/90 bg-white px-2.5 py-1.5 shadow-sm transition-all hover:border-teal-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-teal-500/20 active:scale-[0.98] cursor-pointer"
                aria-expanded={isOpen}
                aria-haspopup="true"
            >
                <div className="h-8 w-8 overflow-hidden rounded-full border border-slate-200 bg-slate-100 flex-shrink-0">
                    {avatarUrl ? (
                        <img src={avatarUrl} alt={userName} className="h-8 w-8 object-cover" />
                    ) : (
                        <div className="grid h-8 w-8 place-items-center bg-slate-200 text-xs font-semibold text-slate-700">
                            {initials(userName)}
                        </div>
                    )}
                </div>
                <div className="hidden sm:block text-left pr-1">
                    <div className="text-xs font-semibold text-slate-800 group-hover:text-teal-700 transition-colors leading-tight truncate max-w-[140px]">
                        {userName}
                    </div>
                    <div className="text-[10px] text-slate-500 font-medium leading-tight truncate max-w-[140px]">
                        {userRole}
                    </div>
                </div>
                <svg
                    className={`h-4 w-4 text-slate-400 group-hover:text-slate-600 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {isOpen && (
                <div
                    className="absolute right-0 mt-2 w-64 origin-top-right rounded-2xl border border-slate-200/90 bg-white p-2 shadow-xl backdrop-blur-md transition-all duration-150 z-50 animate-in fade-in zoom-in-95"
                    role="menu"
                >
                    <div className="rounded-xl bg-slate-50/90 p-3 mb-1 border border-slate-100">
                        <div className="flex items-center gap-3">
                            <div className="h-10 w-10 overflow-hidden rounded-full border border-slate-200 bg-slate-100 flex-shrink-0 shadow-sm">
                                {avatarUrl ? (
                                    <img src={avatarUrl} alt={userName} className="h-10 w-10 object-cover" />
                                ) : (
                                    <div className="grid h-10 w-10 place-items-center bg-slate-200 text-sm font-semibold text-slate-700">
                                        {initials(userName)}
                                    </div>
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="text-xs font-bold text-slate-900 truncate leading-tight">{userName}</div>
                                <div className="text-[11px] text-slate-500 truncate mt-0.5">{userEmail}</div>
                                <span className="mt-1 inline-flex items-center rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold text-teal-700 border border-teal-200/60">
                                    {userRole}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="py-1 space-y-0.5 text-xs text-slate-700">
                        {profileRoute && profileRoute !== '#' && (
                            <>
                                <a
                                    href={profileRoute}
                                    data-native="true"
                                    onClick={() => setIsOpen(false)}
                                    className="group/item flex items-center gap-2.5 rounded-xl px-3 py-2 text-slate-700 transition hover:bg-teal-50 hover:text-teal-700 font-medium"
                                    role="menuitem"
                                >
                                    <svg className="h-4 w-4 text-slate-400 group-hover/item:text-teal-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span>My Profile</span>
                                </a>

                                <a
                                    href={`${profileRoute}#password-settings`}
                                    data-native="true"
                                    onClick={() => setIsOpen(false)}
                                    className="group/item flex items-center gap-2.5 rounded-xl px-3 py-2 text-slate-700 transition hover:bg-teal-50 hover:text-teal-700 font-medium"
                                    role="menuitem"
                                >
                                    <svg className="h-4 w-4 text-slate-400 group-hover/item:text-teal-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                    </svg>
                                    <span>Change Password</span>
                                </a>
                            </>
                        )}
                    </div>

                    <div className="my-1 border-t border-slate-100" />

                    <form method="POST" action="/logout" className="m-0">
                        {csrfToken && <input type="hidden" name="_token" value={csrfToken} />}
                        <button
                            type="submit"
                            className="group/logout flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 text-left cursor-pointer"
                            role="menuitem"
                        >
                            <svg className="h-4 w-4 text-rose-500 group-hover/logout:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Sign out</span>
                        </button>
                    </form>
                </div>
            )}
        </div>
    );
}
