import React from 'react';
import SearchableSelect from '../SearchableSelect';

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
            <SearchableSelect
                name={name}
                defaultValue={defaultValue}
                value={value}
                options={normalizedOptions}
                onChange={handleChange}
                required={required}
                disabled={disabled}
                placeholder={placeholder}
                className="mt-2"
                triggerClassName={[
                    'h-10 rounded-xl border-white/20 px-4 text-sm text-slate-900',
                    selectClassName,
                ].filter(Boolean).join(' ')}
                error={error}
                {...props}
            />
        </div>
    );
}
