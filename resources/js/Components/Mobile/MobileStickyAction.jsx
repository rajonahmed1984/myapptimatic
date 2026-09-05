import React from 'react';

export default function MobileStickyAction({
    children,
    aboveBottomNav = false,
    className = '',
}) {
    // If displayed while bottom navigation is also visible, dock above it. Otherwise dock to bottom edge.
    const bottomClass = aboveBottomNav
        ? 'bottom-[calc(4rem+env(safe-area-inset-bottom,0px))]'
        : 'bottom-0 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]';

    return (
        <div
            className={`fixed inset-x-0 z-30 bg-white/95 backdrop-blur-md border-t border-slate-200/90 px-4 py-3 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] md:hidden transition-all duration-200 ${bottomClass} ${className}`}
        >
            <div className="flex items-center gap-3 w-full max-w-md mx-auto">
                {children}
            </div>
        </div>
    );
}
