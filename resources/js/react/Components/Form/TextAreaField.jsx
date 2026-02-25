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
                    'mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200',
                    error ? 'border-rose-300' : '',
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
