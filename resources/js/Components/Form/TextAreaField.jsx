import React from 'react';
import ErrorMessage from './ErrorMessage';

export default function TextAreaField({
    label = null,
    name,
    defaultValue = '',
    rows = 2,
    className = '',
    textAreaClassName = '',
    error = null,
    ...props
}) {
    return (
        <div className={className}>
            {label ? <label className="text-sm text-slate-200/85">{label}</label> : null}
            <textarea
                name={name}
                defaultValue={defaultValue}
                rows={rows}
                className={[
                    'mt-2 w-full rounded-full border border-slate-300 bg-white px-4 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-600',
                    error ? 'border-rose-500 focus:ring-rose-500' : '',
                    textAreaClassName,
                ]
                    .filter(Boolean)
                    .join(' ')}
                {...props}
            />
            <ErrorMessage message={error} />
        </div>
    );
}
