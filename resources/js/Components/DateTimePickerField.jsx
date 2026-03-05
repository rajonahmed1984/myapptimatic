import React, { useEffect, useMemo, useRef, useState } from 'react';
import flatpickr from 'flatpickr';
import {
    formatDateTimeDisplay,
    formatDateTimeSubmit,
    parseDateTimeValue,
} from '../utils/easyDate';

const joinClass = (...parts) => parts.filter(Boolean).join(' ');

export default function DateTimePickerField({
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
    submitFormat = 'iso',
    id = null,
    placeholder = 'DD-MM-YYYY hh:mm AM',
    containerClassName = '',
    inputClassName = '',
    labelClassName = 'text-xs text-slate-500',
    hideLabel = false,
}) {
    const controlled = value !== undefined;
    const initialDateTime = useMemo(
        () => parseDateTimeValue(controlled ? value : defaultValue),
        [controlled, value, defaultValue],
    );

    const inputRef = useRef(null);
    const pickerRef = useRef(null);

    const [submitValue, setSubmitValue] = useState(formatDateTimeSubmit(initialDateTime, submitFormat));
    const [displayValue, setDisplayValue] = useState(formatDateTimeDisplay(initialDateTime));

    const normalizedMinDate = useMemo(() => parseDateTimeValue(minDate), [minDate]);
    const normalizedMaxDate = useMemo(() => parseDateTimeValue(maxDate), [maxDate]);
    const resolvedId = id || `datetime-field-${String(name || 'datetime').replace(/[^a-z0-9_-]/gi, '-')}`;

    useEffect(() => {
        if (!inputRef.current) {
            return undefined;
        }

        const instance = flatpickr(inputRef.current, {
            allowInput: false,
            clickOpens: !disabled,
            disableMobile: true,
            enableTime: true,
            time_24hr: false,
            dateFormat: 'd-m-Y h:i K',
            defaultDate: initialDateTime || undefined,
            minDate: normalizedMinDate || undefined,
            maxDate: normalizedMaxDate || undefined,
            minuteIncrement: 5,
            onReady: () => {
                inputRef.current?.setAttribute('data-easy-date-ignore', '1');
            },
            onChange: (selectedDates) => {
                const selectedDateTime = selectedDates?.[0] || null;
                const nextDisplay = formatDateTimeDisplay(selectedDateTime);
                const nextSubmit = formatDateTimeSubmit(selectedDateTime, submitFormat);

                setDisplayValue(nextDisplay);
                setSubmitValue(nextSubmit);

                if (onChange) {
                    onChange(nextSubmit, selectedDateTime);
                }
            },
        });

        pickerRef.current = instance;

        return () => {
            instance.destroy();
            pickerRef.current = null;
        };
    }, [disabled, initialDateTime, maxDate, minDate, normalizedMaxDate, normalizedMinDate, onChange, submitFormat]);

    useEffect(() => {
        if (!controlled) {
            return;
        }

        const nextDateTime = parseDateTimeValue(value);
        const nextDisplay = formatDateTimeDisplay(nextDateTime);
        const nextSubmit = formatDateTimeSubmit(nextDateTime, submitFormat);

        setDisplayValue(nextDisplay);
        setSubmitValue(nextSubmit);

        if (pickerRef.current) {
            if (nextDateTime) {
                pickerRef.current.setDate(nextDateTime, false, 'd-m-Y h:i K');
            } else {
                pickerRef.current.clear(false);
            }
        }
    }, [controlled, submitFormat, value]);

    const wrapperClass = joinClass('space-y-1', containerClassName);
    const fieldClass = joinClass(
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

            <input type="hidden" name={name} value={submitValue} />

            <input
                ref={inputRef}
                id={resolvedId}
                type="text"
                value={displayValue}
                readOnly
                required={required}
                disabled={disabled}
                placeholder={placeholder}
                className={fieldClass}
                onChange={() => {}}
            />

            {error ? <p className="text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}
