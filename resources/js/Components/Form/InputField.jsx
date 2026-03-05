import React from 'react';
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
    ...props
}) {
    return (
        <div className={className}>
            {label ? <label className="text-sm text-slate-200/85">{label}</label> : null}
            <input
                type={type}
                name={name}
                defaultValue={defaultValue}
                placeholder={placeholder}
                required={required}
                autoComplete={autoComplete}
                className={[
                    'mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200',
                    error ? 'border-rose-300' : '',
                    inputClassName,
                ]
                    .filter(Boolean)
                    .join(' ')}
                {...props}
            />
            <ErrorMessage message={error} />
        </div>
    );
}
