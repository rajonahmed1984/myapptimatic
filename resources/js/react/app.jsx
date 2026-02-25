import '../bootstrap';
import React, { useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { formatDate, parseDate } from './utils/datetime';

const DISPLAY_DATE_PLACEHOLDER = 'DD-MM-YYYY';

const normalizeDisplayDateValue = (value) => {
    if (typeof value !== 'string') {
        return value;
    }

    const trimmed = value.trim();
    if (trimmed === '') {
        return '';
    }

    const parsed = parseDate(trimmed);
    if (!parsed) {
        return value;
    }

    return formatDate(parsed, value);
};

const normalizeDateInputsInDocument = () => {
    const inputs = document.querySelectorAll(`input[placeholder="${DISPLAY_DATE_PLACEHOLDER}"]`);
    inputs.forEach((input) => {
        const normalized = normalizeDisplayDateValue(input.value);
        if (normalized !== input.value) {
            input.value = normalized;
        }
    });
};

function DateInputRuntimeAdapter({ App, props }) {
    useEffect(() => {
        normalizeDateInputsInDocument();

        const handleFocusOut = (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }
            if (target.placeholder !== DISPLAY_DATE_PLACEHOLDER) {
                return;
            }

            const normalized = normalizeDisplayDateValue(target.value);
            if (normalized !== target.value) {
                target.value = normalized;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        document.addEventListener('focusout', handleFocusOut, true);

        return () => {
            document.removeEventListener('focusout', handleFocusOut, true);
        };
    }, [props]);

    return <App {...props} />;
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page.default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<DateInputRuntimeAdapter App={App} props={props} />);
    },
});
