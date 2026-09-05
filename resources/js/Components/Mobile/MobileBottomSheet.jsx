import React, { useEffect } from 'react';

export default function MobileBottomSheet({
    isOpen = false,
    onClose,
    title = '',
    description = '',
    children,
    footer = null,
    maxHeight = 'max-h-[88vh]',
    className = '',
}) {
    useEffect(() => {
        if (!isOpen) return;

        const originalOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                onClose?.();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => {
            document.body.style.overflow = originalOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex flex-col justify-end md:hidden">
            {/* Backdrop */}
            <div
                onClick={onClose}
                className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-200"
                aria-hidden="true"
            />

            {/* Bottom Sheet Container */}
            <div
                role="dialog"
                aria-modal="true"
                className={`relative z-10 w-full flex flex-col rounded-t-[28px] border-t border-slate-200 bg-white shadow-2xl transition-transform duration-300 ease-out safe-area-bottom ${maxHeight} ${className}`}
            >
                {/* Drag Handle Pill */}
                <div className="flex justify-center pt-3 pb-1 cursor-grab" onClick={onClose}>
                    <div className="h-1.5 w-12 rounded-full bg-slate-300 hover:bg-slate-400 transition" />
                </div>

                {/* Header */}
                {(title || onClose) && (
                    <div className="flex items-center justify-between px-5 py-2.5 border-b border-slate-100">
                        <div className="min-w-0 flex-1">
                            {title && (
                                <h2 className="text-base font-bold text-slate-900 truncate">
                                    {title}
                                </h2>
                            )}
                            {description && (
                                <p className="text-xs text-slate-500 truncate mt-0.5">
                                    {description}
                                </p>
                            )}
                        </div>

                        {onClose && (
                            <button
                                type="button"
                                onClick={onClose}
                                className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition active:scale-95"
                                aria-label="Close sheet"
                            >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        )}
                    </div>
                )}

                {/* Scrollable Content */}
                <div className="flex-1 overflow-y-auto px-5 py-4 momentum-scroll text-slate-700">
                    {children}
                </div>

                {/* Optional Sticky Footer */}
                {footer && (
                    <div className="border-t border-slate-100 bg-slate-50 px-5 py-3.5">
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
}
