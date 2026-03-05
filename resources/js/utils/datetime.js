const EMPTY_VALUE = '-';

const pad = (value) => String(value).padStart(2, '0');

const DDMMYYYY_REGEX = /^(\d{2})-(\d{2})-(\d{4})$/;
const YYYYMMDD_REGEX = /^(\d{4})-(\d{2})-(\d{2})$/;

const toDate = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'string') {
        const normalized = value.trim();
        if (normalized === '') {
            return null;
        }

        const ddmmyyyyMatch = normalized.match(DDMMYYYY_REGEX);
        if (ddmmyyyyMatch) {
            const [, day, month, year] = ddmmyyyyMatch;
            const parsed = new Date(Number(year), Number(month) - 1, Number(day));
            if (
                !Number.isNaN(parsed.getTime()) &&
                parsed.getDate() === Number(day) &&
                parsed.getMonth() === Number(month) - 1 &&
                parsed.getFullYear() === Number(year)
            ) {
                return parsed;
            }
            return null;
        }

        const yyyymmddMatch = normalized.match(YYYYMMDD_REGEX);
        if (yyyymmddMatch) {
            const [, year, month, day] = yyyymmddMatch;
            const parsed = new Date(Number(year), Number(month) - 1, Number(day));
            if (
                !Number.isNaN(parsed.getTime()) &&
                parsed.getDate() === Number(day) &&
                parsed.getMonth() === Number(month) - 1 &&
                parsed.getFullYear() === Number(year)
            ) {
                return parsed;
            }
            return null;
        }

        if (/^\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2}(:\d{2})?$/.test(normalized)) {
            const parsed = new Date(normalized.replace(' ', 'T'));
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        }
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatInTimeZone = (value, { includeDate = true, includeTime = true } = {}, timeZone = null, fallback = EMPTY_VALUE) => {
    const date = toDate(value);
    if (!date) {
        return fallback;
    }

    try {
        const options = {};
        if (includeDate) {
            options.day = '2-digit';
            options.month = '2-digit';
            options.year = 'numeric';
        }
        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
            options.hour12 = true;
        }
        if (timeZone) {
            options.timeZone = timeZone;
        }

        const formatter = new Intl.DateTimeFormat('en-GB', options);
        const parts = formatter.formatToParts(date).reduce((acc, part) => {
            acc[part.type] = part.value;
            return acc;
        }, {});

        const datePart = includeDate ? `${parts.day}-${parts.month}-${parts.year}` : '';
        const period = (parts.dayPeriod || '').toUpperCase();
        const timePart = includeTime ? `${parts.hour}:${parts.minute}${period ? ` ${period}` : ''}` : '';

        if (includeDate && includeTime) {
            return `${datePart} ${timePart}`.trim();
        }

        return includeDate ? datePart : timePart;
    } catch (_error) {
        if (includeDate && includeTime) {
            return formatDateTime(date, fallback);
        }
        if (includeDate) {
            return formatDate(date, fallback);
        }
        return formatTime(date, fallback);
    }
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

export const formatDateInTimeZone = (value, timeZone, fallback = EMPTY_VALUE) =>
    formatInTimeZone(value, { includeDate: true, includeTime: false }, timeZone, fallback);

export const formatTimeInTimeZone = (value, timeZone, fallback = EMPTY_VALUE) =>
    formatInTimeZone(value, { includeDate: false, includeTime: true }, timeZone, fallback);

export const formatDateTimeInTimeZone = (value, timeZone, fallback = EMPTY_VALUE) =>
    formatInTimeZone(value, { includeDate: true, includeTime: true }, timeZone, fallback);

export const parseDate = (value) => {
    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim();
    if (normalized === '') {
        return null;
    }

    let year;
    let month;
    let day;

    let match = normalized.match(DDMMYYYY_REGEX);
    if (match) {
        [, day, month, year] = match;
    } else {
        match = normalized.match(YYYYMMDD_REGEX);
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
    console.assert(formatDateInTimeZone('2026-02-25T13:15:00Z', 'UTC').startsWith('25-02-2026'), 'Timezone date should be DD-MM-YYYY.');
}
