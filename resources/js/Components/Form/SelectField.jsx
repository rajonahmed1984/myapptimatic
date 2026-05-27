import React from 'react';
import ErrorMessage from './ErrorMessage';

export default function SelectField({
    label = null,
    name,
    defaultValue = '',
    value,
    options = [],
    className = '',
    selectClassName = '',
    error = null,
    onChange,
    required = false,
    disabled = false,
    placeholder = 'Select an option',
    ...props
}) {
    const normalizedOptions = (Array.isArray(options) ? options : []).map((option) => ({
        value: String(option?.value ?? ''),
        label: String(option?.label ?? ''),
    }));

    const handleChange = (nextValue, selectedOption) => {
        if (typeof onChange !== 'function') {
            return;
        }

        onChange(
            {
                target: {
                    name,
                    value: nextValue,
                },
            },
            selectedOption,
        );
    };

    return (
        <div className={className}>
            {label ? <label className="text-sm text-slate-200/85">{label}</label> : null}
            <select
                name={name}
                defaultValue={defaultValue}
                value={value}
                onChange={(event) => handleChange(event.target.value, null)}
                required={required}
                disabled={disabled}
                className={[
                    'mt-2 w-full h-8 rounded-full border border-slate-300 bg-white px-4 py-1.5 text-left text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-teal-600',
                    error ? 'border-rose-500 focus:ring-rose-500' : '',
                    selectClassName,
                ].filter(Boolean).join(' ')}
                {...props}
            >
                {placeholder ? <option value="">{placeholder}</option> : null}
                {normalizedOptions.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                ))}
            </select>
            <ErrorMessage message={error} />
        </div>
    );
}
