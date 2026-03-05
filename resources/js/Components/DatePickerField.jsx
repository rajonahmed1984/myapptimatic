import React, { useEffect, useMemo, useRef, useState } from 'react';
import flatpickr from 'flatpickr';
import {
    formatDateDisplay,
    formatDateSubmit,
    inferDateSubmitFormat,
    parseDateValue,
} from '../utils/easyDate';

const combineClass = (...values) => values.filter(Boolean).join(' ');

export default function DatePickerField({
    name,
    value,
    defaultValue = '',
    onChange,
    error = null,
    label = '',
    required = false,
    disabled = false,
    minDate = null,
    maxDate = null,
    id = null,
    submitFormat = null,
    placeholder = 'DD-MM-YYYY',
    containerClassName = '',
    inputClassName = '',
    labelClassName = 'text-xs text-slate-500',
    hideLabel = false,
}) {
    const inferredFormat = useMemo(
        () => submitFormat || inferDateSubmitFormat(value ?? defaultValue, 'dmy'),
        [submitFormat, value, defaultValue],
    );

    const controlled = value !== undefined;
    const initialDate = useMemo(() => parseDateValue(controlled ? value : defaultValue), [controlled, value, defaultValue]);

    const inputRef = useRef(null);
    const pickerRef = useRef(null);

    const [submitValue, setSubmitValue] = useState(formatDateSubmit(initialDate, inferredFormat));
    const [displayValue, setDisplayValue] = useState(formatDateDisplay(initialDate));

    const normalizedMinDate = useMemo(() => parseDateValue(minDate), [minDate]);
    const normalizedMaxDate = useMemo(() => parseDateValue(maxDate), [maxDate]);
    const resolvedId = id || `date-field-${String(name || 'date').replace(/[^a-z0-9_-]/gi, '-')}`;

    useEffect(() => {
        if (!inputRef.current) {
            return undefined;
        }

        const instance = flatpickr(inputRef.current, {
            allowInput: false,
            clickOpens: !disabled,
            disableMobile: true,
            dateFormat: 'd-m-Y',
            defaultDate: initialDate || undefined,
            minDate: normalizedMinDate || undefined,
            maxDate: normalizedMaxDate || undefined,
            monthSelectorType: 'static',
            onReady: () => {
                inputRef.current?.setAttribute('data-easy-date-ignore', '1');
            },
            onChange: (selectedDates) => {
                const selectedDate = selectedDates?.[0] || null;
                const nextDisplay = formatDateDisplay(selectedDate);
                const nextSubmit = formatDateSubmit(selectedDate, inferredFormat);

                setDisplayValue(nextDisplay);
                setSubmitValue(nextSubmit);

                if (onChange) {
                    onChange(nextSubmit, selectedDate);
                }
            },
        });

        pickerRef.current = instance;

        return () => {
            instance.destroy();
            pickerRef.current = null;
        };
    }, [disabled, inferredFormat, initialDate, normalizedMaxDate, normalizedMinDate, onChange]);

    useEffect(() => {
        if (!controlled) {
            return;
        }

        const nextDate = parseDateValue(value);
        const nextDisplay = formatDateDisplay(nextDate);
        const nextSubmit = formatDateSubmit(nextDate, inferredFormat);

        setDisplayValue(nextDisplay);
        setSubmitValue(nextSubmit);

        if (pickerRef.current) {
            if (nextDate) {
                pickerRef.current.setDate(nextDate, false, 'd-m-Y');
            } else {
                pickerRef.current.clear(false);
            }
        }
    }, [controlled, inferredFormat, value]);

    useEffect(() => {
        if (!pickerRef.current) {
            return;
        }

        pickerRef.current.set('minDate', normalizedMinDate || null);
        pickerRef.current.set('maxDate', normalizedMaxDate || null);
    }, [normalizedMaxDate, normalizedMinDate]);

    const wrapperClass = combineClass('space-y-1', containerClassName);
    const baseInputClass = combineClass(
        'w-full rounded-xl border bg-white px-3 py-2 text-sm whitespace-nowrap',
        error ? 'border-rose-300 text-rose-700' : 'border-slate-300 text-slate-700',
        disabled ? 'cursor-not-allowed bg-slate-100 text-slate-500' : '',
        inputClassName,
    );

    return (
        <div className={wrapperClass} data-easy-date-field="true">
            {!hideLabel && label ? (
                <label htmlFor={resolvedId} className={labelClassName}>{label}</label>
            ) : null}

            <input
                type="hidden"
                name={name}
                value={submitValue}
            />

            <input
                ref={inputRef}
                id={resolvedId}
                type="text"
                value={displayValue}
                readOnly
                required={required}
                disabled={disabled}
                placeholder={placeholder}
                className={baseInputClass}
                onChange={() => {}}
            />

            {error ? <p className="text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}
