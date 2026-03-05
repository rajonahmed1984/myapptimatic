const DDMMYYYY_REGEX = /^(\d{2})-(\d{2})-(\d{4})$/;
const YYYYMMDD_REGEX = /^(\d{4})-(\d{2})-(\d{2})$/;

const pad = (value) => String(value).padStart(2, '0');

export const isValidDate = (value) => value instanceof Date && !Number.isNaN(value.getTime());

export const parseDateValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (isValidDate(value)) {
        return new Date(value.getFullYear(), value.getMonth(), value.getDate());
    }

    if (typeof value === 'string') {
        const normalized = value.trim();
        if (normalized === '') {
            return null;
        }

        let day;
        let month;
        let year;

        const dmyMatch = normalized.match(DDMMYYYY_REGEX);
        if (dmyMatch) {
            [, day, month, year] = dmyMatch;
        } else {
            const ymdMatch = normalized.match(YYYYMMDD_REGEX);
            if (ymdMatch) {
                [, year, month, day] = ymdMatch;
            } else {
                const parsed = new Date(normalized.replace(' ', 'T'));
                if (!isValidDate(parsed)) {
                    return null;
                }
                return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
            }
        }

        const parsed = new Date(Number(year), Number(month) - 1, Number(day));
        if (!isValidDate(parsed)) {
            return null;
        }

        if (
            parsed.getFullYear() !== Number(year) ||
            parsed.getMonth() !== Number(month) - 1 ||
            parsed.getDate() !== Number(day)
        ) {
            return null;
        }

        return parsed;
    }

    const parsed = new Date(value);
    if (!isValidDate(parsed)) {
        return null;
    }

    return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
};

export const parseDateTimeValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (isValidDate(value)) {
        return new Date(value);
    }

    if (typeof value === 'string') {
        const normalized = value.trim();
        if (normalized === '') {
            return null;
        }

        const parsed = new Date(normalized.replace(' ', 'T'));
        if (isValidDate(parsed)) {
            return parsed;
        }
    }

    const parsed = new Date(value);
    return isValidDate(parsed) ? parsed : null;
};

export const formatDateDisplay = (value) => {
    const date = parseDateValue(value);
    if (!date) return '';

    return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`;
};

export const formatDateIso = (value) => {
    const date = parseDateValue(value);
    if (!date) return '';

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

export const formatDateSubmit = (value, submitFormat = 'dmy') => (
    submitFormat === 'iso' ? formatDateIso(value) : formatDateDisplay(value)
);

export const inferDateSubmitFormat = (value, fallback = 'dmy') => {
    if (typeof value !== 'string') {
        return fallback;
    }

    const normalized = value.trim();
    if (YYYYMMDD_REGEX.test(normalized)) {
        return 'iso';
    }

    return fallback;
};

export const formatDateTimeDisplay = (value) => {
    const date = parseDateTimeValue(value);
    if (!date) return '';

    const hour24 = date.getHours();
    const period = hour24 >= 12 ? 'PM' : 'AM';
    const hour12 = hour24 % 12 || 12;

    return `${formatDateDisplay(date)} ${pad(hour12)}:${pad(date.getMinutes())} ${period}`;
};

export const formatDateTimeIsoSubmit = (value) => {
    const date = parseDateTimeValue(value);
    if (!date) return '';

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

export const formatDateTimeSubmit = (value, submitFormat = 'iso') => (
    submitFormat === 'dmy' ? formatDateTimeDisplay(value) : formatDateTimeIsoSubmit(value)
);

const startOfDay = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());
const endOfMonth = (year, month) => new Date(year, month + 1, 0);

export const resolveDateRangePreset = (presetKey, now = new Date()) => {
    const today = startOfDay(now);
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);

    if (presetKey === 'today') {
        return { start: today, end: today };
    }
    if (presetKey === 'yesterday') {
        return { start: yesterday, end: yesterday };
    }
    if (presetKey === 'last_7_days') {
        const start = new Date(today);
        start.setDate(today.getDate() - 6);
        return { start, end: today };
    }
    if (presetKey === 'last_30_days') {
        const start = new Date(today);
        start.setDate(today.getDate() - 29);
        return { start, end: today };
    }
    if (presetKey === 'this_month') {
        const start = new Date(today.getFullYear(), today.getMonth(), 1);
        return { start, end: today };
    }
    if (presetKey === 'last_month') {
        const year = today.getMonth() === 0 ? today.getFullYear() - 1 : today.getFullYear();
        const month = today.getMonth() === 0 ? 11 : today.getMonth() - 1;
        const start = new Date(year, month, 1);
        const end = endOfMonth(year, month);
        return { start, end };
    }

    return { start: null, end: null };
};
