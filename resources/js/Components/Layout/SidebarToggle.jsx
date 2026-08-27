import React from 'react';

export default function SidebarToggle({ onToggleMobile }) {
    const handleToggle = () => {
        if (typeof window === 'undefined') return;

        if (window.innerWidth >= 768) {
            const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
            document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
            try {
                localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
            } catch (e) {}
        } else {
            if (typeof onToggleMobile === 'function') {
                onToggleMobile();
            }
        }
    };

    return (
        <button
            type="button"
            id="sidebarToggle"
            onClick={handleToggle}
            className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200/90 bg-white text-slate-600 shadow-sm transition-all hover:border-teal-300 hover:bg-slate-50 hover:text-teal-600 active:scale-95 cursor-pointer flex-shrink-0"
            aria-label="Toggle sidebar"
            title="Toggle sidebar"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                className="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <rect width="18" height="18" x="3" y="3" rx="3" />
                <path d="M9 3v18" />
            </svg>
        </button>
    );
}
