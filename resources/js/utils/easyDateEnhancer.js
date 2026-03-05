import flatpickr from 'flatpickr';
import { parseDateValue } from './easyDate';

const CANDIDATE_SELECTOR = [
    'input[type="date"]',
    'input[placeholder="DD-MM-YYYY"]',
    'input[placeholder="YYYY-MM-DD"]',
    'input[data-easy-date="true"]',
].join(',');

const inferSubmitFormat = (input) => {
    const dataFormat = String(input?.dataset?.submitFormat || '').trim().toLowerCase();
    if (dataFormat === 'iso' || dataFormat === 'dmy') {
        return dataFormat;
    }

    const placeholder = String(input?.getAttribute('placeholder') || '').trim().toUpperCase();
    if (placeholder === 'YYYY-MM-DD') {
        return 'iso';
    }

    if (String(input?.type || '').toLowerCase() === 'date') {
        return 'iso';
    }

    return 'dmy';
};

const shouldSkip = (input) => {
    if (!(input instanceof HTMLInputElement)) {
        return true;
    }

    if (input.dataset.easyDateIgnore === '1' || input.closest('[data-easy-date-field="true"]')) {
        return true;
    }

    if (input.disabled || input.readOnly) {
        return true;
    }

    if (input.type !== 'date' && input.type !== 'text') {
        return true;
    }

    return false;
};

const toDateOrNull = (value) => parseDateValue(value) || null;

const applyFlatpickr = (input) => {
    const submitFormat = inferSubmitFormat(input);
    const dateFormat = submitFormat === 'iso' ? 'Y-m-d' : 'd-m-Y';
    const defaultDate = toDateOrNull(input.value);
    const minDate = toDateOrNull(input.getAttribute('min'));
    const maxDate = toDateOrNull(input.getAttribute('max'));
    const currentClassName = input.className || '';

    const instance = flatpickr(input, {
        allowInput: false,
        clickOpens: true,
        disableMobile: true,
        dateFormat,
        altInput: submitFormat === 'iso',
        altFormat: 'd-m-Y',
        defaultDate: defaultDate || undefined,
        minDate: minDate || undefined,
        maxDate: maxDate || undefined,
        monthSelectorType: 'static',
        onReady: (_selectedDates, _dateStr, flatpickrInstance) => {
            const alt = flatpickrInstance.altInput;
            if (alt) {
                alt.className = currentClassName;
                alt.classList.add('whitespace-nowrap');
                alt.setAttribute('data-easy-date-ignore', '1');
            } else {
                input.classList.add('whitespace-nowrap');
            }

            input.setAttribute('data-easy-date-ignore', '1');
            input.setAttribute('data-easy-date-format', submitFormat);
        },
    });

    return instance;
};

export const enhanceEasyDateInputsInDocument = (root = document) => {
    if (!root?.querySelectorAll) {
        return [];
    }

    const instances = [];
    const nodes = root.querySelectorAll(CANDIDATE_SELECTOR);

    nodes.forEach((input) => {
        if (shouldSkip(input)) {
            return;
        }

        if (input._flatpickr) {
            return;
        }

        const instance = applyFlatpickr(input);
        if (instance) {
            instances.push(instance);
        }
    });

    return instances;
};
