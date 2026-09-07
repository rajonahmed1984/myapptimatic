import React, { useState } from 'react';
import ErrorMessage from './ErrorMessage';

export default function InputField({
    label = null,
    name,
    type = 'text',
    defaultValue = '',
    required = false,
    placeholder = '',
    autoComplete,
    className = '',
    inputClassName = '',
    error = null,
    disablePasswordToggle = false,
    ...props
}) {
    const isPassword = type === 'password' && !disablePasswordToggle;
    const [showPassword, setShowPassword] = useState(false);

    const inputNode = (
        <input
            type={isPassword ? (showPassword ? 'text' : 'password') : type}
            name={name}
            defaultValue={defaultValue}
            placeholder={placeholder}
            required={required}
            autoComplete={autoComplete}
            className={[
                'w-full h-9 rounded-[10px] border border-slate-300 bg-white px-4 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-600',
                !isPassword ? 'mt-2' : '',
                isPassword ? 'pr-10' : '',
                error ? 'border-rose-500 focus:ring-rose-500' : '',
                inputClassName,
            ]
                .filter(Boolean)
                .join(' ')}
            {...props}
        />
    );

    return (
        <div className={className}>
            {label ? <label className="text-sm text-slate-200/85">{label}</label> : null}
            {isPassword ? (
                <div className="relative mt-2">
                    {inputNode}
                    <button
                        type="button"
                        tabIndex={-1}
                        onClick={() => setShowPassword((prev) => !prev)}
                        aria-label={showPassword ? 'Hide password' : 'Show password'}
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
                            {showPassword ? <path d="M3 21L21 3" /> : null}
                        </svg>
                    </button>
                </div>
            ) : (
                inputNode
            )}
            <ErrorMessage message={error} />
        </div>
    );
}
