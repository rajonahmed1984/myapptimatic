import React, { useState } from 'react';
import MobileTopBar from './MobileTopBar';
import MobileBottomNav from './MobileBottomNav';
import MobileBottomSheet from './MobileBottomSheet';

export default function MobileAppShell({
    title = 'Overview',
    subtitle = '',
    backHref = null,
    onBack = null,
    navItems = [],
    moreSections = [],
    user = null,
    roleLabel = '',
    profileRoute = '',
    branding = {},
    topBarActions = null,
    hideBottomNav = false,
    children,
    className = '',
}) {
    const [isMoreOpen, setIsMoreOpen] = useState(false);

    // If moreSections provided, wire "More" item into navItems if marked with isMore
    const processedNavItems = navItems.map((item) => {
        if (item.isMore) {
            return {
                ...item,
                onClick: () => setIsMoreOpen(true),
            };
        }
        return item;
    });

    return (
        <div className={`min-h-screen flex flex-col w-full ${className}`}>
            {/* Mobile Sticky Top App Bar */}
            <MobileTopBar
                title={title}
                subtitle={subtitle}
                backHref={backHref}
                onBack={onBack}
                actions={topBarActions}
                user={user}
                roleLabel={roleLabel}
                profileRoute={profileRoute}
                branding={branding}
            />

            {/* Content Area with Bottom Nav Padding */}
            <div className={`flex-1 w-full ${!hideBottomNav ? 'pb-safe-nav md:pb-0' : ''}`}>
                {children}
            </div>

            {/* Mobile Bottom Navigation Bar */}
            {!hideBottomNav && (
                <MobileBottomNav items={processedNavItems} />
            )}

            {/* "More" Menu Bottom Sheet for Secondary Navigation */}
            {moreSections.length > 0 && (
                <MobileBottomSheet
                    isOpen={isMoreOpen}
                    onClose={() => setIsMoreOpen(false)}
                    title="Menu & Features"
                    description={`Explore all ${roleLabel || 'system'} tools`}
                >
                    <div className="space-y-6 py-2">
                        {moreSections.map((section, sIdx) => (
                            <div key={section.title || sIdx} className="space-y-2">
                                <div className="text-[11px] font-bold uppercase tracking-[0.2em] text-teal-600 border-b border-slate-100 pb-1">
                                    {section.title}
                                </div>
                                <div className="grid grid-cols-2 gap-2 pt-1">
                                    {section.items.map((item, iIdx) => (
                                        <a
                                            key={item.label || iIdx}
                                            href={item.href}
                                            data-native="true"
                                            className="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/70 hover:bg-teal-50/50 hover:border-teal-200 transition active:scale-98 text-left"
                                        >
                                            {item.icon ? (
                                                <div className="w-8 h-8 rounded-lg bg-white border border-slate-200/90 flex items-center justify-center shrink-0 text-teal-600 shadow-2xs">
                                                    {typeof item.icon === 'function' ? item.icon() : item.icon}
                                                </div>
                                            ) : (
                                                <div className="w-2 h-2 rounded-full bg-teal-500 shrink-0" />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <div className="text-xs font-semibold text-slate-800 truncate">
                                                    {item.label}
                                                </div>
                                                {item.badge && (
                                                    <span className="inline-block mt-0.5 px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-teal-100 text-teal-800">
                                                        {item.badge}
                                                    </span>
                                                )}
                                            </div>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </MobileBottomSheet>
            )}
        </div>
    );
}
