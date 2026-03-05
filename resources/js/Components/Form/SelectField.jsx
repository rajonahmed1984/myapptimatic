import React from 'react';
import ErrorMessage from './ErrorMessage';

export default function SelectField({
    label = null,
    name,
    defaultValue = '',
    options = [],
    className = '',
    selectClassName = '',
    error = null,
    ...props
}) {
    return (
        <div className={className}>
            {label ? <label className="text-sm text-slate-200/85">{label}</label> : null}
            <select
                name={name}
                defaultValue={defaultValue}
                className={[
                    'mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200',
                    error ? 'border-rose-300' : '',
                    selectClassName,
                ]
                    .filter(Boolean)
                    .join(' ')}
                {...props}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <ErrorMessage message={error} />
        </div>
    );
}
