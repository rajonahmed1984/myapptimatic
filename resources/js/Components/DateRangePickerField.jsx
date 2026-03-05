import React, { useEffect, useMemo, useState } from 'react';
import DatePickerField from './DatePickerField';
import { formatDateSubmit, resolveDateRangePreset } from '../utils/easyDate';

const DEFAULT_PRESETS = [
    { key: 'today', label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    { key: 'last_7_days', label: 'Last 7 days' },
    { key: 'last_30_days', label: 'Last 30 days' },
    { key: 'this_month', label: 'This month' },
    { key: 'last_month', label: 'Last month' },
];

export default function DateRangePickerField({
    startName = 'start_date',
    endName = 'end_date',
    startValue = '',
    endValue = '',
    onChange = null,
    startLabel = 'Start date',
    endLabel = 'End date',
    submitFormat = 'dmy',
    presets = DEFAULT_PRESETS,
    showPresets = true,
    required = false,
    disabled = false,
    errors = {},
    className = '',
    gridClassName = 'grid gap-3 md:grid-cols-2',
    inputClassName = '',
}) {
    const [range, setRange] = useState({
        start: String(startValue || ''),
        end: String(endValue || ''),
    });

    useEffect(() => {
        setRange({
            start: String(startValue || ''),
            end: String(endValue || ''),
        });
    }, [startValue, endValue]);

    const updateRange = (nextStart, nextEnd) => {
        const next = { start: nextStart, end: nextEnd };
        setRange(next);

        if (onChange) {
            onChange(next);
        }
    };

    const handlePreset = (presetKey) => {
        const { start, end } = resolveDateRangePreset(presetKey);
        const startSubmit = formatDateSubmit(start, submitFormat);
        const endSubmit = formatDateSubmit(end, submitFormat);
        updateRange(startSubmit, endSubmit);
    };

    const presetButtons = useMemo(
        () => (Array.isArray(presets) ? presets : []),
        [presets],
    );

    return (
        <div className={className}>
            {showPresets ? (
                <div className="mb-2 flex flex-wrap gap-2">
                    {presetButtons.map((preset) => (
                        <button
                            key={preset.key}
                            type="button"
                            onClick={() => handlePreset(preset.key)}
                            disabled={disabled}
                            className="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {preset.label}
                        </button>
                    ))}
                </div>
            ) : null}

            <div className={gridClassName}>
                <DatePickerField
                    name={startName}
                    value={range.start}
                    onChange={(nextValue) => updateRange(nextValue, range.end)}
                    label={startLabel}
                    required={required}
                    disabled={disabled}
                    submitFormat={submitFormat}
                    error={errors?.start || null}
                    inputClassName={inputClassName}
                />

                <DatePickerField
                    name={endName}
                    value={range.end}
                    onChange={(nextValue) => updateRange(range.start, nextValue)}
                    label={endLabel}
                    required={required}
                    disabled={disabled}
                    submitFormat={submitFormat}
                    error={errors?.end || null}
                    inputClassName={inputClassName}
                />
            </div>
        </div>
    );
}
