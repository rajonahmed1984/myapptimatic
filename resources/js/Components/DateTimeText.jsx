import React from 'react';
import { formatDate, formatDateTime, formatTime } from '../utils/datetime';

const normalizeMode = (mode) => {
    if (mode === 'date' || mode === 'time' || mode === 'datetime') {
        return mode;
    }

    return 'datetime';
};

const formatByMode = (value, mode) => {
    const safeMode = normalizeMode(mode);
    const fallback = '';

    if (safeMode === 'date') {
        return formatDate(value, fallback);
    }

    if (safeMode === 'time') {
        return formatTime(value, fallback);
    }

    return formatDateTime(value, fallback);
};

export default function DateTimeText({
    value,
    mode = 'datetime',
    className = '',
    fallback = '--',
    title = null,
}) {
    const hasValue = value !== null && value !== undefined && value !== '';
    const formatted = hasValue ? formatByMode(value, mode) : '';
    const display = formatted || (hasValue ? String(value) : fallback);
    const safeTitle = title ?? display;

    return (
        <span
            className={`datetime-nowrap inline-block max-w-full whitespace-nowrap tabular-nums ${className}`.trim()}
            title={safeTitle}
        >
            {display}
        </span>
    );
}
