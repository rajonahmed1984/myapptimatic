import React from 'react';

export default function MobileCard({
    title,
    subtitle,
    avatar,
    badge,
    badgeColor,
    metrics = [],
    actions = null,
    href = null,
    onClick = null,
    children,
    className = '',
}) {
    const isInteractive = Boolean(href || onClick);

    const CardWrapper = href ? 'a' : onClick ? 'button' : 'div';
    const wrapperProps = href
        ? { href, 'data-native': 'true' }
        : onClick
        ? { type: 'button', onClick }
        : {};

    return (
        <CardWrapper
            {...wrapperProps}
            className={`block w-full text-left rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm transition-all duration-150 ${
                isInteractive ? 'hover:border-teal-300 hover:shadow-md active:scale-[0.99] cursor-pointer' : ''
            } ${className}`}
        >
            {/* Header Row */}
            {(title || avatar || badge) && (
                <div className="flex items-start justify-between gap-3 mb-2.5">
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                        {avatar && (
                            <div className="shrink-0">{avatar}</div>
                        )}
                        <div className="min-w-0 flex-1">
                            {title && (
                                <div className="text-sm font-bold text-slate-900 truncate leading-snug">
                                    {title}
                                </div>
                            )}
                            {subtitle && (
                                <div className="text-xs text-slate-500 truncate mt-0.5">
                                    {subtitle}
                                </div>
                            )}
                        </div>
                    </div>

                    {badge && (
                        <span
                            className={`shrink-0 inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider ${
                                badgeColor || 'border-slate-200 bg-slate-100 text-slate-700'
                            }`}
                        >
                            {badge}
                        </span>
                    )}
                </div>
            )}

            {/* Metrics Chips Grid */}
            {metrics.length > 0 && (
                <div className="grid grid-cols-2 gap-2 my-2.5 py-2 border-y border-slate-100/90 text-xs">
                    {metrics.map((m, idx) => (
                        <div key={idx} className="min-w-0">
                            <span className="text-[10px] uppercase tracking-wider text-slate-400 font-semibold block truncate">
                                {m.label}
                            </span>
                            <span className={`text-sm font-bold truncate block mt-0.5 ${m.tone || 'text-slate-800'}`}>
                                {m.value}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            {/* Custom Children Body */}
            {children && <div className="text-sm text-slate-600 mt-1">{children}</div>}

            {/* Footer Actions */}
            {actions && (
                <div className="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-end gap-2" onClick={(e) => e.stopPropagation()}>
                    {actions}
                </div>
            )}
        </CardWrapper>
    );
}
