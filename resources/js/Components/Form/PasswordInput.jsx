import React, { useState } from 'react';

export default function PasswordInput({
    className = 'ui-input',
    wrapperClassName = '',
    inputClassName = '',
    ...props
}) {
    const [show, setShow] = useState(false);

    const baseInputClass = [
        className || inputClassName || 'ui-input',
        'pr-10',
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div className={`relative ${wrapperClassName}`}>
            <input
                {...props}
                type={show ? 'text' : 'password'}
                className={baseInputClass}
            />
            <button
                type="button"
                tabIndex={-1}
                onClick={() => setShow((prev) => !prev)}
                aria-label={show ? 'Hide password' : 'Show password'}
                className="absolute right-2.5 top-1/2 -translate-y-1/2 inline-flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition hover:text-slate-700 hover:bg-slate-100/80 focus:outline-none"
            >
                <svg
                    className="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <path d="M1.5 12s4.5-7.5 10.5-7.5S22.5 12 22.5 12 18 19.5 12 19.5 1.5 12 1.5 12z" />
                    <circle cx="12" cy="12" r="3.2" />
                    {show ? <path d="M3 21L21 3" /> : null}
                </svg>
            </button>
        </div>
    );
}
