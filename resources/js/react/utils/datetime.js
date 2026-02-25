const EMPTY_VALUE = '-';

const pad = (value) => String(value).padStart(2, '0');

const toDate = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

export const formatDate = (value, fallback = EMPTY_VALUE) => {
    const date = toDate(value);
    if (!date) {
        return fallback;
    }

    return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`;
};

export const formatTime = (value, fallback = EMPTY_VALUE) => {
    const date = toDate(value);
    if (!date) {
        return fallback;
    }

    const hours = date.getHours();
    const minutes = pad(date.getMinutes());
    const period = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours % 12 || 12;

    return `${pad(displayHour)}:${minutes} ${period}`;
};

export const formatDateTime = (value, fallback = EMPTY_VALUE) => {
    const datePart = formatDate(value, '');
    const timePart = formatTime(value, '');

    if (!datePart || !timePart) {
        return fallback;
    }

    return `${datePart} ${timePart}`;
};

export const parseDate = (value) => {
    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim();
    if (normalized === '') {
        return null;
    }

    const ddmmyyyy = /^(\d{2})-(\d{2})-(\d{4})$/;
    const yyyymmdd = /^(\d{4})-(\d{2})-(\d{2})$/;

    let year;
    let month;
    let day;

    let match = normalized.match(ddmmyyyy);
    if (match) {
        [, day, month, year] = match;
    } else {
        match = normalized.match(yyyymmdd);
        if (!match) {
            return null;
        }
        [, year, month, day] = match;
    }

    const parsed = new Date(Number(year), Number(month) - 1, Number(day));
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    if (parsed.getDate() !== Number(day) || parsed.getMonth() !== Number(month) - 1 || parsed.getFullYear() !== Number(year)) {
        return null;
    }

    return parsed;
};

// Lightweight runtime sanity checks for local/dev builds.
if (import.meta?.env?.DEV) {
    console.assert(formatDate('2026-02-25T00:00:00Z').includes('-'), 'formatDate should return DD-MM-YYYY.');
    console.assert(formatTime('2026-02-25T13:15:00Z').includes('M'), 'formatTime should include AM/PM.');
    console.assert(parseDate('25-02-2026') instanceof Date, 'parseDate should parse DD-MM-YYYY.');
}

