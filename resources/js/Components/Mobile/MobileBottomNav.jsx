import React from 'react';

export default function MobileBottomNav({ items = [], className = '' }) {
    if (!items || items.length === 0) return null;

    return (
        <nav
            aria-label="Mobile Navigation"
            className={`fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 mobile-bottom-nav md:hidden shadow-[0_-4px_20px_rgba(0,0,0,0.05)] ${className}`}
        >
            <div className="grid h-16 grid-flow-col auto-cols-fr items-center px-1">
                {items.map((item, idx) => {
                    const isActive = Boolean(item.active);
                    const hasBadge = item.badge !== undefined && item.badge !== null && Number(item.badge) > 0;

                    const content = (
                        <div className="flex flex-col items-center justify-center py-1 relative select-none">
                            <div
                                className={`relative flex items-center justify-center w-10 h-8 rounded-full transition-all duration-200 ${
                                    isActive
                                        ? 'bg-teal-50 text-teal-600 font-bold'
                                        : 'text-slate-500 hover:text-slate-800'
                                }`}
                            >
                                {item.icon ? (
                                    typeof item.icon === 'function' ? item.icon({ active: isActive, className: 'w-5 h-5' }) : item.icon
                                ) : (
                                    <span className={`w-2 h-2 rounded-full ${isActive ? 'bg-teal-600' : 'bg-slate-400'}`} />
                                )}

                                {hasBadge && (
                                    <span
                                        className={`absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full flex items-center justify-center text-[10px] font-extrabold shadow-sm ${
                                            item.badgeColor || 'bg-rose-500 text-white'
                                        }`}
                                    >
                                        {Number(item.badge) > 99 ? '99+' : item.badge}
                                    </span>
                                )}
                            </div>
                            <span
                                className={`text-[10px] tracking-tight leading-none mt-0.5 truncate max-w-[68px] ${
                                    isActive ? 'font-bold text-teal-700' : 'font-medium text-slate-500'
                                }`}
                            >
                                {item.label}
                            </span>
                        </div>
                    );

                    if (item.onClick) {
                        return (
                            <button
                                key={item.label || idx}
                                type="button"
                                onClick={item.onClick}
                                className="w-full flex items-center justify-center rounded-xl transition-transform active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-1 motion-reduce:transition-none motion-reduce:active:scale-100"
                                aria-label={item.label}
                                aria-current={isActive ? 'page' : undefined}
                            >
                                {content}
                            </button>
                        );
                    }

                    return (
                        <a
                            key={item.label || idx}
                            href={item.href}
                            data-native="true"
                            className="w-full flex items-center justify-center rounded-xl transition-transform active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-1 motion-reduce:transition-none motion-reduce:active:scale-100"
                            aria-label={item.label}
                            aria-current={isActive ? 'page' : undefined}
                        >
                            {content}
                        </a>
                    );
                })}
            </div>
        </nav>
    );
}
